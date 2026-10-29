<?php
/**
 * Asset Panda UI — component loader.
 *
 * This file is the single entry point every vertical uses to render shared
 * markup. Neither gutenberg/ nor unyson/ writes HTML; they build a props array
 * and call ap_render_component(). The markup and CSS live only in
 * ui/components/<name>/.
 *
 * This file is copied verbatim into each vertical's lib/asset-panda-ui/ by
 * tools/sync-ui.ps1. All paths resolve relative to this file, so the copied
 * tree behaves identically to the source tree. Do not edit the copies — edit
 * ui/ and re-run the sync.
 *
 * @package AssetPanda\UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Allow the file to be required outside of WordPress (tests, CLI) without
	// exploding, but never expose it to a direct web request.
	if ( PHP_SAPI !== 'cli' ) {
		exit;
	}
}

if ( ! defined( 'AP_UI_DIR' ) ) {
	// The kit root — the directory that contains src/ and components/.
	define( 'AP_UI_DIR', dirname( __DIR__ ) );
}

if ( ! defined( 'AP_UI_VERSION' ) ) {
	// Bumps the asset cache buster. Sync can rewrite this per release.
	define( 'AP_UI_VERSION', '1.0.0' );
}

if ( ! defined( 'AP_UI_URL' ) ) {
	/*
	 * Public URL to the kit root, used to enqueue CSS. Each vertical defines
	 * this when it loads the kit (it knows where its own lib/ lives). We fall
	 * back to deriving it from the WordPress content dir so a forgotten define
	 * degrades to "styles may 404" rather than a fatal error.
	 */
	if ( function_exists( 'content_url' ) && defined( 'WP_CONTENT_DIR' ) ) {
		$ap_ui_rel = str_replace(
			wp_normalize_path( WP_CONTENT_DIR ),
			'',
			wp_normalize_path( AP_UI_DIR )
		);
		define( 'AP_UI_URL', content_url( $ap_ui_rel ) );
		unset( $ap_ui_rel );
	} else {
		define( 'AP_UI_URL', '' );
	}
}

/**
 * Whether validation should fail loud (throw) or fail safe (log + degrade).
 *
 * Staging (WP_DEBUG on) throws so contract mistakes are caught before release.
 * Production logs the problem and renders with whatever it can, so one bad prop
 * never white-screens a live page.
 *
 * @return bool
 */
function ap_ui_is_strict() {
	return defined( 'WP_DEBUG' ) && WP_DEBUG;
}

/**
 * Report a contract violation.
 *
 * @param string $component Component name.
 * @param string $message   Human-readable problem.
 *
 * @throws InvalidArgumentException When running in strict mode.
 */
