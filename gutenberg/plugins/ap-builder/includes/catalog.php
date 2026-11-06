<?php
/**
 * The library's catalog (REST, editor users only):
 *
 *   GET ap-builder/v1/sections         every section design used on the site: one entry per kit block and
 *                                      variant (its look options), taken from published pages and resources
 *   GET ap-builder/v1/sections/<key>   that section's block markup, with the real page's content
 *   GET ap-builder/v1/layouts          whole-page templates from the AP Template Library
 *   GET ap-builder/v1/layouts/<key>    that template's blocks
 *   GET ap-builder/v1/art              the kit's own art: pandas, orbs and glows, icons, badges, illustrations
 *
 * Sections are found by reading the site, not listed by hand: a section built on a new page joins the
 * library on the next rebuild (cached 12 hours; any page or resource save rebuilds it).
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_BUILDER_SECTIONS_CACHE = 'ap_builder_sections_v1';

/** Sections not offered: whole-site chrome (edited in its template part). */
function ap_builder_skip_blocks() {
	return array( 'assetpanda/site-header', 'assetpanda/site-footer', 'assetpanda/container', 'assetpanda/visual' );
}

/**
 * The library group a kit block belongs to.
 *
 * @param string $name Block name.
 * @return string
 */
function ap_builder_section_group( $name ) {
	$n = substr( $name, strlen( 'assetpanda/' ) );
	$groups = array(
		'heroes'     => '/hero|splash|titlebar/',
		'cta'        => '/cta|booking|demo-|form|thanks|confirm|calendly|message/',
		'proof'      => '/stats|award|trust|case|stor|review|testimonial|founder|quote|logos|reach|mission|values|timeline/',
		'compare'    => '/compar|plans|pricing|roi|calculator/',
		'faq'        => '/faq/',
		'media'      => '/media|video|dashboard|scene|photo|showcase|band/',
		'features'   => '/card|rows|feature|capabilit|platform|value|why|sticky|tab|problem|continuity|industr|intel|readiness|assist|link|directory|integration|next|split|toc|prose|resource/',
	);
	foreach ( $groups as $group => $pattern ) {
		if ( preg_match( $pattern, $n ) ) {
			return $group;
		}
	}
	return 'other';
}

/** A short readable name for a block type ("AP Stats" → "Stats"). */
function ap_builder_block_title( $type, $name ) {
	$title = $type && ! empty( $type->title ) ? (string) $type->title : $name;
	return trim( preg_replace( '/^AP\s+/', '', $title ) );
}

