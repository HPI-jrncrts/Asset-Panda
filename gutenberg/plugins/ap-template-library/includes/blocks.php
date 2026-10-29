<?php
/**
 * Blocks: what the builder shows for each block of a reference, and how "Send to Draft"
 * rebuilds blocks from it.
 *
 * Kit blocks (assetpanda/*) — fields come from the block's registered attributes
 * (block.json, generated from the kit contract), help text from the kit's
 * component.json. The rebuilt block starts from the reference block and only its
 * registered attributes are replaced.
 *
 * Core blocks — a fixed set get simple editors:
 *   paragraph / heading  -> text (inline HTML)
 *   list (list-item kids) -> items
 *   image                -> image + alt text
 *   button               -> text + link
 *   embed                -> URL
 *   html / classic       -> HTML
 * Container blocks (group, columns, …) show their children; any other block is kept
 * exactly as it is (read-only in the builder, editable in the block editor afterwards).
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Deepest container nesting the builder opens. */
const AP_TL_MAX_NESTING = 6;

/**
 * Whether a block name is a kit block.
 *
 * @param mixed $name Block name.
 * @return bool
 */
function ap_tl_is_kit_block( $name ) {
	return is_string( $name ) && 0 === strpos( $name, 'assetpanda/' );
}

/**
 * A parsed block that is only the whitespace between blocks.
 *
 * @param array $block Parsed block.
 * @return bool
 */
function ap_tl_is_whitespace_block( array $block ) {
	return empty( $block['blockName'] ) && '' === trim( (string) ( $block['innerHTML'] ?? '' ) );
}

/**
 * Attributes never shown as fields; they are carried over from the reference untouched.
 *
 * @return string[]
 */
function ap_tl_hidden_attributes() {
	/**
	 * Filters the kit block attributes the builder does not show (kept from the reference).
	 *
	 * @param string[] $keys Attribute names.
	 */
	return (array) apply_filters( 'ap_tl_hidden_attributes', array( 'lock', 'metadata', 'className', 'style', 'anchor', 'align' ) );
}

/**
 * The empty value of a schema type (the effective default of an attribute without one).
 *
 * @param mixed $type Schema type.
 * @return mixed
 */
function ap_tl_empty_for_type( $type ) {
	switch ( $type ) {
		case 'string':
			return '';
		case 'boolean':
			return false;
		case 'number':
		case 'integer':
			return 0;
		case 'array':
		case 'object':
			return array();
	}
	return null;
}

/**
 * An attribute's default (or its type's empty value).
 *
 * @param array $schema Attribute schema.
 * @return mixed
 */
function ap_tl_attribute_default( array $schema ) {
	if ( array_key_exists( 'default', $schema ) ) {
		return $schema['default'];
	}
	return ap_tl_empty_for_type( $schema['type'] ?? null );
}

/**
 * The kit directory (components/ lives under it), when the kit is active.
 *
 * @return string
 */
function ap_tl_kit_dir() {
	if ( defined( 'AP_UI_DIR' ) && is_dir( AP_UI_DIR ) ) {
		return untrailingslashit( AP_UI_DIR );
	}
	return '';
}

/**
 * The kit contract (component.json) of a kit block, read directly from disk.
 * Missing or unreadable contracts give an empty array; this never reports to the kit.
 *
 * @param string $block_name assetpanda/<name>.
 * @return array
 */
function ap_tl_component_contract( $block_name ) {
	static $cache = array();
	if ( isset( $cache[ $block_name ] ) ) {
		return $cache[ $block_name ];
	}
	$cache[ $block_name ] = array();
	$slug                 = substr( (string) $block_name, strlen( 'assetpanda/' ) );
	$dir                  = ap_tl_kit_dir();
	if ( '' === $dir || ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		return $cache[ $block_name ];
	}
	$file = $dir . '/components/' . $slug . '/component.json';
	if ( is_readable( $file ) ) {
		$decoded = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $decoded ) && isset( $decoded['props'] ) && is_array( $decoded['props'] ) ) {
			$cache[ $block_name ] = $decoded;
		}
	}
	return $cache[ $block_name ];
}

