<?php
/**
 * The topic index: one small term record per published page, kept in post meta
 * and refreshed whenever the page changes.
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_DUPCHECK_META = '_ap_dupcheck_terms';

/**
 * Post types that are checked and compared: everything edited in the block
 * editor except media, videos, reusable blocks, templates and GEO update records.
 *
 * @return string[]
 */
function ap_dupcheck_types() {
	static $types = null;
	if ( null !== $types ) {
		return $types;
	}
	$skip  = array( 'attachment', 'ap_video', 'ap_geo_update', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_family', 'wp_font_face', 'wp_global_styles' );
	$types = array();
	foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $type ) {
		if ( ! in_array( $type, $skip, true ) && function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $type ) ) {
			$types[] = $type;
		}
	}
	/**
	 * Filters the post types the duplicate check covers.
	 *
	 * @param string[] $types Post type names.
	 */
	$types = array_values( array_unique( (array) apply_filters( 'ap_dupcheck_post_types', $types ) ) );
	return $types;
}

/**
 * Builds and stores a published post's term record.
 *
 * @param WP_Post $post Post.
 * @return array Record.
 */
function ap_dupcheck_index_post( WP_Post $post ) {
	$rec      = ap_dupcheck_terms( $post->post_title, $post->post_content );
	$rec['v'] = AP_DUPCHECK_ALGO;
	$rec['m'] = $post->post_modified_gmt;
	update_post_meta( $post->ID, AP_DUPCHECK_META, $rec );
	return $rec;
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status || ! in_array( $post->post_type, ap_dupcheck_types(), true ) ) {
			return;
		}
		ap_dupcheck_index_post( $post );
	},
	20,
	2
);

/**
 * Every published page of the covered types with its term record. Records that
 * are missing or older than the page are rebuilt here, within a time budget;
 * the rest are left for the next call (or the background backfill).
 *
 * @param float $budget Seconds allowed for rebuilding records.
 * @return array{docs:array<int,array>, pending:int}
 */
function ap_dupcheck_corpus( $budget = 4.0 ) {
	global $wpdb;
	$types = ap_dupcheck_types();
	if ( ! $types ) {
		return array(
			'docs'    => array(),
			'pending' => 0,
		);
	}
	$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$args = array_merge( array( AP_DUPCHECK_META, '_yoast_wpseo_focuskw' ), $types );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of %s placeholders.
	$sql  = $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_name, p.post_type, p.post_modified_gmt, t.meta_value AS rec, k.meta_value AS kw FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = %s LEFT JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ($in)", $args );
	$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$docs    = array();
	$pending = 0;
	$start   = microtime( true );
	foreach ( (array) $rows as $r ) {
		$id  = (int) $r->ID;
		$rec = $r->rec ? maybe_unserialize( $r->rec ) : null;
		if ( ! is_array( $rec ) || ( $rec['v'] ?? 0 ) !== AP_DUPCHECK_ALGO || ( $rec['m'] ?? '' ) !== $r->post_modified_gmt ) {
			if ( microtime( true ) - $start > $budget ) {
				++$pending;
				continue;
			}
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$rec = ap_dupcheck_index_post( $post );
		}
		$rec['id']   = $id;
		$rec['name'] = (string) $r->post_title;
		$rec['slug'] = (string) $r->post_name;
		$rec['type'] = (string) $r->post_type;
		$rec['kw']   = (string) $r->kw;
		$docs[ $id ] = $rec;
	}
	return array(
		'docs'    => $docs,
		'pending' => $pending,
	);
}

/** Schedules the one-off background build of every record. */
function ap_dupcheck_schedule_backfill() {
	if ( ! wp_next_scheduled( 'ap_dupcheck_backfill' ) ) {
		wp_schedule_single_event( time() + 10, 'ap_dupcheck_backfill' );
	}
}

add_action(
	'ap_dupcheck_backfill',
	static function () {
		$c = ap_dupcheck_corpus( 20.0 );
		if ( $c['pending'] > 0 ) {
			wp_schedule_single_event( time() + 30, 'ap_dupcheck_backfill' );
		}
	}
);
