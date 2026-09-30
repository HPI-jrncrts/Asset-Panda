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
/**
 * Every directory under blocks/ that contains a block.json is a block.
 *
 * Auto-discovery rather than a hand-maintained list: a block is added by
 * creating its folder, not by editing this file.
 *
 * @return string[] Absolute paths to block directories.
 */
function ap_block_dirs() {
	static $dirs = null;

	if ( null !== $dirs ) {
		return $dirs;
	}

	$found = glob( get_stylesheet_directory() . '/blocks/*/block.json' );
	$dirs  = $found ? array_map( 'dirname', $found ) : array();

	return $dirs;
}

add_action(
	'init',
	static function () {
		foreach ( ap_block_dirs() as $dir ) {
			register_block_type( $dir );
		}
	}
);

/**
 * Enqueue the shared block editor.
 *
 * Every AP block is an adapter over a kit component, so they all need the same
 * editor: a labelled field per attribute plus a ServerSideRender preview.
 * blocks/ap-editor.js derives those controls from each block's registered
 * attributes and registers the editor half of all of them, so there is one
 * script rather than one per block.
 *
 * Buildless: it uses the global wp.* packages, so the dependencies are declared
 * explicitly here (there is no wp-scripts build step producing an
 * index.asset.php).
 */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		$path = get_stylesheet_directory() . '/blocks/ap-editor.js';

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_script(
			'assetpanda-blocks-editor',
			get_stylesheet_directory_uri() . '/blocks/ap-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			(string) filemtime( $path ),
			true
		);
	}
);
