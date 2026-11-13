<?php
/**
 * "Published: <date>" / "Updated On: <date>" on resources and press releases (1.3.28; launch cutoff 1.3.30).
 *
 * Joseph, 2026-11-12: "let's display the blog posts and all other resource posts pages their dates. So primarily it
 * should be 'Published: Date' but if there's a recent update on the content then it will have to be 'Updated On:
 * Date'. Italic, gray-ish texts. The menu nav posts doesn't need to have those dates. Just the main post hubs like the
 * blog posts, case studies, compare etc, and within the blog posts itself below the title. No need for the author".
 *
 * Where: the listing cards (Resource Center, blog, every type / topic / industry hub, search, newsroom) and the post
 * header under the title (listing.php), and the Compare hub's guide cards (an integration-directory block on the
 * /compare/ page, below). Not the header's mega-menu posts, not author names.
 *
 * "Updated" means the words changed. post_modified cannot tell: on www almost every post was re-saved by launch
 * passes (link rewrites, template conversions) that changed markup only, so it would call 433 of 454 posts updated.
 * Instead:
 *   - a save of a published post that changes its readable text (title, body text, text held in block attributes)
 *     records the save time in _ap_text_updated_gmt;
 *   - a post without that record gets it once, the first time its date is shown, from its revisions: the last saved
 *     revision whose text differs from the revision before it ('none' when they show no change);
 *   - a change counts from a day after publication (a same-day fix keeps "Published");
 *   - and only once the site was live (1.3.30, Joseph 2026-11-13: "only count edits after launch"): the 2026 site
 *     went live on 2026-10-31 at 06:35 Manila time, and the rebuild before it rewrote many posts (17 guides on
 *     Oct 23) that readers never saw in their earlier form. Filter: ap_content_updates_since (a GMT time).
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AP_CONTENT_TEXT_UPDATED_META' ) ) {
	define( 'AP_CONTENT_TEXT_UPDATED_META', '_ap_text_updated_gmt' );
}

/**
 * The moment from which a change to a post's words shows as "Updated On:" (GMT): the 2026 site's launch,
 * 2026-10-31 06:35 Manila time. Edits made while the site was being rebuilt keep "Published:".
 *
 * @return int Unix time.
 */
function ap_content_updates_since() {
	$since = strtotime( (string) apply_filters( 'ap_content_updates_since', '2026-10-30 22:35:00' ) . ' UTC' );
	return $since ? $since : 0;
}

/**
 * Post types that carry the date.
 *
 * @return string[]
 */
function ap_content_dated_types() {
	return (array) apply_filters( 'ap_content_dated_types', array( 'resource', 'newsroom' ) );
}

/**
 * Whether the installed kit draws a labelled date (resource-header's date_label, kit 1.3.197+). An older kit keeps
 * the old behaviour: only press releases show a plain date.
 *
 * @return bool
 */
function ap_content_kit_has_date_label() {
	static $ok = null;
	if ( null === $ok ) {
		$ok = false;
		if ( ap_content_kit_ready( 'resource-header', 'resource-cards' ) && function_exists( 'ap_ui_contract' ) ) {
			$contract = ap_ui_contract( 'resource-header' );
			$ok       = is_array( $contract ) && isset( $contract['props']['date_label'] );
		}
	}
	return $ok;
}

/**
 * A fingerprint of what a reader reads: the title, the body text, and the text some blocks keep in their attributes
 * (multi-word strings that are not URLs, class names or styles). Markup, links and image changes leave it unchanged.
 *
 * @param string $title   Post title.
 * @param string $content Post content.
 * @return string
 */
function ap_content_text_fingerprint( $title, $content ) {
	$content = (string) $content;
	$extra   = array();
	if ( false !== strpos( $content, '<!-- wp:' ) && preg_match_all( '/<!--\s+wp:[a-z0-9_\/-]+\s+(\{.*?\})\s+\/?-->/s', $content, $m ) ) {
		foreach ( $m[1] as $json ) {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			array_walk_recursive(
				$data,
				function ( $value, $key ) use ( &$extra ) {
					if ( is_string( $value ) && ! in_array( (string) $key, array( 'className', 'style', 'anchor', 'layout', 'metadata' ), true )
						&& preg_match( '/\s/', trim( $value ) ) && preg_match( '/\pL{2}/u', $value )
						&& ! preg_match( '~^\s*(https?:|/|#|mailto:|tel:|data:)~i', $value ) ) {
						$extra[] = $value;
					}
				}
			);
		}
	}
	$text = (string) preg_replace( '/<!--.*?-->/s', ' ', $content ) . ' ' . implode( ' ', $extra );
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = (string) preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text );
	$head = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return md5( trim( $head ) . "\n" . trim( $text ) );
}

/*
 * A save that changes the words of a live post records when (the post's new modified time). Saves that only touch
 * markup, links, images, terms or meta record nothing, and neither does the first publication.
 */
add_action(
	'post_updated',
	function ( $post_id, $after, $before ) {
		if ( ! $after instanceof WP_Post || ! $before instanceof WP_Post || ! in_array( $after->post_type, ap_content_dated_types(), true )
			|| 'publish' !== $after->post_status || 'publish' !== $before->post_status ) {
			return;
		}
		if ( ap_content_text_fingerprint( $before->post_title, $before->post_content ) === ap_content_text_fingerprint( $after->post_title, $after->post_content ) ) {
			return;
		}
		$gmt = (string) $after->post_modified_gmt;
		update_post_meta( (int) $post_id, AP_CONTENT_TEXT_UPDATED_META, '' !== $gmt && '0000-00-00 00:00:00' !== $gmt ? $gmt : gmdate( 'Y-m-d H:i:s' ) );
		$GLOBALS['ap_content_text_touched'][ (int) $post_id ] = true;
	},
	10,
	3
);