/**
 * Field names seen in repeater rows across every registered kit block (name => sample type).
 * Used to read the row shape of an empty list from the contract's "Each: …" description.
 *
 * @return array<string, string>
 */
function ap_tl_row_vocabulary() {
	static $vocab = null;
	if ( null !== $vocab ) {
		return $vocab;
	}
	$vocab = array();
	$add   = static function ( $rows ) use ( &$vocab ) {
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ap_tl_is_list( $row ) ) {
				continue;
			}
			foreach ( $row as $key => $value ) {
				if ( ! is_string( $key ) || isset( $vocab[ $key ] ) ) {
					continue;
				}
				$vocab[ $key ] = is_bool( $value ) ? 'boolean' : ( is_int( $value ) || is_float( $value ) ? 'number' : ( is_array( $value ) ? 'array' : 'string' ) );
			}
		}
	};
	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		if ( ! ap_tl_is_kit_block( $name ) ) {
			continue;
		}
		foreach ( (array) $type->attributes as $schema ) {
			if ( isset( $schema['default'] ) ) {
				$add( $schema['default'] );
			}
		}
	}
	foreach ( array( 'title', 'body', 'label', 'text', 'image', 'alt', 'url', 'icon', 'icon_svg', 'link_label', 'link_url', 'question', 'answer', 'value', 'description', 'quote', 'author', 'role', 'name', 'logo', 'kicker', 'tone', 'category' ) as $common ) {
		if ( ! isset( $vocab[ $common ] ) ) {
			$vocab[ $common ] = 'string';
		}
	}
	return $vocab;
}

/**
 * Row shape of a list prop read from its contract description ("Each: title, body, url…").
 *
 * @param string $description Prop description.
 * @return array<int, array{key: string, type: string}>
 */
function ap_tl_shape_from_description( $description ) {
	if ( ! preg_match( '/\bEach(?:\s+(?:entry|item|row|card|slide))?\s*:\s*(.+)$/is', (string) $description, $m ) ) {
		return array();
	}
	$vocab = ap_tl_row_vocabulary();
	$stop  = array_flip( array( 'optional', 'required', 'and', 'or', 'the', 'a', 'an', 'with', 'each', 'entry', 'item', 'shown', 'instead', 'defaults', 'also', 'string', 'plain', 'works', 'px', 'desktop', 'phone', 'in', 'of', 'to', 'at', 'per', 'step', 'when', 'if', 'is', 'it', 'for', 'on', 'by', 'as', 'its', 'one', 'two', 'three' ) );
	preg_match_all( '/`?\b([a-z][a-z0-9]*(?:_[a-z0-9]+)*)\b`?/', $m[1], $tokens );
	$shape = array();
	foreach ( $tokens[1] as $token ) {
		if ( isset( $stop[ $token ] ) || isset( $shape[ $token ] ) || strlen( $token ) > 32 ) {
			continue;
		}
		if ( isset( $vocab[ $token ] ) ) {
			$shape[ $token ] = $vocab[ $token ];
		} elseif ( false !== strpos( $token, '_' ) ) {
			$shape[ $token ] = 'string';
		}
	}
	$out = array();
	foreach ( $shape as $key => $type ) {
		$out[] = array(
			'key'  => $key,
			'type' => $type,
		);
	}
	return array_slice( $out, 0, 24 );
}

/**
 * Infers a JSON-schema type from a value (for blocks whose type is not registered).
 *
 * @param mixed $value Value.
 * @return string
 */
function ap_tl_infer_type( $value ) {
	if ( is_bool( $value ) ) {
		return 'boolean';
	}
	if ( is_int( $value ) || is_float( $value ) ) {
		return 'number';
	}
	if ( is_array( $value ) ) {
		return ap_tl_is_list( $value ) && array() !== $value ? 'array' : ( array() === $value ? 'array' : 'object' );
	}
	return 'string';
}

/**
 * What the builder needs to draw a kit block type: title, description, field schemas, help.
 *
 * @param string $name      Block name.
 * @param array  $ref_attrs Saved attributes (used when the type is not registered).
 * @return array
 */