/** The first heading-like text of a block's attributes, for the card and for search. */
function ap_builder_block_heading( array $attrs ) {
	foreach ( array( 'title', 'heading', 'eyebrow', 'title_accent', 'lede', 'statement', 'quote' ) as $key ) {
		if ( ! empty( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
			return wp_html_excerpt( wp_strip_all_tags( $attrs[ $key ] ), 90, '…' );
		}
	}
	return '';
}

/**
 * Every section design used on the site (cached).
 *
 * @return array[] { key, name, title, group, variant, heading, source, source_id, index, uses }
 */
function ap_builder_sections() {
	$cached = get_transient( AP_BUILDER_SECTIONS_CACHE );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$registry = WP_Block_Type_Registry::get_instance();
	$front    = (int) get_option( 'page_on_front' );
	$posts    = get_posts(
		array(
			'post_type'        => array( 'page', 'resource' ),
			'post_status'      => 'publish',
			'numberposts'      => 500,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);
	// The homepage first: its sections are the reference versions.
	usort(
		$posts,
		static function ( $a, $b ) use ( $front ) {
			return ( (int) ( $b->ID === $front ) ) - ( (int) ( $a->ID === $front ) );
		}
	);

	$items = array();
	foreach ( $posts as $post ) {
		if ( false === strpos( (string) $post->post_content, '<!-- wp:assetpanda/' ) ) {
			continue;
		}
		foreach ( parse_blocks( $post->post_content ) as $index => $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( 0 !== strpos( $name, 'assetpanda/' ) || in_array( $name, ap_builder_skip_blocks(), true ) ) {
				continue;
			}
			$type    = $registry->get_registered( $name );
			$attrs   = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$variant = array();
			if ( $type ) {
				foreach ( (array) $type->attributes as $key => $schema ) {
					if ( 'align' === $key || empty( $schema['enum'] ) ) {
						continue;
					}
					$value = array_key_exists( $key, $attrs ) ? $attrs[ $key ] : ( isset( $schema['default'] ) ? $schema['default'] : '' );
					if ( ! isset( $schema['default'] ) || $value !== $schema['default'] ) {
						$variant[ $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
					}
				}
			}
			ksort( $variant );
			$key = substr( md5( $name . '|' . wp_json_encode( $variant ) ), 0, 12 );
			if ( isset( $items[ $key ] ) ) {
				++$items[ $key ]['uses'];
				continue;
			}
			$items[ $key ] = array(
				'key'       => $key,
				'name'      => $name,
				'title'     => ap_builder_block_title( $type, $name ),
				'group'     => ap_builder_section_group( $name ),
				'variant'   => implode(
					', ',
					array_map(
						static function ( $k, $v ) {
							return str_replace( '_', ' ', $k ) . ': ' . $v;
						},
						array_keys( $variant ),
						$variant
					)
				),
				'heading'   => ap_builder_block_heading( $attrs ),
				'source'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'source_id' => (int) $post->ID,
				'index'     => (int) $index,
				'uses'      => 1,
			);
		}
	}
	$items = array_values( $items );
	set_transient( AP_BUILDER_SECTIONS_CACHE, $items, 12 * HOUR_IN_SECONDS );
	return $items;
}

/** Rebuild the sections on the next request after a page or resource changes. */
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'page', 'resource' ), true ) && ! wp_is_post_revision( $post_id ) ) {
			delete_transient( AP_BUILDER_SECTIONS_CACHE );
		}
	},
	10,
	2
);

/**
 * One section's block, as it is on its page.
 *
 * @param string $key Section key.
 * @return array|null Parsed block.
 */
function ap_builder_section_block( $key ) {
	static $parsed = array(); // Source page id => its parsed blocks, so a page is parsed once per request.
	foreach ( ap_builder_sections() as $item ) {
		if ( $item['key'] !== $key ) {
			continue;
		}
		$post = get_post( $item['source_id'] );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		if ( ! isset( $parsed[ $post->ID ] ) ) {
			$parsed[ $post->ID ] = parse_blocks( $post->post_content );
		}
		$blocks = $parsed[ $post->ID ];
		$block  = isset( $blocks[ $item['index'] ] ) ? $blocks[ $item['index'] ] : null;
		return $block && $block['blockName'] === $item['name'] ? $block : null;
	}
	return null;
}

/**
 * Whole-page templates from the AP Template Library.
 *
 * @return array[]|null Null when the Template Library is not active.
 */
function ap_builder_layouts() {
	if ( ! function_exists( 'ap_tl_templates' ) || ! function_exists( 'ap_tl_template_context' ) ) {
		return null;
	}
	$groups = function_exists( 'ap_tl_groups' ) ? ap_tl_groups() : array();
	$out    = array();
	foreach ( ap_tl_templates() as $key => $template ) {
		try {
			$context = ap_tl_template_context( $template );
		} catch ( \Throwable $e ) {
			continue;
		}
		if ( 'ok' !== $context['status'] || ! $context['post'] ) {
			continue;
		}
		$thumb = function_exists( 'ap_tl_thumbnail_url' ) ? (string) ap_tl_thumbnail_url( $template, $context['post'] ) : '';
		$out[] = array(
			'key'         => (string) $key,
			'label'       => (string) $template['label'],
			'group'       => isset( $groups[ $template['group'] ] ) ? (string) $groups[ $template['group'] ] : (string) $template['group'],
			'description' => (string) $template['description'],
			'post_type'   => (string) $context['post_type'],
			'source'      => html_entity_decode( get_the_title( $context['post'] ), ENT_QUOTES, 'UTF-8' ),
			'thumb'       => $thumb,
		);
	}
	return $out;
}

