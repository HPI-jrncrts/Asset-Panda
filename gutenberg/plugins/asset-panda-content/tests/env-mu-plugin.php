<?php
/**
 * TEST ONLY — copied into the local site's mu-plugins by tests/run.sh and
 * removed by tests/cleanup.php. Emulates staging around the plugin:
 * - loads read-only copies of dev Code Snippet 6 ("AP Blog 2026 Templates")
 *   and snippet 8 (the ap26 design pass), from AP_TEST_FIXTURES;
 * - with the request header X-AP-Test-Yoast: 1, defines WPSEO_VERSION so the
 *   plugin's SEO fallback must stand down.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// No cron during the test: nothing may write to the DB behind the snapshot.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

if ( ! empty( $_SERVER['HTTP_X_AP_TEST_YOAST'] ) && ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '99.0-test' );
}

$ap_test_fixtures = '__FIXTURES__';
if ( is_readable( $ap_test_fixtures . '/snippet6-loader.php' ) ) {
	require_once $ap_test_fixtures . '/snippet6-loader.php';
}
if ( is_readable( $ap_test_fixtures . '/snippet8.php' ) ) {
	require_once $ap_test_fixtures . '/snippet8.php';
}