function ap_ui_fail( $component, $message ) {
	$full = sprintf( '[asset-panda-ui] component "%s": %s', $component, $message );

	if ( ap_ui_is_strict() ) {
		throw new InvalidArgumentException( $full );
	}

	error_log( $full ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
}

/**
 * Load and cache a component's contract (component.json).
 *
 * @param string $component Component name.
 *
 * @return array|null Decoded contract, or null when missing/invalid.
 */
function ap_ui_contract( $component ) {
	static $cache = array();

	if ( array_key_exists( $component, $cache ) ) {
		return $cache[ $component ];
	}

	$path = AP_UI_DIR . '/components/' . $component . '/component.json';

	if ( ! is_readable( $path ) ) {
		ap_ui_fail( $component, 'missing component.json contract.' );
		$cache[ $component ] = null;
		return null;
	}

	$decoded = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( ! is_array( $decoded ) || ! isset( $decoded['props'] ) || ! is_array( $decoded['props'] ) ) {
		ap_ui_fail( $component, 'component.json is malformed or has no "props".' );
		$cache[ $component ] = null;
		return null;
	}

	$cache[ $component ] = $decoded;
	return $decoded;
}

/**
 * Check a single value against its declared type.
 *
 * @param mixed  $value Value to check.
 * @param string $type  Declared type from the contract.
 *
 * @return bool
 */
function ap_ui_type_matches( $value, $type ) {
	switch ( $type ) {
		case 'string':
			return is_string( $value );
		case 'number':
			return is_int( $value ) || is_float( $value );
		case 'integer':
			return is_int( $value );
		case 'boolean':
			return is_bool( $value );
		case 'array':
			return is_array( $value ) && array_values( $value ) === $value;
		case 'object':
			return is_array( $value );
		case 'any':
			return true;
		default:
			return false;
	}
}

/**
 * Validate raw props against a contract and return the normalized set.
 *
 * Applies defaults for absent optional props, enforces required props, checks
 * declared types, and enforces enum membership.
 *
 * @param string $component Component name (for error messages).
 * @param array  $contract  Decoded contract.
 * @param array  $props     Raw props supplied by the caller.
 *
 * @return array Normalized props ready to hand to render.php.
 */
function ap_ui_validate( $component, array $contract, array $props ) {
	$schema     = $contract['props'];
	$normalized = array();

	foreach ( $schema as $key => $rule ) {
		$rule     = is_array( $rule ) ? $rule : array();
		$type     = isset( $rule['type'] ) ? $rule['type'] : 'any';
		$required = ! empty( $rule['required'] );
		$has      = array_key_exists( $key, $props );

		if ( ! $has ) {
			if ( $required ) {
				ap_ui_fail( $component, sprintf( 'missing required prop "%s".', $key ) );
			}
			$normalized[ $key ] = array_key_exists( 'default', $rule ) ? $rule['default'] : null;
			continue;
		}

		$value = $props[ $key ];

		if ( ! ap_ui_type_matches( $value, $type ) ) {
			ap_ui_fail(
				$component,
				sprintf( 'prop "%s" expected %s, got %s.', $key, $type, gettype( $value ) )
			);
			$value = array_key_exists( 'default', $rule ) ? $rule['default'] : $value;
		}

		if ( isset( $rule['enum'] ) && is_array( $rule['enum'] ) && ! in_array( $value, $rule['enum'], true ) ) {
			ap_ui_fail(
				$component,
				sprintf( 'prop "%s" value %s is not one of [%s].', $key, wp_json_encode( $value ), implode( ', ', $rule['enum'] ) )
			);
			$value = array_key_exists( 'default', $rule ) ? $rule['default'] : $value;
		}

		$normalized[ $key ] = $value;
	}

	// Surface props the caller sent that the contract does not declare — almost
	// always a typo or a drifted adapter. Never silently pass them through.
	foreach ( $props as $key => $unused ) {
		if ( ! isset( $schema[ $key ] ) ) {
			ap_ui_fail( $component, sprintf( 'unknown prop "%s" is not declared in the contract.', $key ) );
		}
	}

	return $normalized;
}

/**
 * Register and enqueue a component's stylesheet (and the shared tokens).
 *
 * Verticals never enqueue component CSS themselves — doing it here keeps all
 * styling knowledge inside the kit. Safe to call repeatedly; WordPress
 * de-duplicates by handle.
 *
 * @param string $component Component name.
 */
function ap_ui_enqueue_styles( $component ) {
	if ( ! function_exists( 'wp_enqueue_style' ) || '' === AP_UI_URL ) {
		return;
	}

	if ( ! wp_style_is( 'ap-ui-tokens', 'registered' ) ) {
		wp_register_style( 'ap-ui-tokens', AP_UI_URL . '/src/tokens.css', array(), AP_UI_VERSION );
	}
	wp_enqueue_style( 'ap-ui-tokens' );

	$handle = 'ap-ui-' . $component;
	$css    = AP_UI_DIR . '/components/' . $component . '/style.css';

	if ( ! wp_style_is( $handle, 'registered' ) && is_readable( $css ) ) {
		wp_register_style(
			$handle,
			AP_UI_URL . '/components/' . $component . '/style.css',
			array( 'ap-ui-tokens' ),
			AP_UI_VERSION
		);
	}
	wp_enqueue_style( $handle );

	ap_ui_enqueue_motion();
	ap_ui_enqueue_behaviour( $component );
}

/**
 * Register and enqueue the shared motion layer.
 *
 * Motion is cross-cutting rather than component-owned: one stylesheet defines
 * the reveal/hover vocabulary and one script runs a single IntersectionObserver
 * for the whole document. Enqueued when any component renders, so a page using
 * none of the kit still receives neither file.
 *
 * The script is idempotent and footer-registered; see ui/src/motion.js.
 */
function ap_ui_enqueue_motion() {
	if ( ! function_exists( 'wp_enqueue_style' ) || '' === AP_UI_URL ) {
		return;
	}

	if ( ! wp_style_is( 'ap-ui-motion', 'registered' ) ) {
		wp_register_style(
			'ap-ui-motion',
			AP_UI_URL . '/src/motion.css',
			array( 'ap-ui-tokens' ),
			AP_UI_VERSION
		);
	}
	wp_enqueue_style( 'ap-ui-motion' );

	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		return;
	}

	if ( ! wp_script_is( 'ap-ui-motion', 'registered' ) ) {
		wp_register_script(
			'ap-ui-motion',
			AP_UI_URL . '/src/motion.js',
			array(),
			AP_UI_VERSION,
			true
		);
	}
	wp_enqueue_script( 'ap-ui-motion' );
}

