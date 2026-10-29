<?php
/**
 * TEST ONLY — snapshot the local DB before the test installs anything.
 *
 * php snapshot.php <path/to/wp-load.php> <state.json>
 *
 * SHORTINIT: no plugin or theme code runs. Records max ids, every option, every usermeta
 * row (the test logs users in, which writes session tokens) and table checksums, so
 * cleanup.php can put the database back exactly.
 *
 * @package APTemplateLibrary
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
define( 'SHORTINIT', true );
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
global $wpdb;

$b64    = static function ( array $rows, $col ) {
	foreach ( $rows as &$row ) {
		$row[ $col ] = base64_encode( (string) $row[ $col ] ); // Lossless through JSON.
	}
	return $rows;
};
$tables = array( 'posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'options', 'usermeta', 'users', 'comments', 'commentmeta' );
$state  = array(
	'max'       => array(
		'post'     => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ),
		'postmeta' => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->postmeta}" ),
		'term'     => (int) $wpdb->get_var( "SELECT MAX(term_id) FROM {$wpdb->terms}" ),
		'tt'       => (int) $wpdb->get_var( "SELECT MAX(term_taxonomy_id) FROM {$wpdb->term_taxonomy}" ),
		'termmeta' => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->termmeta}" ),
		'usermeta' => (int) $wpdb->get_var( "SELECT MAX(umeta_id) FROM {$wpdb->usermeta}" ),
		'user'     => (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" ),
		'comment'  => (int) $wpdb->get_var( "SELECT MAX(comment_ID) FROM {$wpdb->comments}" ),
	),
	'options'   => $b64( $wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options}", ARRAY_A ), 'option_value' ),
	'usermeta'  => $b64( $wpdb->get_results( "SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta}", ARRAY_A ), 'meta_value' ),
	'tt_counts' => $wpdb->get_results( "SELECT term_taxonomy_id, count FROM {$wpdb->term_taxonomy}", ARRAY_A ),
	'checksums' => array(),
	'counts'    => array(),
);
foreach ( $tables as $t ) {
	$row                      = $wpdb->get_row( 'CHECKSUM TABLE ' . $wpdb->prefix . $t, ARRAY_A );
	$state['checksums'][ $t ] = $row ? $row['Checksum'] : null;
	$state['counts'][ $t ]    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . $t );
}
file_put_contents( $argv[2], json_encode( $state ) );
echo 'snapshot: max post ' . $state['max']['post'] . ', max user ' . $state['max']['user'] . ', options ' . count( $state['options'] ) . "\n";
exit( 0 );
