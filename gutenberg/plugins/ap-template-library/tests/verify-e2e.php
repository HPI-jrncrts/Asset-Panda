<?php
/**
 * TEST ONLY — after e2e.mjs: reads the drafts the browser created straight from the DB and
 * checks the stored block attributes, then runs uninstall.php and checks what it removed.
 *
 * php verify-e2e.php <path/to/wp-load.php> <out dir>
 *
 * @package APTemplateLibrary
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
$out     = rtrim( $argv[2], '/\\' );
$drafts  = json_decode( (string) @file_get_contents( $out . '/e2e-drafts.json' ), true ); // phpcs:ignore
$results = array();
function v_ok( $name, $pass, $detail = '' ) {
	global $results;
	$results[] = array(
		'test'   => $name,
		'pass'   => (bool) $pass,
		'detail' => $pass ? '' : $detail,
	);
	echo ( $pass ? 'PASS ' : 'FAIL ' ) . $name . ( $pass ? '' : '  -- ' . substr( wp_json_encode( $detail ), 0, 500 ) ) . "\n";
}
$top = static function ( $content ) {
	return array_values(
		array_filter(
			parse_blocks( $content ),
			static function ( $b ) {
				return ! empty( $b['blockName'] ) || '' !== trim( $b['innerHTML'] );
			}
		)
	);
};

$page = ! empty( $drafts['page'] ) ? get_post( (int) $drafts['page'] ) : null;
v_ok( 'V1 e2e page draft exists as a draft page', $page && 'draft' === $page->post_status && 'page' === $page->post_type, $drafts );
if ( $page ) {
	$b = $top( $page->post_content );
	v_ok( 'V2 stored section order (FAQ moved above Stats, duplicate removed)', array( 'assetpanda/vertical-hero', 'assetpanda/trust-strip', 'core/heading', 'core/paragraph', 'assetpanda/faq', 'assetpanda/stats', 'assetpanda/demo-cta' ) === wp_list_pluck( $b, 'blockName' ), wp_list_pluck( $b, 'blockName' ) );
	v_ok( 'V3 stored hero title is the edited one', "Inventory software\nbuilt by the Template Library" === $b[0]['attrs']['title'], $b[0]['attrs']['title'] );
	v_ok( 'V4 stored hero dashboard is the uploaded attachment, as a site-relative path', (bool) preg_match( '#^/wp-content/uploads/\d{4}/\d{2}/ap-tl-upload-test[^/]*\.png$#', $b[0]['attrs']['dashboard'] ), $b[0]['attrs']['dashboard'] );
	v_ok( 'V5 stored stats has the added item', 4 === count( $b[5]['attrs']['items'] ) && 'Faster audits' === $b[5]['attrs']['items'][3]['label'] && '4x' === $b[5]['attrs']['items'][3]['value'], $b[5]['attrs']['items'] );
	v_ok( 'V6 stored paragraph edit', false !== strpos( $b[3]['innerHTML'], 'The Template Library built this paragraph.' ) );
	v_ok( 'V7 title, slug, parent and page template', 'Template Library E2E Inventory' === $page->post_title && 'tl-e2e-inventory' === $page->post_name && (int) get_page_by_path( 'solutions' )->ID === (int) $page->post_parent && 'page-no-title' === get_post_meta( $page->ID, '_wp_page_template', true ) );
}
$blog = ! empty( $drafts['blog'] ) ? get_post( (int) $drafts['blog'] ) : null;
v_ok( 'V8 e2e blog draft exists as a draft resource of type blog', $blog && 'draft' === $blog->post_status && 'resource' === $blog->post_type && array( 'blog' ) === wp_get_object_terms( $blog->ID, 'resource-types', array( 'fields' => 'slugs' ) ) );
if ( $blog ) {
	$b = $top( $blog->post_content );
	v_ok( 'V9 blog list has 4 list-item blocks, last one added', 4 === count( $b[3]['innerBlocks'] ) && '<li>Audit twice a year</li>' === $b[3]['innerBlocks'][3]['innerHTML'] );
	v_ok( 'V10 blog nested button text edited', false !== strpos( $b[5]['innerBlocks'][0]['innerBlocks'][0]['innerHTML'], '>Talk to sales</a>' ) );
}

// Uninstall: only this plugin's data goes.
$keep_page = $page ? $page->ID : 0;
update_user_meta( 1, 'ap_tl_progress', array( 'x' => array( 'state' => '{}' ) ) );
define( 'WP_UNINSTALL_PLUGIN', 'ap-template-library/ap-template-library.php' );
include WP_PLUGIN_DIR . '/ap-template-library/uninstall.php';
wp_cache_flush();
global $wpdb;
v_ok( 'U1 uninstall removes the settings option', false === get_option( 'ap_tl_settings' ) );
v_ok( 'U2 uninstall removes every user\'s saved progress', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'ap_tl_progress'" ) );
v_ok( 'U3 uninstall removes the template marker post meta', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ap_tl_template'" ) );
v_ok( 'U4 uninstall keeps the pages it created', ! $keep_page || get_post( $keep_page ) instanceof WP_Post );

$blocked = (string) @file_get_contents( $out . '/blocked-http.log' ); // phpcs:ignore
v_ok( 'N1 the plugin made no outbound HTTP request during the whole run', false === strpos( $blocked, 'AP_TL ' ), $blocked );

file_put_contents( $out . '/verify-results.json', wp_json_encode( $results, JSON_PRETTY_PRINT ) );
$failed = count(
	array_filter(
		$results,
		static function ( $r ) {
			return ! $r['pass'];
		}
	)
);
echo "\n" . ( count( $results ) - $failed ) . '/' . count( $results ) . " passed\n";
exit( $failed ? 1 : 0 );