/**
 * When a post's text last changed after publication, from its revisions: the newest non-autosave revision whose text
 * differs from the revision before it, a day or more after publication. A change saved without a revision (a direct
 * database write) shows as the post differing from its newest revision and is dated by the post's modified time.
 *
 * @param WP_Post $post Post.
 * @return string GMT 'Y-m-d H:i:s', or 'none'.
 */
function ap_content_text_updated_from_revisions( WP_Post $post ) {
	global $wpdb;
	$pub  = strtotime( $post->post_date_gmt . ' UTC' );
	$from = $pub ? $pub + DAY_IN_SECONDS : PHP_INT_MAX;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$revs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_name, post_title, post_content, post_date_gmt FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY post_date_gmt ASC, ID ASC",
			$post->ID
		)
	);
	$prev = null;
	$last = 'none';
	foreach ( (array) $revs as $rev ) {
		if ( false !== strpos( (string) $rev->post_name, 'autosave' ) ) {
			continue;
		}
		$fp = ap_content_text_fingerprint( $rev->post_title, $rev->post_content );
		if ( null !== $prev && $fp !== $prev && strtotime( $rev->post_date_gmt . ' UTC' ) >= $from ) {
			$last = (string) $rev->post_date_gmt;
		}
		$prev = $fp;
	}
	if ( null !== $prev && ap_content_text_fingerprint( $post->post_title, $post->post_content ) !== $prev
		&& strtotime( $post->post_modified_gmt . ' UTC' ) >= $from ) {
		$last = (string) $post->post_modified_gmt;
	}
	return $last;
}

/**
 * When the post's text last changed after publication (GMT), or '' when it has not.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function ap_content_text_updated_gmt( WP_Post $post ) {
	$stored = (string) get_post_meta( $post->ID, AP_CONTENT_TEXT_UPDATED_META, true );
	if ( '' === $stored ) {
		$stored = ap_content_text_updated_from_revisions( $post );
		update_post_meta( $post->ID, AP_CONTENT_TEXT_UPDATED_META, $stored );
	}
	return 'none' === $stored ? '' : $stored;
}

/**
 * The date a post shows: "Published:" and its publication date, or "Updated On:" and the day its words last changed.
 *
 * @param WP_Post $post Post.
 * @return array{label:string,date:string,iso:string,updated:bool}|null Null for a post that is not published.
 */
function ap_content_display_date( $post ) {
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
		return null;
	}
	$pub = '0000-00-00 00:00:00' !== $post->post_date_gmt ? strtotime( $post->post_date_gmt . ' UTC' ) : strtotime( get_gmt_from_date( $post->post_date ) . ' UTC' );
	if ( ! $pub ) {
		return null;
	}
	$upd     = ap_content_text_updated_gmt( $post );
	$upd_ts  = '' !== $upd ? strtotime( $upd . ' UTC' ) : 0;
	$updated = $upd_ts && $upd_ts >= $pub + DAY_IN_SECONDS && $upd_ts >= ap_content_updates_since();
	$ts      = $updated ? $upd_ts : $pub;
	$out     = array(
		'label'   => $updated ? 'Updated On:' : 'Published:',
		'date'    => wp_date( 'F j, Y', $ts ),
		'iso'     => wp_date( 'Y-m-d', $ts ),
		'updated' => $updated,
	);
	return (array) apply_filters( 'ap_content_display_date', $out, $post );
}

/*
 * The Compare hub (/compare/) is a page: its guide cards are an integration-directory block whose items are typed
 * in, each linking to a comparison guide. A card that links to a dated post gets that post's date line (kit 1.3.199
 * draws date / date_iso / date_label under the card name; an older kit ignores them). One query per block.
 */
add_filter(
	'render_block_data',
	function ( $block ) {
		if ( ! is_array( $block ) || 'assetpanda/integration-directory' !== ( $block['blockName'] ?? '' ) || empty( $block['attrs']['items'] ) || ! is_array( $block['attrs']['items'] ) || ! ap_content_kit_has_date_label() ) {
			return $block;
		}
		$home  = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$paths = array();
		foreach ( $block['attrs']['items'] as $i => $item ) {
			$url = is_array( $item ) && is_string( $item['url'] ?? null ) ? trim( $item['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( ( $host && $host !== $home ) || '' === trim( $path, '/' ) ) {
				continue;
			}
			$paths[ $i ] = untrailingslashit( $path );
		}
		if ( ! $paths ) {
			return $block;
		}
		$slugs = array_values( array_unique( array_map( 'basename', $paths ) ) );
		$posts = get_posts(
			array(
				'post_type'        => ap_content_dated_types(),
				'post_status'      => 'publish',
				'post_name__in'    => $slugs,
				'numberposts'      => count( $slugs ) * 2,
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
		$by_path = array();
		foreach ( $posts as $post ) {
			$by_path[ untrailingslashit( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ) ] = $post;
		}
		foreach ( $paths as $i => $path ) {
			if ( isset( $by_path[ $path ] ) ) {
				$block['attrs']['items'][ $i ] = array_merge( $block['attrs']['items'][ $i ], ap_content_date_props( $by_path[ $path ] ) );
			}
		}
		return $block;
	}
);

/**
 * The date props for a resource-header / resource-cards item, or an empty array.
 *
 * @param WP_Post $post Post.
 * @return array
 */
function ap_content_date_props( $post ) {
	$d = ap_content_display_date( $post );
	if ( ! $d || '' === (string) $d['date'] ) {
		return array();
	}
	return array(
		'date'       => (string) $d['date'],
		'date_iso'   => (string) $d['iso'],
		'date_label' => (string) $d['label'],
	);
}
