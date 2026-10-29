<?php
/**
 * Plugin Name:       AP Template Library
 * Description:       A template picker for the Asset Panda 2026 site. Every template is a real reference page or resource on this site (solution, product / who we help, industry hub, platform, integration, landing, blog post, case study, compare, utility…). Pick one in Template Library, fill in its sections as boxes with text fields and image pickers, and "Send to Draft" builds a new draft from the reference's blocks. Nothing is ever published by this plugin.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       ap-template-library
 * Domain Path:       /languages
 *
 * How it works:
 * - A template is a pointer to a published reference post. The reference is read when a
 *   screen opens, so a template always matches its reference page and never drifts.
 * - The builder turns each top-level block of the reference into a box. Kit blocks
 *   (assetpanda/*) get fields generated from their registered attributes (block.json,
 *   generated from the kit contract), with help text from the kit's component.json.
 *   Common core blocks (paragraph, heading, list, image, button, embed, HTML) get
 *   simple fields; all other blocks are kept exactly as they are.
 * - "Send to Draft" sends only edited values and the index of each box's reference
 *   block. The server rebuilds every block from the reference, applies the sanitised
 *   values, and calls wp_insert_post() with status draft.
 *
 * Safety: capability checks on every screen and route, REST nonces, no file writes,
 * no remote requests, and no fatal on load. A missing reference shows as
 * "Reference missing" instead of failing.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_TL_VERSION', '1.0.1' );
define( 'AP_TL_FILE', __FILE__ );
define( 'AP_TL_DIR', __DIR__ );
define( 'AP_TL_OPTION', 'ap_tl_settings' );
define( 'AP_TL_PROGRESS_META', 'ap_tl_progress' );
define( 'AP_TL_SOURCE_META', '_ap_tl_template' );
define( 'AP_TL_REST_NS', 'ap-template-library/v1' );

/**
 * Records a load problem and shows it to administrators, without stopping the site.
 *
 * @param string $message What went wrong.
 */
function ap_tl_fail( $message ) {
	$GLOBALS['ap_tl_errors'][] = (string) $message;
	if ( ! has_action( 'admin_notices', 'ap_tl_admin_load_notice' ) ) {
		add_action( 'admin_notices', 'ap_tl_admin_load_notice' );
	}
}

/** Admin notice for load problems. */
function ap_tl_admin_load_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$errors = isset( $GLOBALS['ap_tl_errors'] ) ? (array) $GLOBALS['ap_tl_errors'] : array();
	foreach ( array_unique( $errors ) as $error ) {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'AP Template Library:', 'ap-template-library' ) . '</strong> ' . esc_html( $error ) . '</p></div>';
	}
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	ap_tl_fail( 'needs PHP 7.4 or newer; the plugin is idle.' );
	return;
}

$ap_tl_includes = array( 'registry', 'sanitize', 'blocks', 'drafts', 'rest', 'admin' );
foreach ( $ap_tl_includes as $ap_tl_include ) {
	if ( ! is_readable( AP_TL_DIR . '/includes/' . $ap_tl_include . '.php' ) ) {
		ap_tl_fail( 'file includes/' . $ap_tl_include . '.php is missing; the plugin is idle. Re-upload the zip.' );
		return;
	}
}
foreach ( $ap_tl_includes as $ap_tl_include ) {
	require_once AP_TL_DIR . '/includes/' . $ap_tl_include . '.php';
}
unset( $ap_tl_includes, $ap_tl_include );

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'ap-template-library', false, dirname( plugin_basename( AP_TL_FILE ) ) . '/languages' );
	}
);

/**
 * URL of a file in this plugin.
 *
 * @param string $path Path relative to the plugin root.
 * @return string
 */
function ap_tl_url( $path ) {
	return plugins_url( ltrim( $path, '/' ), AP_TL_FILE );
}

/**
 * Asset version: the file's mtime, so a re-uploaded zip is never served from cache.
 *
 * @param string $path Path relative to the plugin root.
 * @return string
 */
function ap_tl_asset_ver( $path ) {
	$file = AP_TL_DIR . '/' . ltrim( $path, '/' );
	return is_readable( $file ) ? AP_TL_VERSION . '.' . filemtime( $file ) : AP_TL_VERSION;
}
