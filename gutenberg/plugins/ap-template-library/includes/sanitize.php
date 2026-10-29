<?php
/**
 * Sanitising builder input.
 *
 * Every value the builder sends is untrusted. Strings are sanitised by what they are:
 * - link and image fields (keys like url, href, image, photo, icon, logo… holding a URL or
 *   path) go through esc_url_raw();
 * - strings that carry markup (inline SVG icons, decorative mockup fragments) go through
 *   wp_kses() with the post allowlist plus an SVG allowlist (the kit's own when available);
 * - every other string goes through sanitize_textarea_field(). This is sanitize_text_field()
 *   but keeps line breaks, which the kit uses in titles.
 *
 * A string identical to a value already stored in the reference page's kit blocks is
 * kept exactly. It is content an editor already saved, and re-filtering it would damage
 * markup props the user never touched. The kit escapes every prop again on output.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Longest string accepted for one field. */
const AP_TL_MAX_STRING = 200000;
/** Most entries accepted in one list. */
const AP_TL_MAX_ITEMS = 500;
/** Deepest nesting accepted in one attribute value. */
const AP_TL_MAX_DEPTH = 10;

/**
 * Whether an array is a list (0..n-1 keys). PHP 7.4-safe.
 *
 * @param array $value Array.
 * @return bool
 */
function ap_tl_is_list( array $value ) {
	return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Field keys that hold a link.
 *
 * @param string $key Field key.
 * @return bool
 */
function ap_tl_is_url_key( $key ) {
	return (bool) preg_match( '/(^|_)(url|href|link|src)$/i', (string) $key );
}

/**
 * Field keys that usually hold an image (or other media) URL.
 *
 * @param string $key Field key.
 * @return bool
 */
function ap_tl_is_image_key( $key ) {
	$key = (string) $key;
	if ( preg_match( '/(^|_)(alt|w|h|width|height|svg|text|style|label|title|tone|fit|position|size|class|cls|zoom|mode|color|colour)$/i', $key ) ) {
		return false;
	}
	return (bool) preg_match( '/(^|_)(image|img|photo|poster|icon|logo|background|bg|avatar|thumbnail|thumb|picture|illustration|screenshot|dashboard|person|cutout|video|mobile_cards)(_|$)/i', $key );
}

/**
 * Whether a string looks like a URL or a site path (not a bare word such as "check").
 *
 * @param string $value Value.
 * @return bool
 */
function ap_tl_looks_like_url( $value ) {
	return (bool) preg_match( '#^((https?:)?//|/|\#|\?|mailto:|tel:)#i', trim( (string) $value ) );
}

/**
 * The SVG allowlist: the kit's when the kit is active, else the same vocabulary.
 *
 * @return array
 */
function ap_tl_svg_allowlist() {
	if ( function_exists( 'ap_ui_svg_allowlist' ) ) {
		return ap_ui_svg_allowlist();
	}
	static $allowed = null;
	if ( null === $allowed ) {
		$attrs   = array_fill_keys(
			array( 'id', 'class', 'viewbox', 'width', 'height', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-dasharray', 'opacity', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points', 'transform', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'clip-path', 'clip-rule', 'mask', 'preserveaspectratio', 'xmlns', 'aria-hidden', 'focusable', 'overflow' ),
			true
		);
		$allowed = array_fill_keys(
			array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'defs', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'title' ),
			$attrs
		);
	}
	return $allowed;
}

/**
 * Sanitises one string attribute value.
 *
 * @param string $value Raw value.
 * @param string $key   Field key (drives the choice of sanitiser).
 * @return string
 */
function ap_tl_sanitize_string( $value, $key ) {
	$value = wp_check_invalid_utf8( (string) $value );
	if ( strlen( $value ) > AP_TL_MAX_STRING ) {
		$value = substr( $value, 0, AP_TL_MAX_STRING );
	}
	if ( '' === trim( $value ) ) {
		return '';
	}

	// Link and image fields: anything that is a URL, or names a scheme (javascript:, data:…), goes
	// through esc_url_raw(), which drops disallowed protocols. Bare words ("check") stay text.
	if ( ( ap_tl_is_url_key( $key ) || ap_tl_is_image_key( $key ) ) && ( ap_tl_looks_like_url( $value ) || preg_match( '/^\s*[a-z][a-z0-9+.\-]*\s*:/i', $value ) ) ) {
		return esc_url_raw( trim( $value ) );
	}

	if ( false !== strpos( $value, '<' ) ) {
		$trimmed = trim( $value );
		if ( 0 === stripos( $trimmed, '<svg' ) ) {
			return wp_kses( $trimmed, ap_tl_svg_allowlist() );
		}
		return wp_kses( $value, array_merge( wp_kses_allowed_html( 'post' ), ap_tl_svg_allowlist() ) );
	}

	return sanitize_textarea_field( $value );
}

/**
 * Sanitises any attribute value, recursively.
 *
 * @param mixed  $value Raw value (decoded JSON).
 * @param string $key   The key this value sits under (list entries inherit their list's key).
 * @param array  $known md5 => true of strings already stored in the reference page.
 * @param int    $depth Current depth.
 * @return mixed
 */
