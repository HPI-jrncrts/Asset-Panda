<?php
/**
 * The library's AI helper, through WordPress's own AI client (WordPress 7:
 * wp_ai_client_prompt(), with the provider and key the site set up under
 * Settings → Connectors). This plugin never sees or stores an API key.
 *
 *   GET  ap-builder/v1/ai/status   ready | no-provider | disabled | unsupported (cached 10 minutes)
 *   POST ap-builder/v1/ai          mode "build":   a description → 1–4 of the site's own section designs,
 *                                                  in order, with new copy written into their text fields
 *                                  mode "rewrite": the selected block's text fields + an instruction →
 *                                                  the same fields, rewritten
 *
 * The model never writes markup: it picks section keys from the catalog and
 * returns text for named fields; the server copies the real section and sets
 * only those fields. So everything it builds is a designed section, styled
 * like the rest of the site. Editor users only; 40 requests per user per hour.
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_BUILDER_AI_CATALOG_CACHE = 'ap_builder_ai_catalog_v1';

/** Keys that are never copy (geometry, tokens, ids, markup, links). */
function ap_builder_ai_not_copy( $key ) {
	return (bool) preg_match( '/^(x|y|w|h|mx|my|ix|iw|zoom|left|right|top|end|height|width|anchor|slot|tone|link_tone|accent|cls|class|state|kind|key|format|fit|crop|float|flag|form_id|video_id|video_start|video_end|hover_slot|hover_bg|style|size|type|group|markup|icon|icon_svg|image|logo|background|avatar|photo|mark)$|(^|_)(url|href|svg|id|w|h|image|logo|icon|alt)$/', (string) $key );
}

/**
 * A block's copy fields: path ("title", "items.0.body") => current text.
 *
 * @param array $attrs Block attributes.
 * @return array<string, string>
 */
function ap_builder_ai_fields( array $attrs ) {
	$out = array();
	foreach ( $attrs as $key => $value ) {
		if ( ap_builder_ai_not_copy( $key ) ) {
			continue;
		}
		if ( is_string( $value ) ) {
			$text = trim( $value );
			if ( '' !== $text && ! preg_match( '#^(https?:)?/|<[a-z]#i', $text ) && strlen( $text ) <= 1200 ) {
				$out[ $key ] = $text;
			}
			continue;
		}
		if ( is_array( $value ) && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
			foreach ( array_slice( $value, 0, 8 ) as $i => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( $row as $field => $text ) {
					if ( is_string( $text ) && ! ap_builder_ai_not_copy( $field ) && '' !== trim( $text ) && ! preg_match( '#^(https?:)?/|<[a-z]#i', trim( $text ) ) && strlen( $text ) <= 1200 ) {
						$out[ $key . '.' . $i . '.' . $field ] = trim( $text );
					}
				}
			}
		}
	}
	return $out;
}

/**
 * Sets text at a field path, only where a string already is.
 *
 * @param array  $attrs Block attributes.
 * @param string $path  "title" or "items.0.body".
 * @param string $text  New text.
 * @return array
 */
function ap_builder_ai_set( array $attrs, $path, $text ) {
	$parts = explode( '.', (string) $path );
	if ( 1 === count( $parts ) ) {
		if ( isset( $attrs[ $parts[0] ] ) && is_string( $attrs[ $parts[0] ] ) && ! ap_builder_ai_not_copy( $parts[0] ) ) {
			$attrs[ $parts[0] ] = $text;
		}
		return $attrs;
	}
	if ( 3 === count( $parts ) && ctype_digit( $parts[1] ) && isset( $attrs[ $parts[0] ][ (int) $parts[1] ][ $parts[2] ] )
		&& is_string( $attrs[ $parts[0] ][ (int) $parts[1] ][ $parts[2] ] ) && ! ap_builder_ai_not_copy( $parts[2] ) ) {
		$attrs[ $parts[0] ][ (int) $parts[1] ][ $parts[2] ] = $text;
	}
	return $attrs;
}