/**
 * Register and enqueue a component's optional behaviour.js.
 *
 * Most components have none — this no-ops cleanly when the file is absent.
 * Mirrors ap_ui_enqueue_styles(): on demand, once per component per page
 * (WordPress de-dupes by handle), footer-registered, and dependent on the
 * shared motion runtime so a component can rely on .ap-inview being managed.
 *
 * @param string $component Component name.
 */
function ap_ui_enqueue_behaviour( $component ) {
	if ( ! function_exists( 'wp_enqueue_script' ) || '' === AP_UI_URL ) {
		return;
	}

	$handle = 'ap-ui-' . $component . '-behaviour';
	$js     = AP_UI_DIR . '/components/' . $component . '/behaviour.js';

	if ( ! is_readable( $js ) ) {
		return;
	}

	if ( ! wp_script_is( $handle, 'registered' ) ) {
		wp_register_script(
			$handle,
			AP_UI_URL . '/components/' . $component . '/behaviour.js',
			array( 'ap-ui-motion' ),
			AP_UI_VERSION,
			true
		);
	}
	wp_enqueue_script( $handle );
}

/**
 * Render a shared UI component to an HTML string.
 *
 * This is the ONLY public API of the kit. Every vertical calls it with the
 * component name and a flat props array; it validates against the contract,
 * enqueues the component's styles, and returns the rendered markup.
 *
 * @param string $component Component name (folder under components/).
 * @param array  $props     Props to pass to the component.
 *
 * @return string Rendered HTML, or '' on failure in non-strict mode.
 */
function ap_render_component( $component, array $props = array() ) {
	$component = (string) $component;

	if ( '' === $component || ! preg_match( '/^[a-z0-9\-]+$/', $component ) ) {
		ap_ui_fail( $component, 'invalid component name.' );
		return '';
	}

	$contract = ap_ui_contract( $component );
	if ( null === $contract ) {
		return '';
	}

	$render = AP_UI_DIR . '/components/' . $component . '/render.php';
	if ( ! is_readable( $render ) ) {
		ap_ui_fail( $component, 'missing render.php.' );
		return '';
	}

	// Validate first — in strict mode this throws before any output.
	$props = ap_ui_validate( $component, $contract, $props );

	ap_ui_enqueue_styles( $component );

	// Render in an isolated scope. render.php sees only $props and $component.
	$renderer = static function ( $__file, $props, $component ) {
		ob_start();
		include $__file;
		return ob_get_clean();
	};

	return $renderer( $render, $props, $component );
}

/**
 * Public URL of a file a component ships in its own assets/ folder.
 *
 * Decorative artwork that is part of a component's design (icons, rules,
 * brand marks) travels with the component, so it is versioned and synced with
 * the markup that uses it. Content imagery (photos, logos, screenshots) is NOT
 * an asset — it arrives as a prop from the editor. Escape at the point of
 * output with esc_url().
 *
 * The kit version rides along as ?ver=, as it does on the stylesheets: hosts
 * cache these files for a year, so an asset fixed in a release would otherwise
 * keep serving its old bytes at the same URL.
 *
 * @param string $component Component name.
 * @param string $file      File name inside components/<name>/assets/.
 * @return string URL, or '' when the kit has no public URL configured.
 */
function ap_ui_asset_url( $component, $file ) {
	if ( '' === AP_UI_URL ) {
		return '';
	}
	return AP_UI_URL . '/components/' . rawurlencode( $component ) . '/assets/' . rawurlencode( $file ) . '?ver=' . rawurlencode( AP_UI_VERSION );
}

/**
 * Escape a string for HTML and preserve the author's line breaks.
 *
 * Several designs break a heading at a specific point ("Built for the field. /
 * Ready for anything."). Editors type the break; this renders it as <br>
 * without ever letting markup through.
 *
 * @param string $text Raw, untrusted text.
 * @return string Escaped HTML.
 */
