<?php
/**
 * Asset Panda UI kit — Unyson (live) wiring (adapter bootstrap).
 *
 * Drop-in include for the `e25-base-theme-child` theme. It is ADDITIVE: it does
 * not touch anything the existing theme already does. Wire it up with a single
 * line at the end of the theme's functions.php:
 *
 *     require_once get_stylesheet_directory() . '/inc/asset-panda-ui.php';
 *
 * Its only job is to make ap_render_component() available so the Unyson
 * shortcode views can call it. Unyson auto-discovers the shortcode itself from
 * framework-customizations/theme/shortcodes/ — no registration needed here.
 *
 * Markup + CSS live ONLY in ui/components/<name>/. Never add HTML here.
 *
 * @package AssetPanda\Unyson
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The kit is synced into this theme at lib/asset-panda-ui/ by tools/sync-ui.ps1.
// Define the kit's public URL + version BEFORE requiring the loader so the
// loader uses these rather than its content_url() fallback.
if ( ! defined( 'AP_UI_VERSION' ) ) {
	define( 'AP_UI_VERSION', '1.0.0' );
}
if ( ! defined( 'AP_UI_URL' ) ) {
	define( 'AP_UI_URL', get_stylesheet_directory_uri() . '/lib/asset-panda-ui' );
}

require_once get_stylesheet_directory() . '/lib/asset-panda-ui/src/loader.php';