/** The section catalog as the model sees it: key, name, group, look, and each copy field with its sample text (cached). */
function ap_builder_ai_catalog() {
	$cached = get_transient( AP_BUILDER_AI_CATALOG_CACHE );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$out = array();
	foreach ( array_slice( ap_builder_sections(), 0, 160 ) as $item ) {
		$block = ap_builder_section_block( $item['key'] );
		if ( ! $block ) {
			continue;
		}
		$fields = array();
		foreach ( ap_builder_ai_fields( (array) $block['attrs'] ) as $path => $text ) {
			$fields[ $path ] = wp_html_excerpt( $text, 110, '…' );
			if ( count( $fields ) >= 24 ) {
				break;
			}
		}
		if ( ! $fields ) {
			continue;
		}
		$out[] = array(
			'key'     => $item['key'],
			'section' => $item['title'] . ( '' !== $item['variant'] ? ' (' . $item['variant'] . ')' : '' ),
			'group'   => $item['group'],
			'fields'  => $fields,
		);
	}
	set_transient( AP_BUILDER_AI_CATALOG_CACHE, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'page', 'resource' ), true ) && ! wp_is_post_revision( $post_id ) ) {
			delete_transient( AP_BUILDER_AI_CATALOG_CACHE );
		}
	},
	10,
	2
);

/** Whether text generation can run (cached 10 minutes; the check may ask the providers for their models). */
function ap_builder_ai_status() {
	if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
		return 'unsupported';
	}
	if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
		return 'disabled';
	}
	$cached = get_transient( 'ap_builder_ai_status' );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	try {
		$status = wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation() ? 'ready' : 'no-provider';
	} catch ( \Throwable $e ) {
		$status = 'no-provider';
	}
	set_transient( 'ap_builder_ai_status', $status, 10 * MINUTE_IN_SECONDS );
	return $status;
}

/**
 * Runs one prompt and returns the decoded JSON object.
 *
 * @param string $system System instruction.
 * @param string $user   User prompt.
 * @param array  $schema JSON schema of the answer.
 * @return array|WP_Error
 */
function ap_builder_ai_json( $system, $user, array $schema ) {
	try {
		$text = wp_ai_client_prompt( $user )
			->using_system_instruction( $system )
			->using_max_tokens( 4000 )
			->using_temperature( 0.5 )
			->as_json_response( $schema )
			->generate_text();
	} catch ( \Throwable $e ) {
		return new WP_Error( 'ap_builder_ai_failed', 'The AI request failed: ' . $e->getMessage(), array( 'status' => 502 ) );
	}
	if ( is_wp_error( $text ) ) {
		return new WP_Error( 'ap_builder_ai_failed', $text->get_error_message(), array( 'status' => 502 ) );
	}
	$text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( (string) $text ) ) );
	$data = json_decode( $text, true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ap_builder_ai_unreadable', 'The AI answer could not be read. Try again.', array( 'status' => 502 ) );
	}
	return $data;
}

/** The brand voice every prompt shares. */
function ap_builder_ai_voice() {
	return 'You write for Asset Panda, cloud asset management and tracking software (asset tracking, inventory, maintenance, audits, '
		. 'check-in/check-out, barcode and RFID, the Asset Intelligence Platform and UrsaAI) used by IT, operations, government, education, '
		. 'healthcare, public safety and construction teams. Voice: clear, confident, specific, plain US English, short sentences, no hype words, '
		. 'no exclamation marks. Never invent statistics, prices, customer names, quotes, awards or integrations: when a field held a number, '
		. 'a name or a quote, keep the original text unless the request supplies a new one.';
}

/**
 * The build request: a description → designed sections with new copy.
 *
 * @param string $prompt Description.
 * @return array|WP_Error { markup, note }
 */
function ap_builder_ai_build( $prompt ) {
	$catalog = ap_builder_ai_catalog();
	if ( ! $catalog ) {
		return new WP_Error( 'ap_builder_ai_empty', 'The section library is empty.', array( 'status' => 500 ) );
	}
	$keys   = wp_list_pluck( $catalog, 'key' );
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'note'     => array( 'type' => 'string' ),
			'sections' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'key'    => array(
							'type' => 'string',
							'enum' => array_values( $keys ),
						),
						'fields' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'path' => array( 'type' => 'string' ),
									'text' => array( 'type' => 'string' ),
								),
								'required'   => array( 'path', 'text' ),
							),
						),
					),
					'required'   => array( 'key', 'fields' ),
				),
			),
		),
		'required'   => array( 'sections', 'note' ),
	);
	$system = ap_builder_ai_voice() . "\n\n"
		. 'You assemble website sections from a fixed catalog of designed sections. Each catalog entry has a key, a section name with its look, '
		. 'a group, and its text fields (path => sample text). Pick 1 to 4 entries that best build what the editor asks for, in page order, and '
		. 'write new text for their fields. Use only keys from the catalog and only the field paths listed for that key; write every field '
		. 'that the new content changes and leave out the rest; keep each text about as long as its sample (headings short, body copy one to '
		. 'three sentences). Reply with JSON only: {"sections":[{"key":"…","fields":[{"path":"…","text":"…"}]}],"note":"one sentence on what you built"}.'
		. "\n\nCATALOG:\n" . wp_json_encode( $catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$data   = ap_builder_ai_json( $system, $prompt, $schema );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$blocks = array();
	foreach ( array_slice( (array) ( isset( $data['sections'] ) ? $data['sections'] : array() ), 0, 4 ) as $pick ) {
		$key   = isset( $pick['key'] ) ? (string) $pick['key'] : '';
		$block = in_array( $key, $keys, true ) ? ap_builder_section_block( $key ) : null;
		if ( ! $block ) {
			continue;
		}
		foreach ( (array) ( isset( $pick['fields'] ) ? $pick['fields'] : array() ) as $field ) {
			if ( isset( $field['path'], $field['text'] ) && is_string( $field['text'] ) ) {
				$block['attrs'] = ap_builder_ai_set( (array) $block['attrs'], (string) $field['path'], sanitize_textarea_field( $field['text'] ) );
			}
		}
		$blocks[] = $block;
	}
	if ( ! $blocks ) {
		return new WP_Error( 'ap_builder_ai_nothing', 'The AI did not pick any section. Describe the section a little more and try again.', array( 'status' => 422 ) );
	}
	return array(
		'markup' => serialize_blocks( $blocks ),
		'note'   => isset( $data['note'] ) ? sanitize_text_field( (string) $data['note'] ) : '',
	);
}

