<?php
/**
 * Plugin Name:       Asset Panda Site
 * Description:       Site-wide setup for www.assetpanda.com that is not page content: Google Tag Manager and Cookiebot, security headers, forced HTTPS and the homepage Ursa demo frame (production host only), a user enumeration guard, WordPress's emoji script kept off the pages, plus one-time Yoast SEO and site identity setup. Settings → Asset Panda Site.
 * Version:           1.2.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       asset-panda-site
 *
 * Safety model (staging becomes www, so a mistake here reaches every page):
 * - Tracking, consent, security headers, the HTTPS redirect and the demo frame
 *   run only when the request host is on the production host list (default
 *   www.assetpanda.com) and the feature's setting is on. assetpanda1dev,
 *   *.wpenginepowered.com, *.wpengine.com, localhost, *.local, *.test and bare
 *   IPs are refused even if someone adds them to the list. The user
 *   enumeration guard (includes/users.php) runs on any host.
 * - Nothing runs on activation. The Yoast and site identity setup run only
 *   when an administrator presses Apply or calls the REST endpoints.
 * - Every hook is wrapped: on any error the feature is skipped and the error
 *   is logged; the page itself is never broken.
 * - Kill switch (wp-config.php): define( 'AP_SITE_DISABLE', true ) stops all
 *   front-end output and redirects at once. The admin page stays available.
 * - Deactivation removes every hook. Uninstall deletes this plugin's own
 *   options only; Yoast settings, site identity and media stay as applied.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_SITE_VERSION', '1.2.1' );
define( 'AP_SITE_FILE', __FILE__ );
define( 'AP_SITE_DIR', __DIR__ );
define( 'AP_SITE_OPTION', 'ap_site_settings' );
define( 'AP_SITE_LAST_APPLY', 'ap_site_last_apply' );

/**
 * Writes a problem to the PHP error log without interrupting the request.
 *
 * @param string $message What went wrong.
 */
function ap_site_log( $message ) {
	if ( function_exists( 'error_log' ) ) {
		error_log( '[asset-panda-site] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Runs a callback and swallows any error, so a broken feature never breaks a page.
 *
 * @param callable $callback Work to do.
 * @param mixed    $fallback Returned when the callback throws.
 * @return mixed
 */
function ap_site_guard( $callback, $fallback = null ) {
	try {
		return call_user_func( $callback );
	} catch ( \Throwable $e ) {
		ap_site_log( $e->getMessage() . ' in ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		return $fallback;
	}
}

/**
 * Records a load problem and shows it to administrators.
 *
 * @param string $message What went wrong.
 */
function ap_site_fail( $message ) {
	$GLOBALS['ap_site_errors'][] = (string) $message;
	if ( ! has_action( 'admin_notices', 'ap_site_admin_notice_errors' ) ) {
		add_action( 'admin_notices', 'ap_site_admin_notice_errors' );
	}
}

/** Admin notice for load problems. */
function ap_site_admin_notice_errors() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$errors = isset( $GLOBALS['ap_site_errors'] ) ? (array) $GLOBALS['ap_site_errors'] : array();
	foreach ( array_unique( $errors ) as $error ) {
		echo '<div class="notice notice-error"><p><strong>Asset Panda Site:</strong> ' . esc_html( $error ) . '</p></div>';
	}
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	ap_site_fail( 'needs PHP 7.4 or newer; the plugin is idle.' );
	return;
}

$ap_site_includes = array( 'environment', 'tracking', 'headers', 'users', 'performance', 'homepage', 'media', 'seo', 'identity', 'rest', 'admin' );
foreach ( $ap_site_includes as $ap_site_include ) {
	if ( ! is_readable( AP_SITE_DIR . '/includes/' . $ap_site_include . '.php' ) ) {
		ap_site_fail( 'file includes/' . $ap_site_include . '.php is missing; the plugin is idle. Re-upload the zip.' );
		return;
	}
}
foreach ( $ap_site_includes as $ap_site_include ) {
	require_once AP_SITE_DIR . '/includes/' . $ap_site_include . '.php';
}
unset( $ap_site_includes, $ap_site_include );

/*
 * No activation or deactivation hooks on purpose: activating writes nothing
 * and applies nothing, and there is nothing to undo on deactivation (no
 * rewrite rules, no cron events, no files). Settings stay for reactivation;
 * uninstall.php removes them.
 */

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=asset-panda-site' ) ) . '">Settings</a>' );
		return $links;
	}
);