function ap_ui_lines( $text ) {
	return nl2br( esc_html( trim( (string) $text ) ), false );
}

/**
 * Apply brand-mark styling to already-escaped text.
 *
 * The design requires "UrsaAI" to render as a gradient word with a superscript
 * "AI" wherever it appears in running copy. The input MUST already be escaped;
 * the only markup this adds is the fixed span below, so it cannot introduce
 * anything the author typed.
 *
 * @param string $escaped Escaped HTML.
 * @return string Escaped HTML with brand marks wrapped.
 */
function ap_ui_brandmarks( $escaped ) {
	return preg_replace( '/\bUrsa\s?AI\b/', '<span class="ap-ursa">Ursa<sup>AI</sup></span>', (string) $escaped );
}

/**
 * Sanitise an inline SVG icon supplied as a prop.
 *
 * The live pages draw most icons as inline SVG, often with gradients, so they
 * cannot travel as image URLs. Only drawing elements and presentation
 * attributes survive: no scripts, event handlers, style attributes,
 * foreignObject or external references. Anything that does not start as an
 * <svg> element renders nothing.
 *
 * @param string $markup Raw, untrusted SVG markup.
 * @return string Sanitised SVG, or ''.
 */
function ap_ui_svg( $markup ) {
	$markup = trim( (string) $markup );
	if ( '' === $markup || 0 !== stripos( $markup, '<svg' ) ) {
		return '';
	}

	return wp_kses( $markup, ap_ui_svg_allowlist() );
}

/**
 * The wp_kses allowlist for inline SVG: drawing elements and presentation
 * attributes only. Shared by ap_ui_svg() and ap_ui_markup().
 *
 * @return array<string, array<string, bool>>
 */
function ap_ui_svg_allowlist() {
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
 * Sanitise a decorative mockup fragment supplied as a prop.
 *
 * Some heroes illustrate the product with small mock UI cards (a request, a
 * record, a status chip) whose structure differs from card to card. They are
 * decoration, not content, so rather than a schema per card the fragment
 * arrives as markup and only a fixed vocabulary survives: text-level and
 * layout tags, images, inline SVG, and class/style attributes. Styles pass
 * WordPress's safe-CSS filter, widened only by the geometry properties these
 * cards position themselves with, and narrowed to refuse position: fixed and
 * sticky so a fragment can never cover the page. No links, forms, scripts,
 * event handlers or iframes can get through.
 *
 * @param string $markup Raw, untrusted markup.
 * @return string Sanitised markup, or ''.
 */
function ap_ui_markup( $markup ) {
	static $allowed = null;

	$markup = trim( (string) $markup );
	if ( '' === $markup ) {
		return '';
	}

	if ( null === $allowed ) {
		$common  = array(
			'class'       => true,
			'style'       => true,
			'aria-hidden' => true,
		);
		$allowed = ap_ui_svg_allowlist();
		foreach ( array( 'div', 'span', 'p', 'b', 'strong', 'em', 'i', 'sup', 'sub', 'small', 'ul', 'ol', 'li' ) as $tag ) {
			$allowed[ $tag ] = $common;
		}
		$allowed['br']  = array();
		$allowed['img'] = $common + array(
			'src'      => true,
			'alt'      => true,
			'width'    => true,
			'height'   => true,
			'loading'  => true,
			'decoding' => true,
		);
	}

	$geometry = static function ( $properties ) {
		return array_merge( $properties, array( 'left', 'top', 'right', 'bottom', 'position', 'z-index', 'transform', 'opacity', 'object-fit', 'gap' ) );
	};
	// A decorative card positions itself inside its scene (absolute / relative),
	// never over the page: fixed and sticky would let a fragment cover the whole
	// viewport, so those two values are refused even though core allows them.
	$no_overlay = static function ( $allow, $css ) {
		return preg_match( '/^\s*position\s*:\s*(?:fixed|sticky)\b/i', (string) $css ) ? false : $allow;
	};
	add_filter( 'safe_style_css', $geometry );
	add_filter( 'safecss_filter_attr_allow_css', $no_overlay, 10, 2 );
	$out = wp_kses( $markup, $allowed );
	remove_filter( 'safecss_filter_attr_allow_css', $no_overlay, 10 );
	remove_filter( 'safe_style_css', $geometry );

	return $out;
}
