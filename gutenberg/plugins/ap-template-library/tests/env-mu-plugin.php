<?php
/**
 * TEST ONLY — copied into the local site's mu-plugins by tests/run.sh and removed after.
 * Keeps the test hermetic: no cron, and every HTTP request WordPress tries to make to a
 * host other than the local site is refused and logged (proves the plugin makes none).
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
add_filter( 'automatic_updater_disabled', '__return_true' );

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( in_array( $host, array( 'asset-panda-local.local', '127.0.0.1', 'localhost' ), true ) ) {
			return $pre;
		}
		$trace  = wp_debug_backtrace_summary( null, 0, false );
		$plugin = false;
		foreach ( (array) $trace as $frame ) {
			if ( false !== strpos( (string) $frame, 'ap_tl_' ) ) {
				$plugin = true;
			}
		}
		file_put_contents( '__OUT__/blocked-http.log', gmdate( 'c' ) . ' ' . ( $plugin ? 'AP_TL ' : '' ) . $url . "\n", FILE_APPEND ); // phpcs:ignore
		return new WP_Error( 'ap_tl_test_offline', 'Blocked by the test environment.' );
	},
	1,
	3
);
