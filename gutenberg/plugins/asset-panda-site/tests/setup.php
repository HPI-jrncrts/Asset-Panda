<?php
/**
 * TEST ONLY: snapshot the local DB, then activate the plugin and add test pages.
 *
 * php setup.php <wp-load.php> <state.json> snapshot   SHORTINIT: no plugin or theme code runs;
 *                                                     run it BEFORE anything is installed.
 * php setup.php <wp-load.php> <state.json> activate   writes <state dir>/pages.json
 *
 * @package AssetPandaSite
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$ap_mode = isset( $argv[3] ) ? $argv[3] : '';
if ( 'snapshot' === $ap_mode ) {
	define( 'SHORTINIT', true );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
global $wpdb;

if ( 'snapshot' === $ap_mode ) {
	$tables = array( 'posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'options', 'usermeta', 'users', 'comments' );
	$state  = array(
		'max'       => array(
			'post'     => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ),
			'postmeta' => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->postmeta}" ),
			'term'     => (int) $wpdb->get_var( "SELECT MAX(term_id) FROM {$wpdb->terms}" ),
			'tt'       => (int) $wpdb->get_var( "SELECT MAX(term_taxonomy_id) FROM {$wpdb->term_taxonomy}" ),
			'termmeta' => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->termmeta}" ),
			'user'     => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" ),
			'usermeta' => (int) $wpdb->get_var( "SELECT MAX(umeta_id) FROM {$wpdb->usermeta}" ),
		),
		'options'   => array_map(
			static function ( $row ) {
				$row['option_value'] = base64_encode( $row['option_value'] );
				return $row;
			},
			$wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A )
		),
		'tt_counts' => $wpdb->get_results( "SELECT term_taxonomy_id, count FROM {$wpdb->term_taxonomy}", ARRAY_A ),
		'checksums' => array(),
		'counts'    => array(),
		'uploads'   => array(),
	);
	foreach ( $tables as $t ) {
		$row                      = $wpdb->get_row( 'CHECKSUM TABLE ' . $wpdb->prefix . $t, ARRAY_A );
		$state['checksums'][ $t ] = $row ? $row['Checksum'] : null;
		$state['counts'][ $t ]    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $t );
	}
	// Upload folders that exist now (the test may create this month's).
	$base = WP_CONTENT_DIR . '/uploads';
	$ym   = $base . '/' . gmdate( 'Y' ) . '/' . gmdate( 'm' );
	$state['uploads'] = array(
		'year_dir'   => dirname( $ym ),
		'year_had'   => is_dir( dirname( $ym ) ),
		'month_dir'  => $ym,
		'month_had'  => is_dir( $ym ),
		'month_list' => is_dir( $ym ) ? array_values( array_diff( scandir( $ym ), array( '.', '..' ) ) ) : array(),
	);
	file_put_contents( $argv[2], json_encode( $state ) );
	echo 'snapshot: max post ' . $state['max']['post'] . ', options ' . count( $state['options'] ) . "\n";
	exit( 0 );
}

if ( 'activate' !== $ap_mode ) {
	fwrite( STDERR, "mode must be snapshot or activate\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$r = activate_plugin( 'asset-panda-site/asset-panda-site.php' );
if ( is_wp_error( $r ) ) {
	fwrite( STDERR, 'activate failed: ' . $r->get_error_message() . "\n" );
	exit( 2 );
}
echo 'activated; ap_site_settings stored on activation: ' . var_export( false !== get_option( 'ap_site_settings' ), true ) . "\n";

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $admin ? (int) $admin[0] : 1 );

$hero  = '<!-- wp:assetpanda/home-hero {"eyebrow":"Asset Management Software","title":"Track every asset","poster":"https://assetpanda1dev.wpenginepowered.com/wp-content/uploads/2026/10/ursa-demo-poster-desktop.png","poster_alt":"Ursa demo"} /-->';
$pages = array(
	'home'          => array( 'ap-site-test-home', 'AP Site Test Home', $hero, 0 ),
	'page'          => array( 'ap-site-test-page', 'AP Site Test Page', '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->', 0 ),
	'splash'        => array( 'login-splash-1', 'Asset Panda Login – Splash Page 1', '<!-- wp:paragraph --><p>Splash</p><!-- /wp:paragraph -->', 0 ),
	'splash_p'      => array( 'login-splash-5-p', 'Asset Panda Login – Splash Page 5 P', '<!-- wp:paragraph --><p>Splash</p><!-- /wp:paragraph -->', 0 ),
	'thanks'        => array( 'thanks-ap-site-test', 'Thanks test', '<!-- wp:paragraph --><p>Thanks</p><!-- /wp:paragraph -->', 0 ),
	'ty_vertical'   => array( 'k12-thank-you-page-ap-test', 'K12 thank you test', '<!-- wp:paragraph --><p>Thanks</p><!-- /wp:paragraph -->', 0 ),
	'parent'        => array( 'ap-site-test-parent', 'Parent', '<!-- wp:paragraph --><p>Parent</p><!-- /wp:paragraph -->', 0 ),
);
$ids = array();
foreach ( $pages as $key => $p ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $p[0],
			'post_title'   => $p[1],
			'post_content' => $p[2],
			'post_parent'  => $p[3],
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'page ' . $key . ': ' . $id->get_error_message() . "\n" );
		exit( 3 );
	}
	$ids[ $key ] = array(
		'id'   => $id,
		'path' => wp_parse_url( get_permalink( $id ), PHP_URL_PATH ),
	);
}
// A child whose own slug starts with login-splash, under a normal parent: must NOT match /login-splash*.
$child        = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'login-splash-9',
		'post_title'   => 'Nested',
		'post_content' => '<!-- wp:paragraph --><p>Nested</p><!-- /wp:paragraph -->',
		'post_parent'  => $ids['parent']['id'],
	)
);
$ids['nested'] = array(
	'id'   => $child,
	'path' => wp_parse_url( get_permalink( $child ), PHP_URL_PATH ),
);
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $ids['home']['id'] );
file_put_contents( dirname( $argv[2] ) . '/pages.json', json_encode( $ids ) );
foreach ( $ids as $k => $v ) {
	echo $k . ' = ' . $v['id'] . ' ' . $v['path'] . "\n";
}