function ap_tl_kit_type_info( $name, array $ref_attrs = array() ) {
	$type     = WP_Block_Type_Registry::get_instance()->get_registered( $name );
	$hidden   = array_flip( ap_tl_hidden_attributes() );
	$contract = ap_tl_component_contract( $name );
	$props    = isset( $contract['props'] ) ? $contract['props'] : array();

	$title       = $type && $type->title ? $type->title : $name;
	$description = $type && $type->description ? $type->description : ( $contract['description'] ?? '' );
	$description = wp_strip_all_tags( (string) $description );
	if ( preg_match( '/^(.{20,220}?[.!?])(\s|$)/s', $description, $m ) ) {
		$description = $m[1];
	} elseif ( strlen( $description ) > 220 ) {
		$description = wp_html_excerpt( $description, 220, '…' );
	}

	$attributes = array();
	$help       = array();
	$shapes     = array();
	if ( $type ) {
		foreach ( (array) $type->attributes as $key => $schema ) {
			if ( isset( $hidden[ $key ] ) || ! is_array( $schema ) ) {
				continue;
			}
			$field = array( 'type' => $schema['type'] ?? null );
			if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
				$field['enum'] = array_values( $schema['enum'] );
			}
			$field['default'] = ap_tl_json_ready( ap_tl_attribute_default( $schema ), $field['type'] );
			$attributes[ $key ] = $field;
			if ( isset( $props[ $key ]['description'] ) ) {
				$help[ $key ] = wp_strip_all_tags( (string) $props[ $key ]['description'] );
				if ( 'array' === $field['type'] ) {
					$shape = ap_tl_shape_from_description( $help[ $key ] );
					if ( $shape ) {
						$shapes[ $key ] = $shape;
					}
				}
			}
		}
	} else {
		foreach ( $ref_attrs as $key => $value ) {
			if ( isset( $hidden[ $key ] ) ) {
				continue;
			}
			$attributes[ $key ] = array(
				'type'    => ap_tl_infer_type( $value ),
				'default' => null,
			);
		}
	}

	return array(
		'name'        => $name,
		'title'       => preg_replace( '/^AP\s+/', '', (string) $title ),
		'description' => $description,
		'registered'  => (bool) $type,
		'attributes'  => (object) $attributes,
		'help'        => (object) $help,
		'shapes'      => (object) $shapes,
	);
}

/**
 * Makes a value encode the way its schema type says: empty objects as {} rather than [].
 *
 * @param mixed $value Value.
 * @param mixed $type  Schema type.
 * @return mixed
 */
function ap_tl_json_ready( $value, $type ) {
	if ( 'object' === $type && is_array( $value ) && array() === $value ) {
		return new stdClass();
	}
	return $value;
}

/**
 * The editor (and its current values) for a core block, or null when it is kept as-is.
 *
 * @param array $block Parsed block.
 * @return array{0: string, 1: array}|null
 */
