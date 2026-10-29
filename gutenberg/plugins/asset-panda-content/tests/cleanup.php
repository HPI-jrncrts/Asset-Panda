<?php
/**
 * TEST ONLY — put the local DB back exactly as setup.php found it, then
 * compare table checksums with the snapshot.
 *
 * php cleanup.php <path/to/wp-load.php> <state.json>
 *
 * Runs with SHORTINIT so no plugin, theme or snippet code executes.
 *
 * @package AssetPandaContent
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
define( 'SHORTINIT', true );
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
global $wpdb;

$state = json_decode( file_get_contents( $argv[2] ), true );
$max   = $state['max'];
$p     = $wpdb->prefix;

$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_relationships WHERE object_id > %d OR term_taxonomy_id > %d", $max['post'], $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}postmeta WHERE post_id > %d OR meta_id > %d", $max['post'], $max['postmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}posts WHERE ID > %d", $max['post'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}termmeta WHERE term_id > %d OR meta_id > %d", $max['term'], $max['termmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_taxonomy WHERE term_taxonomy_id > %d", $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}terms WHERE term_id > %d", $max['term'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}usermeta WHERE umeta_id > %d", $max['usermeta'] ) );
foreach ( $state['tt_counts'] as $row ) {
	$wpdb->update( "{$p}term_taxonomy", array( 'count' => (int) $row['count'] ), array( 'term_taxonomy_id' => (int) $row['term_taxonomy_id'] ) );
}

// Options: exactly the snapshot.
$keep = array();
foreach ( $state['options'] as $row ) {
	$row['option_value']         = base64_decode( $row['option_value'] );
	$keep[ $row['option_name'] ] = $row;
}
$now = $wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$p}options", ARRAY_A );
foreach ( $now as $row ) {
	$name = $row['option_name'];
	if ( ! isset( $keep[ $name ] ) || ( isset( $keep[ $name ]['option_id'] ) && (string) $keep[ $name ]['option_id'] !== (string) $row['option_id'] ) ) {
		// New, or deleted and re-added during the test (new id): re-inserted below with its old id.
		$wpdb->delete( "{$p}options", array( 'option_name' => $name ) );
	} elseif ( $keep[ $name ]['option_value'] !== $row['option_value'] || $keep[ $name ]['autoload'] !== $row['autoload'] ) {
		$wpdb->update(
			"{$p}options",
			array(
				'option_value' => $keep[ $name ]['option_value'],
				'autoload'     => $keep[ $name ]['autoload'],
			),
			array( 'option_name' => $name )
		);
	}
}
$have = array_column( $wpdb->get_results( "SELECT option_name FROM {$p}options", ARRAY_A ), "option_name" );
foreach ( $keep as $name => $row ) {
	if ( ! in_array( $name, $have, true ) ) {
		$wpdb->insert( "{$p}options", $row );
	}
}

$bad = 0;
foreach ( $state['checksums'] as $t => $sum ) {
	$row   = $wpdb->get_row( 'CHECKSUM TABLE ' . $p . $t, ARRAY_A );
	$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $p . $t );
	$same  = $row && (string) $row['Checksum'] === (string) $sum && $count === (int) $state['counts'][ $t ];
	$bad  += $same ? 0 : 1;
	echo ( $same ? 'CLEAN ' : 'DIFF  ' ) . $t . ' rows ' . $count . '/' . $state['counts'][ $t ] . "\n";
}
// Value-level check of options (CHECKSUM TABLE can differ on row layout alone).
$opt_diff = 0;
$rows     = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$p}options", ARRAY_A );
$seen     = array();
foreach ( $rows as $row ) {
	$seen[ $row['option_name'] ] = true;
	if ( ! isset( $keep[ $row['option_name'] ] ) || $keep[ $row['option_name'] ]['option_value'] !== $row['option_value'] || $keep[ $row['option_name'] ]['autoload'] !== $row['autoload'] ) {
		++$opt_diff;
		echo 'OPTION DIFF ' . $row['option_name'] . "\n";
	}
}
foreach ( $keep as $name => $row ) {
	if ( empty( $seen[ $name ] ) ) {
		++$opt_diff;
		echo 'OPTION MISSING ' . $name . "\n";
	}
}
echo 'options value-level: ' . ( $opt_diff ? $opt_diff . ' differ' : 'identical' ) . "\n";
if ( 0 === $opt_diff && $bad && isset( $state['checksums']['options'] ) ) {
	$row = $wpdb->get_row( 'CHECKSUM TABLE ' . $p . 'options', ARRAY_A );
	if ( $row && (string) $row['Checksum'] !== (string) $state['checksums']['options'] ) {
		--$bad; // Same rows and values; only the checksum differs.
	}
}
echo $bad ? "cleanup: $bad table(s) differ\n" : "cleanup: local DB identical to the snapshot\n";
exit( $bad ? 1 : 0 );
