<?php
/**
 * Remembers which drafts were made with a "Duplicate" action (Duplicate Page's
 * "Duplicate This", Yoast Duplicate Post, or any admin action named
 * duplicate/clone/copy that passes the source post), so the check does not
 * flag a copy against the page it was copied from.
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_DUPCHECK_COPY_META = '_ap_dupcheck_copy_of';

/**
 * The source post id when the current request is a duplicate action.
 *
 * @return int 0 when it is not.
 */
function ap_dupcheck_request_source() {
	// phpcs:disable WordPress.Security.NonceVerification -- Read-only sniffing; the duplicate plugin checks its own nonce.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( '' === $action || ! preg_match( '/duplicat|clone|copy/', $action ) ) {
		return 0;
	}
	foreach ( array( 'post', 'post_id', 'id' ) as $key ) {
		if ( isset( $_REQUEST[ $key ] ) && is_scalar( $_REQUEST[ $key ] ) && ctype_digit( (string) $_REQUEST[ $key ] ) ) {
			return (int) $_REQUEST[ $key ];
		}
	}
	// phpcs:enable
	return 0;
}

add_action(
	'wp_insert_post',
	static function ( $post_id, $post, $update ) {
		static $copies = array();
		if ( $update || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$source = ap_dupcheck_request_source();
		if ( ! $source ) {
			$source = ap_dupcheck_identical_live( $post );
		}
		if ( ! $source || $source === (int) $post_id || ! get_post( $source ) ) {
			return;
		}
		$copies[ (int) $post_id ] = $source;
		if ( 1 === count( $copies ) ) {
			// Duplicate plugins copy every meta row after inserting the post, which
			// would bring along the original's own marker; write ours last.
			add_action(
				'shutdown',
				static function () use ( &$copies ) {
					foreach ( $copies as $copy => $src ) {
						delete_post_meta( $copy, AP_DUPCHECK_COPY_META );
						add_post_meta( $copy, AP_DUPCHECK_COPY_META, $src, true );
					}
				}
			);
		}
	},
	10,
	3
);

/**
 * A brand-new post that arrives already holding a live page's exact content is
 * a copy of it, whatever made it (WordPress's own "Duplicate" in the Pages
 * list, a plugin, the REST API). The editor's normal "Add New" starts empty.
 *
 * @param WP_Post $post The post just inserted.
 * @return int Live post id, or 0.
 */
function ap_dupcheck_identical_live( $post ) {
	global $wpdb;
	if ( ! $post instanceof WP_Post || 'publish' === $post->post_status || strlen( $post->post_content ) < 200 || ! in_array( $post->post_type, ap_dupcheck_types(), true ) ) {
		return 0;
	}
	$types = ap_dupcheck_types();
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $in is a list of %s placeholders.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID <> %d AND post_content = %s ORDER BY ID LIMIT 1", array_merge( $types, array( (int) $post->ID, $post->post_content ) ) ) );
}

/**
 * Whether a draft was started from the AP Template Library ("Use template"):
 * it holds the template's model page as a starting point, so it is a new page,
 * not an update of that page.
 *
 * @param int $post_id Post id.
 * @return bool
 */
function ap_dupcheck_from_template( $post_id ) {
	return '' !== (string) get_post_meta( $post_id, '_ap_tl_template', true );
}

/**
 * The page a draft was copied from (ours or Yoast Duplicate Post's marker).
 *
 * @param int $post_id Post id.
 * @return int 0 when it is not a copy.
 */
function ap_dupcheck_copy_source( $post_id ) {
	$src = (int) get_post_meta( $post_id, AP_DUPCHECK_COPY_META, true );
	if ( ! $src ) {
		$src = (int) get_post_meta( $post_id, '_dp_original', true );
	}
	return $src === (int) $post_id ? 0 : $src;
}
