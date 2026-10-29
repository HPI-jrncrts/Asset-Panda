<?php
/**
 * TEST ONLY — snapshot the local DB, activate the plugin, add the staging-like
 * DB templates (home / archive / single built from snippet 6's blocks).
 *
 * php setup.php <path/to/wp-load.php> <state.json> snapshot   (SHORTINIT: no plugin or theme code runs;
 *                                                                run it BEFORE the test mu-plugin is installed)
 * php setup.php <path/to/wp-load.php> <state.json> activate
 *
 * @package AssetPandaContent
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

if ( 'snapshot' !== $ap_mode && 'activate' !== $ap_mode ) {
	fwrite( STDERR, "mode must be snapshot or activate\n" );
	exit( 1 );
}

if ( 'snapshot' === $ap_mode ) :
$tables = array( 'posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'options', 'usermeta', 'users', 'comments' );
$state  = array(
	'max'       => array(
		'post'      => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ),
		'postmeta'  => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->postmeta}" ),
		'term'      => (int) $wpdb->get_var( "SELECT MAX(term_id) FROM {$wpdb->terms}" ),
		'tt'        => (int) $wpdb->get_var( "SELECT MAX(term_taxonomy_id) FROM {$wpdb->term_taxonomy}" ),
		'termmeta'  => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->termmeta}" ),
		'usermeta'  => (int) $wpdb->get_var( "SELECT MAX(umeta_id) FROM {$wpdb->usermeta}" ),
	),
	'options'   => array_map(
		function ( $row ) {
			$row['option_value'] = base64_encode( $row['option_value'] ); // Lossless through JSON.
			return $row;
		},
		$wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A )
	),
	'tt_counts' => $wpdb->get_results( "SELECT term_taxonomy_id, count FROM {$wpdb->term_taxonomy}", ARRAY_A ),
	'checksums' => array(),
	'counts'    => array(),
);
foreach ( $tables as $t ) {
	$row                          = $wpdb->get_row( 'CHECKSUM TABLE ' . $wpdb->prefix . $t, ARRAY_A );
	$state['checksums'][ $t ]     = $row ? $row['Checksum'] : null;
	$state['counts'][ $t ]        = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $t );
}
file_put_contents( $argv[2], json_encode( $state ) );
echo 'snapshot: max post ' . $state['max']['post'] . ', options ' . count( $state['options'] ) . "\n";
exit( 0 );
endif;

require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Activate the plugin (runs its activation hook).
$r = activate_plugin( 'asset-panda-content/asset-panda-content.php' );
if ( is_wp_error( $r ) ) {
	fwrite( STDERR, 'activate failed: ' . $r->get_error_message() . "\n" );
	exit( 2 );
}
echo 'activated; last_error=' . var_export( get_option( 'ap_content_last_error' ), true ) . "\n";

// Staging-like DB templates for the active theme (twentytwentyfive here).
$theme = get_stylesheet();
$main  = function ( $inner ) {
	return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n"
		. '<!-- wp:group {"tagName":"main","className":"ap26-blog-main","layout":{"type":"default"}} -->' . "\n"
		. '<main class="wp-block-group ap26-blog-main">' . $inner . '</main>' . "\n"
		. '<!-- /wp:group -->' . "\n"
		. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
};
$templates = array(
	'home'    => $main( '<!-- wp:ap26/blog-hero /-->' . "\n" . '<!-- wp:ap26/blog-grid /-->' ),
	'archive' => $main( '<!-- wp:ap26/category-hero /-->' . "\n" . '<!-- wp:ap26/blog-grid /-->' ),
	'single'  => '<!-- wp:ap26/sticky-bar /-->' . "\n" . $main(
		'<!-- wp:ap26/post-header /-->' . "\n"
		. '<!-- wp:group {"className":"ap26-article","layout":{"type":"default"}} -->' . "\n"
		. '<div class="wp-block-group ap26-article"><!-- wp:post-content /--></div>' . "\n"
		. '<!-- /wp:group -->' . "\n"
		. '<!-- wp:ap26/share-bar /-->' . "\n"
		. '<!-- wp:ap26/related-posts /-->'
	),
);
foreach ( $templates as $slug => $content ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'wp_template',
			'post_name'    => $slug,
			'post_title'   => ucfirst( $slug ) . ' (ap test)',
			'post_status'  => 'publish',
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'template ' . $slug . ': ' . $id->get_error_message() . "\n" );
		exit( 3 );
	}
	wp_set_object_terms( $id, $theme, 'wp_theme' );
	echo "template $slug = $id ($theme)\n";
}
flush_rewrite_rules( false );
echo 'permalink_structure=' . get_option( 'permalink_structure' ) . "\n";
