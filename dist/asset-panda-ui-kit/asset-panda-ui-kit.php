<?php
/**
 * Plugin Name:       Asset Panda UI Kit
 * Description:       The shared Asset Panda presentation kit and its Gutenberg block adapters. Markup and CSS live in components/; each block maps editor fields to kit props. Deactivating removes the blocks and their assets and touches nothing else.
 * Version:           1.3.139
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * Text Domain:       assetpanda
 *
 * @package AssetPanda\UI
 *
 * DEPLOYMENT VEHICLE, NOT AN ARCHITECTURE CHANGE.
 * The repo's model is that ui/ is synced into each vertical's
 * lib/asset-panda-ui/. This plugin packages the same tree so it can be
 * installed on the dev site without SFTP and without editing the child theme.
 * Regenerate it from the repo — never edit these files here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_UI_KIT_FILE', __FILE__ );

/*
 * Asset version for every kit stylesheet and script. The loader falls back to a
 * fixed 1.0.0, which would let browsers and the host's page cache keep serving
 * old CSS after an update — so keep this in step with the Version header.
 */
define( 'AP_UI_VERSION', '1.3.139' );

/*
 * The loader derives AP_UI_DIR from its own location (the parent of src/),
 * which is this plugin's root — exactly the layout it expects. AP_UI_URL then
 * falls out of content_url(). Nothing else to configure.
 */
require_once __DIR__ . '/src/loader.php';
if ( is_readable( __DIR__ . '/blocks/site-header/latest-posts.php' ) ) {
	require_once __DIR__ . '/blocks/site-header/latest-posts.php';
}

/**
 * Every directory under blocks/ containing a block.json is a block.
 *
 * Auto-discovery rather than a hand-maintained list: a block is added by
 * shipping its folder, not by editing this file.
 *
 * @return string[] Absolute paths to block directories.
 */
function ap_ui_kit_block_dirs() {
	static $dirs = null;

	if ( null !== $dirs ) {
		return $dirs;
	}

	$found = glob( __DIR__ . '/blocks/*/block.json' );
	$dirs  = $found ? array_map( 'dirname', $found ) : array();

	return $dirs;
}

add_action(
	'init',
	static function () {
		foreach ( ap_ui_kit_block_dirs() as $dir ) {
			register_block_type( $dir );
		}
	}
);

/*
 * The dev theme still enqueues the old raw-HTML sector stylesheet
 * (ap-sector-pages) on the Fire and Police pages. Those pages are kit blocks
 * now, and its .ap-faq / .ap-awards / .ap-problem rules collide with the kit's
 * own classes, so it is dropped wherever a page renders kit blocks.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post();
		if ( $post && false !== strpos( (string) $post->post_content, '<!-- wp:assetpanda/' ) ) {
			wp_dequeue_style( 'ap-sector-pages' );
			wp_dequeue_script( 'ap-sector-pages' );
		}
	},
	100
);

/**
 * One shared editor script for every AP block. It derives its controls from
 * each block's registered attributes, so there is no per-block script.
 *
 * Buildless: it uses the global wp.* packages, so dependencies are declared
 * explicitly (there is no wp-scripts build producing an index.asset.php).
 */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		$path = __DIR__ . '/blocks/ap-editor.js';

		if ( ! file_exists( $path ) ) {
			return;
		}

		wp_enqueue_script(
			'assetpanda-blocks-editor',
			plugins_url( 'blocks/ap-editor.js', AP_UI_KIT_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			(string) filemtime( $path ),
			true
		);

		// The editor registers exactly the blocks this plugin registered on the server, so a new
		// component is editable the moment its block directory exists (no hand-kept list to grow).
		wp_add_inline_script(
			'assetpanda-blocks-editor',
			'window.AP_UI_BLOCKS = ' . wp_json_encode( array_values( array_map( 'basename', ap_ui_kit_block_dirs() ) ) ) . ';',
			'before'
		);
	}
);
