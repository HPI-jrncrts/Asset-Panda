<?php
/**
 * Asset Panda UI kit — Gutenberg wiring (adapter bootstrap).
 *
 * Drop-in include for the `assetpanda-2026` child theme. It is ADDITIVE: it
 * touches nothing the existing theme already does. Wire it up with a single
 * line at the end of the theme's functions.php:
 *
 *     require_once get_stylesheet_directory() . '/inc/asset-panda-ui.php';
 *
 * Responsibilities (this file only bootstraps — it never emits markup):
 *   1. Point the kit loader at this theme's synced copy of the kit.
 *   2. require the loader so ap_render_component() exists.
 *   3. Register the block adapters (attributes -> props -> kit).
 *   4. Enqueue the editor script for the block previews.
 *
 * Markup + CSS live ONLY in ui/components/<name>/. Never add HTML here.
 *
 * @package AssetPanda\Gutenberg
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

/**
 * Register the block adapters. Each block is a dynamic block: block.json
 * declares its attributes and points at render.php, which maps attributes ->
 * props -> ap_render_component(). save returns null (dynamic, server-rendered).
 */
add_action(
	'init',
	static function () {
		register_block_type( get_stylesheet_directory() . '/blocks/hero' );
	}
);

/**
 * Enqueue the editor script for the block(s). Buildless: the script uses the
 * global wp.* packages, so we declare the dependencies explicitly here (there
 * is no wp-scripts build step producing an index.asset.php).
 */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		$rel  = '/blocks/hero/index.js';
		$path = get_stylesheet_directory() . $rel;

		wp_enqueue_script(
			'assetpanda-hero-editor',
			get_stylesheet_directory_uri() . $rel,
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			file_exists( $path ) ? (string) filemtime( $path ) : AP_UI_VERSION,
			true
		);
	}
);
