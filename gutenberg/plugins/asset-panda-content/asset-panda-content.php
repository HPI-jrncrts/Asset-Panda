<?php
/**
 * Plugin Name:       Asset Panda Content
 * Description:       The Resource Center and Pressroom content platform for the 2026 site: `resource` + `resource-types` and `newsroom` at the live www.assetpanda.com URLs, the import REST contract, Yoast-compatible SEO meta (with a fallback while Yoast is absent) and rendering through the 2026 blog templates. 1.3.0: live's look through the kit's resource-* components, live's industry and topic filters at live's filter addresses, and an instant-search index. 1.3.4: blog and compare posts follow the Figma post designs (light header, image below the title) instead of live's band: the kit's resource-header "article" variant when the kit has it, else the 2026 templates' own post header. 1.3.5: article body copy at weight 400.
 * Version:           1.3.10
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       asset-panda-content
 *
 * Safety model (a 404 or 500 on staging blocks everything):
 * - Nothing here can fatal on load: requirements are checked first, every
 *   include is checked before it is required, and the plugin then runs in
 *   "off" mode (admin notice only) instead of throwing.
 * - Activation never throws: errors are stored and shown as a notice, the
 *   rewrite rules are rebuilt on the next request, and content is untouched.
 * - Every renderer and REST handler is wrapped: on any Throwable the
 *   original output (or a JSON error) goes out, never a white screen.
 * - Deactivation drops the stored rewrite rules so WordPress rebuilds them
 *   without this plugin's routes. Uninstall removes only this plugin's
 *   options: posts, terms and meta stay in the database.
 * - Kill switches (wp-config.php): AP_CONTENT_DISABLE_RENDER (templates fall
 *   back to the 2026 blog templates' own output) and AP_CONTENT_DISABLE_SEO
 *   (no fallback SEO tags).
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_CONTENT_VERSION', '1.3.10' );
define( 'AP_CONTENT_CONTRACT', '1.1' );
define( 'AP_CONTENT_FILE', __FILE__ );
define( 'AP_CONTENT_DIR', __DIR__ );
// 2 (1.3.0): live's filter addresses (/resource-center/filter/…, /resource-center/<type>/filter/…).
define( 'AP_CONTENT_REWRITE_VER', '2' );

/**
 * Records a problem and shows it to administrators, without stopping the site.
 *
 * @param string $message What went wrong.
 */
function ap_content_fail( $message ) {
	$GLOBALS['ap_content_errors'][] = (string) $message;
	if ( ! has_action( 'admin_notices', 'ap_content_admin_notice' ) ) {
		add_action( 'admin_notices', 'ap_content_admin_notice' );
	}
}

/** Admin notice for load or activation problems. */
function ap_content_admin_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$errors = isset( $GLOBALS['ap_content_errors'] ) ? (array) $GLOBALS['ap_content_errors'] : array();
	$stored = get_option( 'ap_content_last_error' );
	if ( $stored ) {
		$errors[] = 'Activation: ' . $stored;
	}
	foreach ( array_unique( $errors ) as $error ) {
		echo '<div class="notice notice-error"><p><strong>Asset Panda Content:</strong> ' . esc_html( $error ) . '</p></div>';
	}
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	ap_content_fail( 'needs PHP 7.4 or newer; the plugin is idle.' );
	return;
}

$ap_content_includes = array( 'types', 'industry', 'meta', 'seo', 'rest', 'search', 'render', 'listing', 'templates', 'callouts' );
foreach ( $ap_content_includes as $ap_content_include ) {
	if ( ! is_readable( AP_CONTENT_DIR . '/includes/' . $ap_content_include . '.php' ) ) {
		ap_content_fail( 'file includes/' . $ap_content_include . '.php is missing; the plugin is idle. Re-upload the zip.' );
		return;
	}
}
foreach ( $ap_content_includes as $ap_content_include ) {
	require_once AP_CONTENT_DIR . '/includes/' . $ap_content_include . '.php';
}
unset( $ap_content_includes, $ap_content_include );

register_activation_hook( __FILE__, 'ap_content_activate' );
register_deactivation_hook( __FILE__, 'ap_content_deactivate' );

/**
 * Activation: register the types, create the six Resource Types and rebuild
 * the rewrite rules. Never throws.
 */
function ap_content_activate() {
	try {
		ap_content_register_types();
		ap_content_ensure_terms();
		flush_rewrite_rules( false );
		update_option( 'ap_content_rewrite_ver', AP_CONTENT_REWRITE_VER, true );
		delete_option( 'ap_content_last_error' );
	} catch ( \Throwable $e ) {
		update_option( 'ap_content_last_error', $e->getMessage(), false );
		// Rebuild on the next request instead.
		delete_option( 'ap_content_rewrite_ver' );
	}
}

/**
 * Deactivation: forget the stored rewrite rules. WordPress rebuilds them on
 * the next request, when this plugin's routes are no longer registered.
 */
function ap_content_deactivate() {
	delete_option( 'rewrite_rules' );
	delete_option( 'ap_content_rewrite_ver' );
}
