<?php
/**
 * TEST ONLY — put the local DB back exactly as snapshot.php found it, then compare.
 *
 * php cleanup.php <path/to/wp-load.php> <state.json>
 *
 * SHORTINIT, so no plugin, theme or test code executes.
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

$state = json_decode( file_get_contents( $argv[2] ), true );
$max   = $state['max'];
$p     = $wpdb->prefix;

$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_relationships WHERE object_id > %d OR term_taxonomy_id > %d", $max['post'], $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}postmeta WHERE post_id > %d OR meta_id > %d", $max['post'], $max['postmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}posts WHERE ID > %d", $max['post'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}termmeta WHERE term_id > %d OR meta_id > %d", $max['term'], $max['termmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_taxonomy WHERE term_taxonomy_id > %d", $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}terms WHERE term_id > %d", $max['term'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}commentmeta WHERE comment_id > %d", $max['comment'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}comments WHERE comment_ID > %d", $max['comment'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}users WHERE ID > %d", $max['user'] ) );
foreach ( $state['tt_counts'] as $row ) {
	$wpdb->update( "{$p}term_taxonomy", array( 'count' => (int) $row['count'] ), array( 'term_taxonomy_id' => (int) $row['term_taxonomy_id'] ) );
}

// usermeta: exactly the snapshot (session tokens of existing users included).
$keep = array();
foreach ( $state['usermeta'] as $row ) {
	$row['meta_value']            = base64_decode( $row['meta_value'] );
	$keep[ (int) $row['umeta_id'] ] = $row;
}
foreach ( $wpdb->get_results( "SELECT umeta_id, user_id, meta_key, meta_value FROM {$p}usermeta", ARRAY_A ) as $row ) {
	$id = (int) $row['umeta_id'];
	if ( ! isset( $keep[ $id ] ) ) {
		$wpdb->delete( "{$p}usermeta", array( 'umeta_id' => $id ) );
	} elseif ( $keep[ $id ]['meta_value'] !== $row['meta_value'] || $keep[ $id ]['meta_key'] !== $row['meta_key'] || (string) $keep[ $id ]['user_id'] !== (string) $row['user_id'] ) {
		$wpdb->update(
			"{$p}usermeta",
			array(
				'user_id'    => $keep[ $id ]['user_id'],
				'meta_key'   => $keep[ $id ]['meta_key'],
				'meta_value' => $keep[ $id ]['meta_value'],
			),
			array( 'umeta_id' => $id )
		);
	}
}
$have = array_map( 'intval', $wpdb->get_col( "SELECT umeta_id FROM {$p}usermeta" ) );
foreach ( $keep as $id => $row ) {
	if ( ! in_array( $id, $have, true ) ) {
		$wpdb->insert( "{$p}usermeta", $row );
	}
}

// Options: exactly the snapshot.
$okeep = array();
foreach ( $state['options'] as $row ) {
	$row['option_value']          = base64_decode( $row['option_value'] );
	$okeep[ $row['option_name'] ] = $row;
}
foreach ( $wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$p}options", ARRAY_A ) as $row ) {
	$name = $row['option_name'];
	if ( ! isset( $okeep[ $name ] ) || (string) $okeep[ $name ]['option_id'] !== (string) $row['option_id'] ) {
		$wpdb->delete( "{$p}options", array( 'option_name' => $name ) );
	} elseif ( $okeep[ $name ]['option_value'] !== $row['option_value'] || $okeep[ $name ]['autoload'] !== $row['autoload'] ) {
		$wpdb->update(
			"{$p}options",
			array(
				'option_value' => $okeep[ $name ]['option_value'],
				'autoload'     => $okeep[ $name ]['autoload'],
			),
			array( 'option_name' => $name )
		);
	}
}
$have = $wpdb->get_col( "SELECT option_name FROM {$p}options" );
foreach ( $okeep as $name => $row ) {
	if ( ! in_array( $name, $have, true ) ) {
		$wpdb->insert( "{$p}options", $row );
	}
}

// Value-level verification of the two tables restored row by row.
$value_diff = array(
	'options'  => 0,
	'usermeta' => 0,
);
foreach ( $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$p}options", ARRAY_A ) as $row ) {
	if ( ! isset( $okeep[ $row['option_name'] ] ) || $okeep[ $row['option_name'] ]['option_value'] !== $row['option_value'] || $okeep[ $row['option_name'] ]['autoload'] !== $row['autoload'] ) {
		++$value_diff['options'];
		echo 'OPTION DIFF ' . $row['option_name'] . "\n";
	}
}
foreach ( $wpdb->get_results( "SELECT umeta_id, user_id, meta_key, meta_value FROM {$p}usermeta", ARRAY_A ) as $row ) {
	$id = (int) $row['umeta_id'];
	if ( ! isset( $keep[ $id ] ) || $keep[ $id ]['meta_value'] !== $row['meta_value'] || $keep[ $id ]['meta_key'] !== $row['meta_key'] ) {
		++$value_diff['usermeta'];
		echo 'USERMETA DIFF ' . $id . "\n";
	}
}

$bad = 0;
foreach ( $state['checksums'] as $t => $sum ) {
	$row   = $wpdb->get_row( 'CHECKSUM TABLE ' . $p . $t, ARRAY_A );
	$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $p . $t );
	$same  = $row && (string) $row['Checksum'] === (string) $sum && $count === (int) $state['counts'][ $t ];
	if ( ! $same && isset( $value_diff[ $t ] ) && 0 === $value_diff[ $t ] && $count === (int) $state['counts'][ $t ] ) {
		$same = true; // Every row value matches the snapshot; only the physical checksum differs.
	}
	$bad += $same ? 0 : 1;
	echo ( $same ? 'CLEAN ' : 'DIFF  ' ) . $t . ' rows ' . $count . '/' . $state['counts'][ $t ] . "\n";
}
echo $bad ? "cleanup: $bad table(s) differ\n" : "cleanup: local DB identical to the snapshot\n";
exit( $bad ? 1 : 0 );