/**
 * The kit's own art, grouped.
 *
 * @return array[] { url, name, group }
 */
function ap_builder_art() {
	if ( ! defined( 'AP_UI_DIR' ) || ! function_exists( 'ap_ui_asset_url' ) ) {
		return array();
	}
	// No GLOB_BRACE: it is missing on some hosts' C libraries.
	$files = array_filter(
		(array) glob( AP_UI_DIR . '/components/*/assets/*' ),
		static function ( $file ) {
			return (bool) preg_match( '/\.(svg|png|webp|jpe?g|gif)$/i', $file );
		}
	);
	$out   = array();
	$seen  = array();
	foreach ( (array) $files as $file ) {
		$component = basename( dirname( dirname( $file ) ) );
		$base      = basename( $file );
		$hash      = md5_file( $file );
		if ( isset( $seen[ $hash ] ) ) {
			continue; // The same drawing shipped by two components.
		}
		$seen[ $hash ] = true;
		$name          = strtolower( $base );
		if ( preg_match( '/panda/', $name ) ) {
			$group = 'pandas';
		} elseif ( preg_match( '/orb|glow|blob|wash|circle|gradient/', $name ) ) {
			$group = 'orbs';
		} elseif ( preg_match( '/logo|badge|award|soc|hipaa|gov|capterra|g2|gartner|store|app-/', $name ) ) {
			$group = 'badges';
		} elseif ( preg_match( '/icn|icon|ico\b|ico-|arrow|check|chev|plus|minus|close|play|quote|star|dot/', $name ) || 'site-header' === $component ) {
			$group = 'icons';
		} else {
			$group = 'illustrations';
		}
		$label = ucfirst( trim( preg_replace( '/[-_]+/', ' ', preg_replace( '/\.[a-z0-9]+$/i', '', $base ) ) ) );
		$out[] = array(
			'url'   => ap_ui_asset_url( $component, $base ),
			'name'  => preg_replace( '/\s+(icn|icon)$/i', '', $label ),
			'group' => $group,
		);
	}
	return $out;
}

add_action(
	'rest_api_init',
	static function () {
		$can = static function () {
			return current_user_can( 'edit_posts' );
		};
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/sections',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function () {
					return rest_ensure_response( ap_builder_sections() );
				},
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/sections/(?P<key>[a-f0-9]{12})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $request ) {
					$block = ap_builder_section_block( (string) $request['key'] );
					if ( ! $block ) {
						return new WP_Error( 'ap_builder_missing', 'That section is no longer on its page.', array( 'status' => 404 ) );
					}
					return rest_ensure_response( array( 'markup' => serialize_block( $block ) ) );
				},
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/layouts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function () {
					$layouts = ap_builder_layouts();
					return rest_ensure_response( null === $layouts ? array( 'unavailable' => true, 'items' => array() ) : array( 'items' => $layouts ) );
				},
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/layouts/(?P<key>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $request ) {
					if ( ! function_exists( 'ap_tl_get_template' ) ) {
						return new WP_Error( 'ap_builder_no_library', 'The AP Template Library is not active.', array( 'status' => 404 ) );
					}
					$template = ap_tl_get_template( (string) $request['key'] );
					$context  = $template ? ap_tl_template_context( $template ) : null;
					if ( ! $context || ! $context['post'] ) {
						return new WP_Error( 'ap_builder_missing', 'That template has no reference page.', array( 'status' => 404 ) );
					}
					return rest_ensure_response( array( 'markup' => (string) $context['post']->post_content ) );
				},
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/art',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => $can,
				'callback'            => static function () {
					return rest_ensure_response( ap_builder_art() );
				},
			)
		);
	}
);
