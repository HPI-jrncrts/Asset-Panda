<?php
/**
 * TEST ONLY: copied into the local site's mu-plugins by tests/run.sh and
 * removed afterwards. Emulates the staging surroundings of the plugin:
 * - requests arrive with Host headers other than the local site's home, so
 *   WordPress's canonical redirect is switched off;
 * - stand-ins for the content plugin's resource / newsroom / resource-types
 *   when that plugin is absent;
 * - the kit's assetpanda/home-hero block, registered from the repo exactly as
 *   the staging theme registers it (gutenberg/blocks/home-hero + ui/ kit);
 * - a Yoast SEO stand-in (tests/yoast-stub.php) only when AP_TEST_YOAST is set
 *   or the request carries X-AP-Test-Yoast: 1.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}

// The wp-config.php kill switch, per request.
if ( ! empty( $_SERVER['HTTP_X_AP_TEST_DISABLE'] ) && ! defined( 'AP_SITE_DISABLE' ) ) {
	define( 'AP_SITE_DISABLE', true );
}

$ap_test_repo  = '__REPO__';
$ap_test_tests = '__TESTS__';

add_action(
	'init',
	static function () {
		remove_action( 'template_redirect', 'redirect_canonical' );
	},
	0
);
add_filter( 'redirect_canonical', '__return_false' );

add_action(
	'init',
	static function () use ( $ap_test_repo ) {
		if ( ! taxonomy_exists( 'resource-types' ) ) {
			register_taxonomy(
				'resource-types',
				array( 'resource' ),
				array(
					'public'  => true,
					'rewrite' => array( 'slug' => 'resource-center' ),
				)
			);
		}
		if ( ! post_type_exists( 'resource' ) ) {
			register_post_type( 'resource', array( 'public' => true, 'has_archive' => false ) );
		}
		if ( ! post_type_exists( 'newsroom' ) ) {
			register_post_type( 'newsroom', array( 'public' => true, 'has_archive' => false ) );
		}

		if ( ! defined( 'AP_UI_URL' ) ) {
			define( 'AP_UI_URL', home_url( '/ap-ui-test' ) );
		}
		if ( is_readable( $ap_test_repo . '/ui/src/loader.php' ) ) {
			require_once $ap_test_repo . '/ui/src/loader.php';
			if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'assetpanda/home-hero' ) ) {
				register_block_type( $ap_test_repo . '/gutenberg/blocks/home-hero' );
			}
		}
	},
	5
);

if ( getenv( 'AP_TEST_YOAST' ) || ! empty( $_SERVER['HTTP_X_AP_TEST_YOAST'] ) ) {
	require_once $ap_test_tests . '/yoast-stub.php';
}