function ap_tl_core_editor_for( array $block ) {
	$name = $block['blockName'] ?? null;
	$html = (string) ( $block['innerHTML'] ?? '' );

	if ( empty( $name ) ) {
		return '' === trim( $html ) ? null : array( 'html', array( 'html' => $html ) );
	}
	if ( ap_tl_is_kit_block( $name ) ) {
		return null;
	}

	switch ( $name ) {
		case 'core/paragraph':
		case 'core/heading':
			if ( ! empty( $block['innerBlocks'] ) ) {
				return null;
			}
			$parts = ap_tl_split_wrapper( $html, 'core/heading' === $name ? 'h[1-6]' : 'p' );
			return $parts ? array( 'text', array( 'text' => $parts[1] ) ) : null;

		case 'core/list':
			if ( empty( $block['innerBlocks'] ) ) {
				return null;
			}
			$items = array();
			foreach ( $block['innerBlocks'] as $item ) {
				if ( 'core/list-item' !== ( $item['blockName'] ?? '' ) || ! empty( $item['innerBlocks'] ) ) {
					return null;
				}
				$parts = ap_tl_split_wrapper( (string) $item['innerHTML'], 'li' );
				if ( ! $parts ) {
					return null;
				}
				$items[] = $parts[1];
			}
			return array(
				'list',
				array(
					'items'   => $items,
					'ordered' => ! empty( $block['attrs']['ordered'] ),
				),
			);

		case 'core/image':
			if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
				return null;
			}
			$p = new WP_HTML_Tag_Processor( $html );
			if ( ! $p->next_tag( 'img' ) ) {
				return null;
			}
			return array(
				'image',
				array(
					'url' => (string) $p->get_attribute( 'src' ),
					'alt' => (string) $p->get_attribute( 'alt' ),
					'id'  => isset( $block['attrs']['id'] ) ? absint( $block['attrs']['id'] ) : 0,
				),
			);

		case 'core/button':
			if ( ! class_exists( 'WP_HTML_Tag_Processor' ) || ! preg_match( '#^(.*?<a\b[^>]*>)(.*?)(</a>.*)$#is', $html, $m ) ) {
				return null;
			}
			$p = new WP_HTML_Tag_Processor( $html );
			$p->next_tag( 'a' );
			return array(
				'button',
				array(
					'text' => $m[2],
					'url'  => (string) $p->get_attribute( 'href' ),
				),
			);

		case 'core/embed':
			if ( empty( $block['attrs']['url'] ) || ! is_string( $block['attrs']['url'] ) ) {
				return null;
			}
			return array( 'embed', array( 'url' => $block['attrs']['url'] ) );

		case 'core/html':
		case 'core/freeform':
			return array( 'html', array( 'html' => $html ) );
	}

	return null;
}

/**
 * Splits "<tag …>inner</tag>" into its opening tag, inner HTML and closing tag.
 *
 * @param string $html      Block HTML.
 * @param string $tag_regex Tag name pattern (e.g. "p", "h[1-6]", "li").
 * @return array{0: string, 1: string, 2: string}|null
 */
function ap_tl_split_wrapper( $html, $tag_regex ) {
	if ( ! preg_match( '#^(\s*<(' . $tag_regex . ')\b[^>]*>)(.*)(</\2>\s*)$#is', (string) $html, $m ) ) {
		return null;
	}
	// Two sibling elements in one block would make the "inner" span both; keep those read-only.
	if ( preg_match( '#</' . preg_quote( $m[2], '#' ) . '\s*>#i', $m[3] ) ) {
		return null;
	}
	return array( $m[1], $m[3], $m[4] );
}

/**
 * A human title for a block.
 *
 * @param array $block Parsed block.
 * @return string
 */
function ap_tl_block_title( array $block ) {
	$name = $block['blockName'] ?? null;
	if ( empty( $name ) ) {
		return __( 'Classic content', 'ap-template-library' );
	}
	$type  = WP_Block_Type_Registry::get_instance()->get_registered( $name );
	$title = $type && $type->title ? $type->title : $name;
	if ( 'core/heading' === $name ) {
		$level  = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;
		$title .= ' (H' . $level . ')';
	}
	return preg_replace( '/^AP\s+/', '', (string) $title );
}

/**
 * A short plain-text preview of a block (for blocks kept as they are).
 *
 * @param array $block Parsed block.
 * @return string
 */
