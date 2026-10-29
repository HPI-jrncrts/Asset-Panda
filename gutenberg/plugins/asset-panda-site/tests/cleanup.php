<?php
/**
 * TEST ONLY: put the local site back exactly as setup.php found it.
 *
 * php cleanup.php <wp-load.php> <state.json> files     full load: delete files of attachments
 *                                                      created during the test
 * php cleanup.php <wp-load.php> <state.json> restore   SHORTINIT: delete new rows, restore every
 *                                                      option, compare table checksums
 *
 * @package AssetPandaSite
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$ap_mode = isset( $argv[3] ) ? $argv[3] : '';
if ( 'restore' === $ap_mode ) {
	define( 'SHORTINIT', true );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
global $wpdb;

$state = json_decode( file_get_contents( $argv[2] ), true );
$max   = $state['max'];
$p     = $wpdb->prefix;

if ( 'files' === $ap_mode ) {
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$p}posts WHERE post_type = 'attachment' AND ID > %d", $max['post'] ) );
	foreach ( $ids as $id ) {
		$file = get_attached_file( (int) $id );
		$ok   = wp_delete_attachment( (int) $id, true );
		echo 'attachment ' . $id . ' ' . ( $ok ? 'deleted' : 'NOT deleted' ) . ' (' . ( $file ? basename( $file ) : '-' ) . ', file ' . ( $file && file_exists( $file ) ? 'STILL THERE' : 'gone' ) . ")\n";
	}
	// Upload folders the test created.
	$u = $state['uploads'];
	if ( is_dir( $u['month_dir'] ) ) {
		$now   = array_values( array_diff( scandir( $u['month_dir'] ), array( '.', '..' ) ) );
		$extra = array_diff( $now, $u['month_list'] );
		foreach ( $extra as $f ) {
			echo 'leftover upload file: ' . $f . "\n";
		}
		if ( ! $u['month_had'] && ! $now ) {
			rmdir( $u['month_dir'] );
		}
	}
	if ( ! $u['year_had'] && is_dir( $u['year_dir'] ) && ! array_diff( scandir( $u['year_dir'] ), array( '.', '..' ) ) ) {
		rmdir( $u['year_dir'] );
	}
	exit( 0 );
}

$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_relationships WHERE object_id > %d OR term_taxonomy_id > %d", $max['post'], $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}postmeta WHERE post_id > %d OR meta_id > %d", $max['post'], $max['postmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}posts WHERE ID > %d", $max['post'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}termmeta WHERE term_id > %d OR meta_id > %d", $max['term'], $max['termmeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}term_taxonomy WHERE term_taxonomy_id > %d", $max['tt'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}terms WHERE term_id > %d", $max['term'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}usermeta WHERE user_id > %d OR umeta_id > %d", $max['user'], $max['usermeta'] ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}users WHERE ID > %d", $max['user'] ) );
foreach ( $state['tt_counts'] as $row ) {
	$wpdb->update( "{$p}term_taxonomy", array( 'count' => (int) $row['count'] ), array( 'term_taxonomy_id' => (int) $row['term_taxonomy_id'] ) );
}

// Options: exactly the snapshot, original option_id included.
$keep = array();
foreach ( $state['options'] as $row ) {
	$row['option_value']         = base64_decode( $row['option_value'] );
	$keep[ $row['option_name'] ] = $row;
}
foreach ( $wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$p}options", ARRAY_A ) as $row ) {
	$name = $row['option_name'];
	if ( ! isset( $keep[ $name ] ) || (string) $keep[ $name ]['option_id'] !== (string) $row['option_id'] ) {
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
$have = array_column( $wpdb->get_results( "SELECT option_name FROM {$p}options", ARRAY_A ), 'option_name' );
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
	if ( ! $same && 'options' === $t ) {
		$same = $count === (int) $state['counts'][ $t ]; // Value-level check below decides.
	}
	$bad += $same ? 0 : 1;
	echo ( $same ? 'CLEAN ' : 'DIFF  ' ) . $t . ' rows ' . $count . '/' . $state['counts'][ $t ] . "\n";
}
$opt_diff = 0;
$seen     = array();
foreach ( $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$p}options", ARRAY_A ) as $row ) {
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
$bad += $opt_diff ? 1 : 0;
echo $bad ? "cleanup: $bad check(s) differ\n" : "cleanup: local DB identical to the snapshot\n";
exit( $bad ? 1 : 0 );
