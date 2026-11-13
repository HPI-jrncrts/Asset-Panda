<?php
/**
 * Plugin Name:       Asset Panda UI Kit
 * Description:       The shared Asset Panda presentation kit and its Gutenberg block adapters. Markup and CSS live in components/; each block maps editor fields to kit props. Deactivating removes the blocks and their assets and touches nothing else.
 * Version:           1.3.199
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
define( 'AP_UI_VERSION', '1.3.199' );

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
			'window.AP_UI_BLOCKS = ' . wp_json_encode( array_values( array_map( 'basename', ap_ui_kit_block_dirs() ) ) ) . ';'
			. 'window.AP_UI_KIT = ' . wp_json_encode( ap_ui_kit_editor_styles() ) . ';'
			. 'window.AP_UI_SERVER_BLOCKS = ' . wp_json_encode( ap_ui_kit_server_only_blocks() ) . ';',
			'before'
		);
	}
);

/**
 * The kit components a component renders: itself plus any it renders inside
 * (solution-hero → form-hero), read from the literal names in render.php.
 *
 * @param string $component Component name.
 * @param array  $seen      Components already visited.
 * @return string[]
 */
function ap_ui_kit_component_uses( $component, array &$seen ) {
	if ( isset( $seen[ $component ] ) ) {
		return array();
	}
	$seen[ $component ] = true;
	$out  = array( $component );
	$file = __DIR__ . '/components/' . $component . '/render.php';
	if ( is_readable( $file ) && preg_match_all( "/ap_render_component\(\s*'([a-z0-9-]+)'/", (string) file_get_contents( $file ), $m ) ) {
		foreach ( $m[1] as $child ) {
			$out = array_merge( $out, ap_ui_kit_component_uses( $child, $seen ) );
		}
	}
	return $out;
}

/**
 * Builder v1 (2026-11): what the editor canvas needs to style a block as the
 * page does. The front end inlines each rendered component's sheet; the
 * editor's previews come over REST, so blocks/ap-editor.js adds the sheets of
 * a block's components to the canvas itself. Editor only.
 *
 * @return array { url, ver, css: { block name: component names } }
 */
function ap_ui_kit_editor_styles() {
	$css = array();
	foreach ( ap_ui_kit_block_dirs() as $dir ) {
		$block     = basename( $dir );
		$component = $block;
		$render    = $dir . '/render.php';
		if ( is_readable( $render ) && preg_match( "/ap_render_component\(\s*'([a-z0-9-]+)'/", (string) file_get_contents( $render ), $m ) ) {
			$component = $m[1];
		}
		$seen  = array();
		$names = array();
		foreach ( ap_ui_kit_component_uses( $component, $seen ) as $name ) {
			if ( is_readable( __DIR__ . '/components/' . $name . '/style.css' ) ) {
				$names[] = $name;
			}
		}
		$css[ 'assetpanda/' . $block ] = $names;
	}
	return array(
		'url' => defined( 'AP_UI_URL' ) ? untrailingslashit( AP_UI_URL ) : plugins_url( '', AP_UI_KIT_FILE ),
		'ver' => AP_UI_VERSION,
		'css' => $css,
	);
}

/**
 * Dynamic blocks registered in PHP with no editor script, under the prefixes
 * this site owns (snippet 6's ap26/*, the content plugin's ap-content/*).
 * Without an editor half the editor shows them as unsupported; ap-editor.js
 * registers one (the server render as the preview). Other plugins' blocks
 * are never touched: they may register their editor half later themselves.
 *
 * @return string[]
 */
function ap_ui_kit_server_only_blocks() {
	$out = array();
	if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
		return $out;
	}
	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		if ( 0 !== strpos( $name, 'ap26/' ) && 0 !== strpos( $name, 'ap-content/' ) ) {
			continue;
		}
		if ( ! $type->is_dynamic() || ! empty( $type->editor_script_handles ) ) {
			continue;
		}
		$out[] = $name;
	}
	return $out;
}