function ap_tl_block_preview( array $block ) {
	$html = serialize_block( $block );
	$html = preg_replace( '/<!--.*?-->/s', ' ', $html );
	$html = preg_replace( '#<(script|style)\b.*?</\1>#is', ' ', (string) $html );
	// Tags become spaces so table cells and list items do not run together.
	$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( (string) preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
	return wp_html_excerpt( $text, 240, '…' );
}

/**
 * Describes a core block (and its children) for the builder.
 *
 * @param array  $block Parsed block.
 * @param string $path  Path of inner-block indexes below the box ("" = the box's own block, "0/2" …).
 * @param int    $depth Nesting depth.
 * @return array
 */
function ap_tl_describe_core( array $block, $path, $depth ) {
	$name   = ! empty( $block['blockName'] ) ? $block['blockName'] : 'core/freeform';
	$editor = ap_tl_core_editor_for( $block );
	$node   = array(
		'path'     => (string) $path,
		'name'     => $name,
		'title'    => ap_tl_block_title( $block ),
		'kit'      => ap_tl_is_kit_block( $name ),
		'editor'   => $editor ? $editor[0] : null,
		'fields'   => $editor ? $editor[1] : new stdClass(),
		'preview'  => '',
		'children' => array(),
	);
	if ( ! $editor && ! empty( $block['innerBlocks'] ) && $depth < AP_TL_MAX_NESTING && ! $node['kit'] ) {
		foreach ( $block['innerBlocks'] as $i => $inner ) {
			if ( ap_tl_is_whitespace_block( $inner ) ) {
				continue;
			}
			$node['children'][] = ap_tl_describe_core( $inner, '' === $path ? (string) $i : $path . '/' . $i, $depth + 1 );
		}
	}
	if ( ! $editor && ! $node['children'] ) {
		$node['preview'] = ap_tl_block_preview( $block );
	}
	return $node;
}

/**
 * The builder's view of a reference post: one box per top-level block, plus kit type info.
 *
 * @param WP_Post $post Reference post.
 * @return array{blocks: array, types: array, hash: string}
 */
function ap_tl_builder_blocks( WP_Post $post ) {
	$parsed = parse_blocks( (string) $post->post_content );
	$boxes  = array();
	$types  = array();

	foreach ( $parsed as $index => $block ) {
		if ( ap_tl_is_whitespace_block( $block ) ) {
			continue;
		}
		$name = $block['blockName'];
		if ( ap_tl_is_kit_block( $name ) ) {
			$saved = is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if ( ! isset( $types[ $name ] ) ) {
				$types[ $name ] = ap_tl_kit_type_info( $name, $saved );
			}
			$attrs = array();
			foreach ( (array) $types[ $name ]['attributes'] as $key => $field ) {
				$value         = array_key_exists( $key, $saved ) ? $saved[ $key ] : ( is_object( $field['default'] ) ? array() : $field['default'] );
				$attrs[ $key ] = ap_tl_json_ready( $value, $field['type'] );
			}
			$boxes[] = array(
				'ref'   => (int) $index,
				'kind'  => 'kit',
				'name'  => $name,
				'title' => $types[ $name ]['title'],
				'attrs' => (object) $attrs,
				'inner' => ! empty( $block['innerBlocks'] ),
			);
		} else {
			$node    = ap_tl_describe_core( $block, '', 0 );
			$boxes[] = array(
				'ref'   => (int) $index,
				'kind'  => 'core',
				'name'  => $node['name'],
				'title' => $node['title'],
				'node'  => $node,
			);
		}
	}

	return array(
		'blocks' => $boxes,
		'types'  => (object) $types,
		'hash'   => md5( (string) $post->post_content ),
	);
}

/**
 * Short section list of a reference, for the library card placeholder.
 *
 * @param WP_Post $post Reference post.
 * @return string[] Section titles.
 */
function ap_tl_section_titles( WP_Post $post ) {
	$titles = array();
	foreach ( parse_blocks( (string) $post->post_content ) as $block ) {
		if ( ap_tl_is_whitespace_block( $block ) ) {
			continue;
		}
		$title = ap_tl_block_title( $block );
		// Collapse runs of the same core block (a blog post is mostly Paragraph, Heading, Paragraph…).
		if ( ! ap_tl_is_kit_block( $block['blockName'] ) && $titles && end( $titles ) === $title ) {
			continue;
		}
		$titles[] = $title;
	}
	return $titles;
}

/**
 * Applies one validated edit to a core block. The editor is chosen from the block itself,
 * never from the request.
 *
 * @param array $block  Parsed block.
 * @param mixed $fields Submitted fields for this block.
 * @return array The block, changed or not.
 */
function ap_tl_apply_core_edit( array $block, $fields ) {
	$editor = ap_tl_core_editor_for( $block );
	if ( ! $editor || ! is_array( $fields ) ) {
		return $block;
	}
	list( $kind, $orig ) = $editor;

	switch ( $kind ) {
		case 'text':
			if ( ! isset( $fields['text'] ) || ! is_string( $fields['text'] ) || $fields['text'] === $orig['text'] ) {
				return $block;
			}
			$parts = ap_tl_split_wrapper( (string) $block['innerHTML'], 'core/heading' === $block['blockName'] ? 'h[1-6]' : 'p' );
			$html  = $parts[0] . ap_tl_sanitize_rich( $fields['text'] ) . $parts[2];
			return ap_tl_leaf_html( $block, $html );

		case 'list':
			if ( ! isset( $fields['items'] ) || ! is_array( $fields['items'] ) ) {
				return $block;
			}
			$items = array();
			foreach ( array_slice( array_values( $fields['items'] ), 0, AP_TL_MAX_ITEMS ) as $item ) {
				if ( is_string( $item ) ) {
					$items[] = $item;
				}
			}
			if ( $items === $orig['items'] ) {
				return $block;
			}
			$template = $block['innerBlocks'][0];
			$parts    = ap_tl_split_wrapper( (string) $template['innerHTML'], 'li' );
			$chunks   = (array) $block['innerContent'];
			$first    = array_search( null, $chunks, true );
			$last     = false;
			foreach ( $chunks as $i => $chunk ) {
				if ( null === $chunk ) {
					$last = $i;
				}
			}
			$opener = implode( '', array_slice( $chunks, 0, (int) $first ) );
			$closer = implode( '', array_slice( $chunks, (int) $last + 1 ) );
			$inner  = array();
			foreach ( $items as $item ) {
				$html    = $parts[0] . ap_tl_sanitize_rich( $item ) . $parts[2];
				$inner[] = array(
					'blockName'    => 'core/list-item',
					'attrs'        => is_array( $template['attrs'] ) ? $template['attrs'] : array(),
					'innerBlocks'  => array(),
					'innerHTML'    => $html,
					'innerContent' => array( $html ),
				);
			}
			$block['innerBlocks']  = $inner;
			$block['innerContent'] = array_merge( array( $opener ), array_fill( 0, count( $inner ), null ), array( $closer ) );
			$block['innerHTML']    = $opener . $closer;
			return $block;

		case 'image':
			$url = isset( $fields['url'] ) && is_string( $fields['url'] ) ? $fields['url'] : $orig['url'];
			$alt = isset( $fields['alt'] ) && is_string( $fields['alt'] ) ? $fields['alt'] : $orig['alt'];
			$id  = isset( $fields['id'] ) ? absint( $fields['id'] ) : $orig['id'];
			if ( $url === $orig['url'] && $alt === $orig['alt'] && $id === $orig['id'] ) {
				return $block;
			}
			if ( $id && ! wp_attachment_is_image( $id ) ) {
				$id = 0;
			}
			$url = '' !== trim( $url ) ? esc_url_raw( $url ) : ( $id ? (string) wp_get_attachment_url( $id ) : '' );
			$p   = new WP_HTML_Tag_Processor( (string) $block['innerHTML'] );
			if ( ! $p->next_tag( 'img' ) ) {
				return $block;
			}
			$p->set_attribute( 'src', $url );
			$p->set_attribute( 'alt', sanitize_text_field( $alt ) );
			foreach ( preg_split( '/\s+/', (string) $p->get_attribute( 'class' ) ) as $class ) {
				if ( preg_match( '/^wp-image-\d+$/', $class ) ) {
					$p->remove_class( $class );
				}
			}
			if ( $id ) {
				$p->add_class( 'wp-image-' . $id );
				$block['attrs']['id'] = $id;
			} else {
				unset( $block['attrs']['id'] );
			}
			return ap_tl_leaf_html( $block, $p->get_updated_html() );

		case 'button':
			$text = isset( $fields['text'] ) && is_string( $fields['text'] ) ? $fields['text'] : $orig['text'];
			$url  = isset( $fields['url'] ) && is_string( $fields['url'] ) ? $fields['url'] : $orig['url'];
			if ( $text === $orig['text'] && $url === $orig['url'] ) {
				return $block;
			}
			$html = (string) $block['innerHTML'];
			if ( $text !== $orig['text'] && preg_match( '#^(.*?<a\b[^>]*>)(.*?)(</a>.*)$#is', $html, $m ) ) {
				$html = $m[1] . ap_tl_sanitize_rich( $text ) . $m[3];
			}
			if ( $url !== $orig['url'] ) {
				$p = new WP_HTML_Tag_Processor( $html );
				if ( $p->next_tag( 'a' ) ) {
					$clean = esc_url_raw( $url );
					if ( '' === $clean ) {
						$p->remove_attribute( 'href' );
					} else {
						$p->set_attribute( 'href', $clean );
					}
					$html = $p->get_updated_html();
				}
			}
			return ap_tl_leaf_html( $block, $html );

		case 'embed':
			if ( ! isset( $fields['url'] ) || ! is_string( $fields['url'] ) || $fields['url'] === $orig['url'] ) {
				return $block;
			}
			$clean = esc_url_raw( $fields['url'] );
			if ( '' === $clean ) {
				return $block;
			}
			$block['attrs']['url'] = $clean;
			return ap_tl_leaf_html( $block, str_replace( $orig['url'], $clean, (string) $block['innerHTML'] ) );

		case 'html':
			if ( ! isset( $fields['html'] ) || ! is_string( $fields['html'] ) || $fields['html'] === $orig['html'] ) {
				return $block;
			}
			return ap_tl_leaf_html( $block, ap_tl_sanitize_html_block( $fields['html'] ) );
	}

	return $block;
}

/**
 * Replaces a leaf block's HTML (blocks without inner blocks).
 *
 * @param array  $block Parsed block.
 * @param string $html  New HTML.
 * @return array
 */
function ap_tl_leaf_html( array $block, $html ) {
	$block['innerHTML']    = (string) $html;
	$block['innerContent'] = array( (string) $html );
	return $block;
}

/**
 * Applies a box's edits ("path" => fields) to a core block.
 *
 * @param array $block Parsed top-level block.
 * @param mixed $edits Submitted edits.
 * @return array
 */
function ap_tl_apply_core_edits( array $block, $edits ) {
	if ( ! is_array( $edits ) ) {
		return $block;
	}
	$count = 0;
	foreach ( $edits as $path => $fields ) {
		if ( ++$count > 1000 ) {
			break;
		}
		$path = (string) $path;
		if ( '' !== $path && ! preg_match( '#^\d{1,4}(/\d{1,4}){0,' . ( AP_TL_MAX_NESTING - 1 ) . '}$#', $path ) ) {
			continue;
		}
		$block = ap_tl_apply_at_path( $block, '' === $path ? array() : array_map( 'intval', explode( '/', $path ) ), $fields );
	}
	return $block;
}

/**
 * Walks down inner blocks and applies an edit at the end of the path.
 *
 * @param array $block    Parsed block.
 * @param int[] $segments Remaining path.
 * @param mixed $fields   Submitted fields.
 * @return array
 */
function ap_tl_apply_at_path( array $block, array $segments, $fields ) {
	if ( ! $segments ) {
		return ap_tl_apply_core_edit( $block, $fields );
	}
	if ( ap_tl_is_kit_block( $block['blockName'] ?? null ) ) {
		return $block;
	}
	$i = array_shift( $segments );
	if ( ! isset( $block['innerBlocks'][ $i ] ) || ! is_array( $block['innerBlocks'][ $i ] ) ) {
		return $block;
	}
	$block['innerBlocks'][ $i ] = ap_tl_apply_at_path( $block['innerBlocks'][ $i ], $segments, $fields );
	return $block;
}

/**
 * Rebuilds a kit block from its reference block and the submitted attributes.
 *
 * @param array $ref_block Reference block.
 * @param mixed $submitted Submitted attributes.
 * @param array $known     Strings already stored in the reference (md5 => true).
 * @return array
 */
function ap_tl_build_kit_block( array $ref_block, $submitted, array $known ) {
	$name      = $ref_block['blockName'];
	$type      = WP_Block_Type_Registry::get_instance()->get_registered( $name );
	$schema    = $type ? (array) $type->attributes : null;
	$ref_attrs = is_array( $ref_block['attrs'] ) ? $ref_block['attrs'] : array();
	$hidden    = array_flip( ap_tl_hidden_attributes() );
	$attrs     = $ref_attrs;

	foreach ( is_array( $submitted ) ? $submitted : array() as $key => $value ) {
		if ( ! is_string( $key ) || isset( $hidden[ $key ] ) ) {
			continue;
		}
		if ( null !== $schema ? ! isset( $schema[ $key ] ) : ! array_key_exists( $key, $ref_attrs ) ) {
			continue;
		}
		$clean = ap_tl_sanitize_value( $value, $key, $known );
		if ( null !== $schema ) {
			$default  = ap_tl_attribute_default( (array) $schema[ $key ] );
			$fallback = array_key_exists( $key, $ref_attrs ) ? $ref_attrs[ $key ] : $default;
			$clean    = ap_tl_coerce( $clean, (array) $schema[ $key ], $fallback );
			// Keep the markup lean, as the block editor does: an attribute the reference did not
			// store and that still equals its default stays out.
			if ( ! array_key_exists( $key, $ref_attrs ) && ap_tl_same_value( $clean, $default ) ) {
				unset( $attrs[ $key ] );
				continue;
			}
			$clean = ap_tl_json_ready( $clean, $schema[ $key ]['type'] ?? null );
		}
		$attrs[ $key ] = $clean;
	}

	$block          = $ref_block;
	$block['attrs'] = $attrs;
	return $block;
}

/**
 * Builds the new post's content from the reference and the builder's boxes.
 *
 * @param WP_Post $reference Reference post.
 * @param mixed   $boxes     Submitted boxes: [ {ref, name?, attrs?} | {ref, edits?} ].
 * @return string|WP_Error Serialized block markup.
 */
function ap_tl_build_content( WP_Post $reference, $boxes ) {
	if ( ! is_array( $boxes ) || ! $boxes ) {
		return new WP_Error( 'ap_tl_no_sections', __( 'Keep at least one section before sending to draft.', 'ap-template-library' ), array( 'status' => 400 ) );
	}
	if ( count( $boxes ) > 400 ) {
		return new WP_Error( 'ap_tl_too_many', __( 'Too many sections.', 'ap-template-library' ), array( 'status' => 400 ) );
	}
	$ref_blocks = parse_blocks( (string) $reference->post_content );
	$known      = ap_tl_known_strings( $ref_blocks );
	$out        = array();

	foreach ( array_values( $boxes ) as $n => $box ) {
		if ( ! is_array( $box ) || ! isset( $box['ref'] ) || ! is_numeric( $box['ref'] ) ) {
			return new WP_Error( 'ap_tl_bad_section', __( 'A section could not be read. Reload the builder and try again.', 'ap-template-library' ), array( 'status' => 400 ) );
		}
		$ref = (int) $box['ref'];
		if ( ! isset( $ref_blocks[ $ref ] ) || ap_tl_is_whitespace_block( $ref_blocks[ $ref ] ) ) {
			return new WP_Error( 'ap_tl_reference_changed', __( 'The reference page changed after this builder was opened. Reload the builder (your edits can be saved with "Save progress" first).', 'ap-template-library' ), array( 'status' => 409 ) );
		}
		$ref_block = $ref_blocks[ $ref ];
		if ( ap_tl_is_kit_block( $ref_block['blockName'] ) ) {
			if ( isset( $box['name'] ) && $box['name'] !== $ref_block['blockName'] ) {
				return new WP_Error( 'ap_tl_reference_changed', __( 'The reference page changed after this builder was opened. Reload the builder (your edits can be saved with "Save progress" first).', 'ap-template-library' ), array( 'status' => 409 ) );
			}
			$out[] = ap_tl_build_kit_block( $ref_block, $box['attrs'] ?? array(), $known );
		} else {
			$out[] = ap_tl_apply_core_edits( $ref_block, $box['edits'] ?? array() );
		}
	}

	return implode( "\n\n", array_map( 'serialize_block', $out ) );
}