/**
 * The rewrite request: the selected block's text fields + an instruction.
 *
 * @param array  $fields path => text.
 * @param string $prompt Instruction.
 * @return array|WP_Error { fields: path => text }
 */
function ap_builder_ai_rewrite( array $fields, $prompt ) {
	$fields = array_slice( array_filter( $fields, 'is_string' ), 0, 60, true );
	if ( ! $fields ) {
		return new WP_Error( 'ap_builder_ai_no_text', 'Select a block with text first.', array( 'status' => 422 ) );
	}
	$schema = array(
		'type'       => 'object',
		'properties' => array(
			'fields' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'path' => array(
							'type' => 'string',
							'enum' => array_map( 'strval', array_keys( $fields ) ),
						),
						'text' => array( 'type' => 'string' ),
					),
					'required'   => array( 'path', 'text' ),
				),
			),
		),
		'required'   => array( 'fields' ),
	);
	$system = ap_builder_ai_voice() . "\n\n"
		. 'Rewrite the text fields below as the editor asks. Return every field you change, with the same path; leave out fields you keep. '
		. 'Plain text only (no HTML, no Markdown). Reply with JSON only: {"fields":[{"path":"…","text":"…"}]}.'
		. "\n\nFIELDS:\n" . wp_json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$data   = ap_builder_ai_json( $system, '' !== trim( $prompt ) ? $prompt : 'Make it clearer and more concise.', $schema );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$out = array();
	foreach ( (array) ( isset( $data['fields'] ) ? $data['fields'] : array() ) as $field ) {
		if ( isset( $field['path'], $field['text'] ) && array_key_exists( (string) $field['path'], $fields ) && is_string( $field['text'] ) ) {
			$out[ (string) $field['path'] ] = sanitize_textarea_field( $field['text'] );
		}
	}
	return array( 'fields' => (object) $out );
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/ai/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => static function () {
					return rest_ensure_response(
						array(
							'status'     => ap_builder_ai_status(),
							'connectors' => admin_url( 'options-connectors.php' ),
						)
					);
				},
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/ai',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'mode'   => array(
						'type'     => 'string',
						'enum'     => array( 'build', 'rewrite' ),
						'required' => true,
					),
					'prompt' => array(
						'type'      => 'string',
						'default'   => '',
						'maxLength' => 2000,
					),
					'fields' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( 'ready' !== ap_builder_ai_status() ) {
						return new WP_Error( 'ap_builder_ai_off', 'No AI provider is connected. Set one up under Settings → Connectors.', array( 'status' => 503 ) );
					}
					$user  = get_current_user_id();
					$count = (int) get_transient( 'ap_builder_ai_' . $user );
					if ( $count >= 40 ) {
						return new WP_Error( 'ap_builder_ai_busy', 'That is 40 AI requests this hour. Try again later.', array( 'status' => 429 ) );
					}
					set_transient( 'ap_builder_ai_' . $user, $count + 1, HOUR_IN_SECONDS );
					$prompt = (string) $request['prompt'];
					$result = 'build' === $request['mode']
						? ap_builder_ai_build( $prompt )
						: ap_builder_ai_rewrite( (array) $request['fields'], $prompt );
					return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
				},
			)
		);
	}
);