function ap_tl_sanitize_value( $value, $key, array $known, $depth = 0 ) {
	if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
		return $value;
	}
	if ( is_float( $value ) ) {
		return is_finite( $value ) ? $value : 0;
	}
	if ( is_string( $value ) ) {
		if ( isset( $known[ md5( $value ) ] ) ) {
			return $value;
		}
		return ap_tl_sanitize_string( $value, $key );
	}
	if ( ! is_array( $value ) || $depth >= AP_TL_MAX_DEPTH ) {
		return null;
	}

	$out = array();
	if ( ap_tl_is_list( $value ) ) {
		foreach ( array_slice( $value, 0, AP_TL_MAX_ITEMS ) as $item ) {
			$out[] = ap_tl_sanitize_value( $item, $key, $known, $depth + 1 );
		}
		return $out;
	}
	foreach ( $value as $child_key => $child ) {
		$child_key = (string) $child_key;
		if ( ! preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $child_key ) ) {
			continue;
		}
		$out[ $child_key ] = ap_tl_sanitize_value( $child, $child_key, $known, $depth + 1 );
		if ( count( $out ) >= AP_TL_MAX_ITEMS ) {
			break;
		}
	}
	return $out;
}

/**
 * Coerces a sanitised value to the attribute's registered schema (type and enum).
 *
 * @param mixed $value    Sanitised value.
 * @param array $schema   Registered attribute schema.
 * @param mixed $fallback Used when the value cannot be made to fit.
 * @return mixed
 */
function ap_tl_coerce( $value, array $schema, $fallback ) {
	$type = isset( $schema['type'] ) ? $schema['type'] : null;
	if ( is_array( $type ) ) {
		$type = null; // Multi-type attributes: keep the sanitised value as it is.
	}

	switch ( $type ) {
		case 'string':
			if ( ! is_string( $value ) ) {
				$value = is_scalar( $value ) ? (string) $value : $fallback;
			}
			break;
		case 'number':
			if ( ! is_int( $value ) && ! is_float( $value ) ) {
				$value = is_numeric( $value ) ? 0 + $value : $fallback;
			}
			break;
		case 'integer':
			if ( ! is_int( $value ) ) {
				$value = is_numeric( $value ) ? (int) $value : $fallback;
			}
			break;
		case 'boolean':
			if ( ! is_bool( $value ) ) {
				$value = in_array( $value, array( 1, '1', 'true' ), true ) ? true : ( in_array( $value, array( 0, '0', 'false', '' ), true ) ? false : $fallback );
			}
			break;
		case 'array':
			if ( ! is_array( $value ) || ! ap_tl_is_list( $value ) ) {
				$value = $fallback;
			}
			break;
		case 'object':
			if ( ! is_array( $value ) ) {
				$value = $fallback;
			}
			break;
	}

	if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
		$match = null;
		foreach ( $schema['enum'] as $allowed ) {
			if ( is_scalar( $value ) && (string) $allowed === (string) $value ) {
				$match = $allowed;
				break;
			}
		}
		$value = null !== $match ? $match : $fallback;
	}

	return $value;
}

/**
 * Collects every string stored in the reference's kit block attributes (md5 => true).
 *
 * @param array $blocks Parsed blocks.
 * @return array<string, bool>
 */
function ap_tl_known_strings( array $blocks ) {
	$known = array();
	$walk  = static function ( $value ) use ( &$walk, &$known ) {
		if ( is_string( $value ) ) {
			$known[ md5( $value ) ] = true;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $child ) {
				$walk( $child );
			}
		}
	};
	$visit = static function ( array $list ) use ( &$visit, $walk ) {
		foreach ( $list as $block ) {
			if ( isset( $block['blockName'] ) && ap_tl_is_kit_block( $block['blockName'] ) && ! empty( $block['attrs'] ) ) {
				$walk( $block['attrs'] );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$visit( $block['innerBlocks'] );
			}
		}
	};
	$visit( $blocks );
	return $known;
}

/**
 * Whether two values are the same once encoded (used to drop attributes equal to their default).
 *
 * @param mixed $a Value.
 * @param mixed $b Value.
 * @return bool
 */
function ap_tl_same_value( $a, $b ) {
	return wp_json_encode( $a ) === wp_json_encode( $b );
}

/**
 * Rich text from a core block edit (inline HTML: links, emphasis, line breaks).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function ap_tl_sanitize_rich( $value ) {
	$value = wp_check_invalid_utf8( is_scalar( $value ) ? (string) $value : '' );
	if ( strlen( $value ) > AP_TL_MAX_STRING ) {
		$value = substr( $value, 0, AP_TL_MAX_STRING );
	}
	return wp_kses_post( $value );
}

/**
 * HTML from a Custom HTML or Classic block edit. Users who may post unfiltered HTML keep it as
 * typed, as they would in the block editor; everyone else gets wp_kses_post().
 *
 * @param mixed $value Raw value.
 * @return string
 */
function ap_tl_sanitize_html_block( $value ) {
	$value = wp_check_invalid_utf8( is_scalar( $value ) ? (string) $value : '' );
	if ( strlen( $value ) > AP_TL_MAX_STRING ) {
		$value = substr( $value, 0, AP_TL_MAX_STRING );
	}
	return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
}
