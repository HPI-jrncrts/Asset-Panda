<?php
/**
 * "Push to the live page": a draft that duplicates a live page (a Duplicate
 * copy, or a rewrite the check found) sends the parts the writer picks to that
 * live page — section by section — instead of being published as a second page.
 *
 * A page is split into sections at its H2 headings (core blocks) and at every
 * top-level kit block. Draft sections are matched to live sections by heading;
 * the writer chooses which to replace, which new ones to add and which live
 * sections to drop. Title, SEO fields and featured image are separate choices.
 * Preview writes an autosave of the live page (nothing visible changes); Push
 * updates the live page and keeps a snapshot so Undo puts it back.
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_DUPCHECK_TARGET_META = '_ap_dupcheck_target';
const AP_DUPCHECK_UNDO_META   = '_ap_dupcheck_undo';
/** Yoast fields a push may carry over. */
const AP_DUPCHECK_SEO_KEYS = array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' );

/**
 * The live page a draft updates: the page it was linked to, else the page it
 * was copied from. 0 when none.
 *
 * @param int $draft_id Draft id.
 * @return int
 */
function ap_dupcheck_target_of( $draft_id ) {
	$id = (int) get_post_meta( $draft_id, AP_DUPCHECK_TARGET_META, true );
	if ( ! $id && ! ap_dupcheck_from_template( $draft_id ) ) {
		$id = ap_dupcheck_copy_source( $draft_id );
	}
	$post = $id ? get_post( $id ) : null;
	return ( $post && 'publish' === $post->post_status && $id !== (int) $draft_id ) ? $id : 0;
}

/**
 * What the editor shows about a live page.
 *
 * @param int $live_id Live page id.
 * @return array|null
 */
function ap_dupcheck_target_info( $live_id ) {
	$post = $live_id ? get_post( $live_id ) : null;
	if ( ! $post || 'publish' !== $post->post_status ) {
		return null;
	}
	$undo = get_post_meta( $post->ID, AP_DUPCHECK_UNDO_META, true );
	return array(
		'id'       => (int) $post->ID,
		'name'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'url'      => get_permalink( $post ),
		'edit_url' => current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : '',
		'kind'     => ap_dupcheck_kind( $post ),
		'updated'  => mysql2date( get_option( 'date_format' ), $post->post_modified ),
		'can_push' => current_user_can( 'edit_post', $post->ID ),
		'undo'     => is_array( $undo ) && ( $undo['modified'] ?? '' ) === $post->post_modified_gmt
			? array(
				'time' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $undo['time'] ) ) ),
			)
			: null,
	);
}

/**
 * Readable label for a top-level kit block: its first heading-like attribute,
 * else the block type's title.
 *
 * @param array $block Parsed block.
 * @return string
 */
function ap_dupcheck_block_label( array $block ) {
	$found = '';
	$walk  = static function ( $value, $key ) use ( &$walk, &$found ) {
		if ( '' !== $found ) {
			return;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$walk( $v, is_string( $k ) ? $k : $key );
			}
			return;
		}
		if ( is_string( $value ) && preg_match( AP_DUPCHECK_HEAD_KEYS, (string) $key ) ) {
			$text = trim( wp_strip_all_tags_safe( $value ) );
			if ( '' !== $text && ! preg_match( '~^(https?:|/|#)~', $text ) ) {
				$found = $text;
			}
		}
	};
	$walk( (array) ( $block['attrs'] ?? array() ), '' );
	$type  = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance()->get_registered( (string) $block['blockName'] ) : null;
	$known = array( 'ap26/cta-mini' => __( 'CTA box', 'ap-duplicate-check' ) );
	if ( isset( $known[ $block['blockName'] ] ) ) {
		$title = $known[ $block['blockName'] ];
	} elseif ( $type && ! empty( $type->title ) ) {
		$title = $type->title;
	} else {
		$title = ucfirst( str_replace( array( '-', '_' ), ' ', preg_replace( '~^[^/]+/~', '', (string) $block['blockName'] ) ) );
	}
	return '' !== $found ? $title . ': ' . $found : $title;
}

/**
 * Splits content into sections.
 *
 * @param string $content Post content.
 * @param string $prefix  Id prefix ('d' draft, 'l' live).
 * @return array[] id, label, kind (intro|heading|block|text), name, blocks, html, words
 */
function ap_dupcheck_sections( $content, $prefix ) {
	$out = array();
	$cur = null;
	$add = static function ( $section ) use ( &$out, $prefix ) {
		if ( $section && $section['blocks'] ) {
			$section['id']   = $prefix . count( $out );
			$section['html'] = serialize_blocks( $section['blocks'] );
			$out[]           = $section;
		}
	};
	foreach ( parse_blocks( (string) $content ) as $b ) {
		$name = $b['blockName'];
		if ( null === $name && '' === trim( (string) $b['innerHTML'] ) ) {
			continue; // Whitespace between blocks.
		}
		$level = (int) ( $b['attrs']['level'] ?? 2 );
		if ( 'core/heading' === $name && $level <= 2 ) {
			$add( $cur );
			$cur = array(
				'label'  => trim( wp_strip_all_tags_safe( $b['innerHTML'] ) ),
				'kind'   => 'heading',
				'name'   => $name,
				'blocks' => array( $b ),
			);
		} elseif ( null !== $name && 0 !== strpos( $name, 'core/' ) ) {
			$add( $cur );
			$add(
				array(
					'label'  => ap_dupcheck_block_label( $b ),
					'kind'   => 'block',
					'name'   => $name,
					'blocks' => array( $b ),
				)
			);
			$cur = null;
		} else {
			if ( ! $cur ) {
				$cur = array(
					'label'  => $out ? '' : __( 'Introduction', 'ap-duplicate-check' ),
					'kind'   => $out ? 'text' : 'intro',
					'name'   => '',
					'blocks' => array(),
				);
			}
			$cur['blocks'][] = $b;
		}
	}
	$add( $cur );
	foreach ( $out as $i => $s ) {
		if ( '' === $s['label'] ) {
			// Loose text after a kit block: named after it.
			$out[ $i ]['label'] = sprintf( /* translators: %s: section before. */ __( 'Text after “%s”', 'ap-duplicate-check' ), $i > 0 ? $out[ $i - 1 ]['label'] : '' );
		}
		$out[ $i ]['words'] = array_values( array_unique( ap_dupcheck_words( $out[ $i ]['label'] ) ) );
	}
	return $out;
}

/**
 * Short plain-text preview of a section.
 *
 * @param array $section Section.
 * @return string
 */
function ap_dupcheck_section_excerpt( array $section ) {
	$blocks = $section['blocks'];
	if ( 'heading' === $section['kind'] && count( $blocks ) > 1 ) {
		array_shift( $blocks ); // The heading is already the row's label.
	}
	$t    = ap_dupcheck_extract( serialize_blocks( $blocks ) );
	// Body first: sub-headings ("Overview", "Features") say little about what changed.
	$text = trim( preg_replace( '/\s+/', ' ', '' !== trim( $t['body'] ) ? $t['body'] : $t['head'] ) );
	return strlen( $text ) > 160 ? rtrim( substr( $text, 0, 157 ) ) . '…' : $text;
}

/**
 * Normalised serialisation, for "is this section already the same?".
 *
 * @param string $html Serialized blocks.
 * @return string
 */
function ap_dupcheck_norm_html( $html ) {
	return trim( preg_replace( '/\s+/', ' ', (string) $html ) );
}

/**
 * Matches draft sections to live sections and proposes what to send.
 *
 * @param string $draft_content Draft content (as edited, unsaved changes included).
 * @param string $live_content  Live page content.
 * @return array{draft:array, live:array, rows:array, same:string[]}
 */
function ap_dupcheck_plan( $draft_content, $live_content ) {
	$draft = ap_dupcheck_sections( $draft_content, 'd' );
	$live  = ap_dupcheck_sections( $live_content, 'l' );
	$match = array(); // draft id => live id.
	$taken = array();
	foreach ( $draft as $d ) {
		$best  = '';
		$score = 0.0;
		foreach ( $live as $l ) {
			if ( isset( $taken[ $l['id'] ] ) ) {
				continue;
			}
			if ( 'intro' === $d['kind'] || 'intro' === $l['kind'] ) {
				$s = ( 'intro' === $d['kind'] && 'intro' === $l['kind'] ) ? 1.0 : 0.0;
			} elseif ( 'block' === $d['kind'] || 'block' === $l['kind'] ) {
				if ( $d['name'] !== $l['name'] ) {
					continue;
				}
				$s = ap_dupcheck_overlap( $d['words'], $l['words'] );
				$s = ( ! $d['words'] && ! $l['words'] ) ? 0.5 : $s;
				$s = ap_dupcheck_norm_html( $d['html'] ) === ap_dupcheck_norm_html( $l['html'] ) ? 1.0 : $s;
			} else {
				$s = ap_dupcheck_overlap( $d['words'], $l['words'] );
			}
			if ( $s > $score ) {
				$score = $s;
				$best  = $l['id'];
			}
		}
		if ( '' !== $best && $score >= 0.5 ) {
			$match[ $d['id'] ] = $best;
			$taken[ $best ]    = true;
		}
	}

	$by_id = array();
	foreach ( array_merge( $draft, $live ) as $s ) {
		$by_id[ $s['id'] ] = $s;
	}
	$rows = array();
	$same = array();
	// Adds go after the live section the previous matched draft section maps to.
	$anchor = 'start';
	$adds   = array();
	foreach ( $draft as $d ) {
		if ( isset( $match[ $d['id'] ] ) ) {
			$anchor = $match[ $d['id'] ];
			continue;
		}
		$adds[ $anchor ][] = $d['id'];
	}
	$replaced = array_flip( $match ); // live id => draft id.
	// Sections the draft moved (a re-ranked "best of" list): offer the draft's order.
	$pos = array_flip( array_column( $live, 'id' ) );
	$seq = array();
	foreach ( $draft as $d ) {
		if ( isset( $match[ $d['id'] ] ) ) {
			$seq[] = $pos[ $match[ $d['id'] ] ];
		}
	}
	$sorted = $seq;
	sort( $sorted );
	$reordered = $seq !== $sorted;
	if ( $reordered ) {
		$rows[] = array(
			'id'      => 'o:draft',
			'type'    => 'order',
			'label'   => __( 'Use the draft’s order of sections', 'ap-duplicate-check' ),
			'excerpt' => __( 'Some sections are in a different place in this draft (for example a re-ranked list). Untick to keep the live page’s order.', 'ap-duplicate-check' ),
			'checked' => true,
		);
	}
	$emit_adds = static function ( $key ) use ( &$rows, $adds, $by_id ) {
		foreach ( $adds[ $key ] ?? array() as $did ) {
			$rows[] = array(
				'id'      => 'a:' . $did,
				'type'    => 'add',
				'label'   => $by_id[ $did ]['label'],
				'excerpt' => ap_dupcheck_section_excerpt( $by_id[ $did ] ),
				'checked' => true,
			);
		}
	};
	$emit_adds( 'start' );
	foreach ( $live as $l ) {
		if ( isset( $replaced[ $l['id'] ] ) ) {
			$d = $by_id[ $replaced[ $l['id'] ] ];
			if ( ap_dupcheck_norm_html( $d['html'] ) === ap_dupcheck_norm_html( $l['html'] ) ) {
				$same[] = $l['label'];
			} else {
				$rows[] = array(
					'id'         => 'r:' . $d['id'],
					'type'       => 'replace',
					'label'      => $l['label'],
					'new_label'  => $d['label'] !== $l['label'] ? $d['label'] : '',
					'excerpt'    => ap_dupcheck_section_excerpt( $d ),
					'live'       => ap_dupcheck_section_excerpt( $l ),
					'checked'    => true,
				);
			}
		} else {
			$rows[] = array(
				'id'      => 'x:' . $l['id'],
				'type'    => 'remove',
				'label'   => $l['label'],
				'excerpt' => ap_dupcheck_section_excerpt( $l ),
				'checked' => false,
			);
		}
		$emit_adds( $l['id'] );
	}
	return array(
		'draft'     => $draft,
		'live'      => $live,
		'match'     => $match,
		'adds'      => $adds,
		'rows'      => $rows,
		'same'      => $same,
		'reordered' => $reordered,
	);
}

/**
 * Share of the shorter word list found in the other.
 *
 * @param string[] $a Words.
 * @param string[] $b Words.
 * @return float
 */
function ap_dupcheck_overlap( array $a, array $b ) {
	if ( ! $a || ! $b ) {
		return 0.0;
	}
	return count( array_intersect( $a, $b ) ) / min( count( $a ), count( $b ) );
}

/**
 * Builds the live page's new content from a plan and the ticked rows.
 *
 * @param array    $plan     ap_dupcheck_plan() result.
 * @param string[] $selected Ticked row ids (r:dN replace, a:dN add, x:lN remove).
 * @return string
 */
function ap_dupcheck_merge( array $plan, array $selected ) {
	$on    = array_flip( $selected );
	$draft = array();
	foreach ( $plan['draft'] as $d ) {
		$draft[ $d['id'] ] = $d;
	}
	$replace = array();
	foreach ( $plan['match'] as $did => $lid ) {
		if ( isset( $on[ 'r:' . $did ] ) ) {
			$replace[ $lid ] = $did;
		}
	}
	if ( ! empty( $plan['reordered'] ) && isset( $on['o:draft'] ) ) {
		return ap_dupcheck_merge_in_draft_order( $plan, $on, $draft, $replace );
	}
	$blocks = array();
	$adds   = static function ( $key ) use ( &$blocks, $plan, $on, $draft ) {
		foreach ( $plan['adds'][ $key ] ?? array() as $did ) {
			if ( isset( $on[ 'a:' . $did ] ) ) {
				$blocks = array_merge( $blocks, $draft[ $did ]['blocks'] );
			}
		}
	};
	$adds( 'start' );
	foreach ( $plan['live'] as $l ) {
		if ( isset( $replace[ $l['id'] ] ) ) {
			$blocks = array_merge( $blocks, $draft[ $replace[ $l['id'] ] ]['blocks'] );
		} elseif ( ! isset( $on[ 'x:' . $l['id'] ] ) ) {
			$blocks = array_merge( $blocks, $l['blocks'] );
		}
		$adds( $l['id'] );
	}
	// One blank line between top-level blocks, as the editor saves them.
	return implode( "\n\n", array_map( 'serialize_block', $blocks ) );
}

/**
 * Merge that follows the draft's order: every matched section where the draft
 * puts it (its draft text if ticked, else the live text), ticked new sections,
 * and live-only sections that stay, each after the live section it followed.
 *
 * @param array $plan    Plan.
 * @param array $on      Ticked row ids (flipped).
 * @param array $draft   Draft sections by id.
 * @param array $replace live id => draft id, ticked replacements.
 * @return string
 */
function ap_dupcheck_merge_in_draft_order( array $plan, array $on, array $draft, array $replace ) {
	$live    = array();
	$anchors = array();
	$prev    = 'start';
	$matched = array_flip( $plan['match'] ); // live id => draft id.
	foreach ( $plan['live'] as $l ) {
		$live[ $l['id'] ] = $l;
		if ( isset( $matched[ $l['id'] ] ) ) {
			$prev = $l['id'];
		} elseif ( ! isset( $on[ 'x:' . $l['id'] ] ) ) {
			$anchors[ $prev ][] = $l['id'];
		}
	}
	$blocks = array();
	$emit   = static function ( $key ) use ( &$blocks, $anchors, $live ) {
		foreach ( $anchors[ $key ] ?? array() as $lid ) {
			$blocks = array_merge( $blocks, $live[ $lid ]['blocks'] );
		}
	};
	$emit( 'start' );
	foreach ( $plan['draft'] as $d ) {
		if ( isset( $plan['match'][ $d['id'] ] ) ) {
			$lid    = $plan['match'][ $d['id'] ];
			$blocks = array_merge( $blocks, isset( $replace[ $lid ] ) ? $d['blocks'] : $live[ $lid ]['blocks'] );
			$emit( $lid );
		} elseif ( isset( $on[ 'a:' . $d['id'] ] ) ) {
			$blocks = array_merge( $blocks, $d['blocks'] );
		}
	}
	return implode( "\n\n", array_map( 'serialize_block', $blocks ) );
}

/**
 * Checks a request may touch both posts.
 *
 * @param int $draft_id Draft id (0 = none).
 * @param int $live_id  Live page id.
 * @return true|WP_Error
 */
function ap_dupcheck_push_guard( $draft_id, $live_id ) {
	$live = get_post( $live_id );
	if ( ! $live || 'publish' !== $live->post_status || ! in_array( $live->post_type, ap_dupcheck_types(), true ) ) {
		return new WP_Error( 'ap_dupcheck_no_live', __( 'That live page was not found.', 'ap-duplicate-check' ), array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_post', $live_id ) ) {
		return new WP_Error( 'ap_dupcheck_forbidden', __( 'You cannot edit that live page.', 'ap-duplicate-check' ), array( 'status' => 403 ) );
	}
	if ( $draft_id ) {
		if ( (int) $draft_id === (int) $live_id || ! get_post( $draft_id ) || ! current_user_can( 'edit_post', $draft_id ) ) {
			return new WP_Error( 'ap_dupcheck_forbidden', __( 'You cannot use this draft.', 'ap-duplicate-check' ), array( 'status' => 403 ) );
		}
	}
	return true;
}

/**
 * Writes (or refreshes) the current user's autosave of the live page, which is
 * what WordPress shows in a preview. The live page itself does not change.
 *
 * @param WP_Post $live    Live page.
 * @param string  $title   Title to preview.
 * @param string  $content Content to preview.
 * @return int|WP_Error Autosave id.
 */
function ap_dupcheck_put_autosave( WP_Post $live, $title, $content ) {
	$data = _wp_post_revision_data(
		array(
			'ID'           => $live->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $live->post_excerpt,
		),
		true
	);
	$old = wp_get_post_autosave( $live->ID, get_current_user_id() );
	if ( $old ) {
		$data['ID'] = $old->ID;
		return wp_update_post( wp_slash( $data ), true );
	}
	return wp_insert_post( wp_slash( $data ), true );
}

/**
 * The preview address: the live page itself with ?ap_dupcheck_preview=<id>.
 *
 * @param WP_Post $live Live page.
 * @return string
 */
function ap_dupcheck_preview_url( WP_Post $live ) {
	return add_query_arg( 'ap_dupcheck_preview', (int) $live->ID, get_permalink( $live ) );
}

/*
 * Serve the preview: for a signed-in editor of the page only, swap the queried
 * post for a copy holding their preview autosave (the site's article template
 * reads the queried object, so WordPress's own preview_id route shows the old
 * text). Nothing is stored or cached; visitors never see it.
 */
add_action(
	'wp',
	static function () {
		global $wp_query;
		if ( empty( $_GET['ap_dupcheck_preview'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id = absint( $_GET['ap_dupcheck_preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $wp_query->is_singular() || 1 !== count( (array) $wp_query->posts ) || (int) $wp_query->posts[0]->ID !== $id || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		$auto = wp_get_post_autosave( $id, get_current_user_id() );
		if ( ! $auto ) {
			return;
		}
		$clone               = new WP_Post( (object) get_object_vars( $wp_query->posts[0] ) );
		$clone->post_content = $auto->post_content;
		$clone->post_title   = $auto->post_title;
		$clone->filter       = 'raw';
		$wp_query->posts[0]          = $clone;
		$wp_query->post              = $clone;
		$wp_query->queried_object    = $clone;
		$wp_query->queried_object_id = $id;
		$GLOBALS['post']             = $clone;
		nocache_headers();
		$title = $auto->post_title;
		add_filter(
			'the_title',
			static function ( $t, $pid = 0 ) use ( $id, $title ) {
				return (int) $pid === $id ? $title : $t;
			},
			1,
			2
		);
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action(
			'wp_footer',
			static function () {
				echo '<div style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:99999;background:#1d2327;color:#fff;font:600 14px/1.4 system-ui,sans-serif;padding:10px 18px;border-radius:999px;box-shadow:0 6px 20px rgba(0,0,0,.3)">' . esc_html__( 'Preview of your changes — not live yet', 'ap-duplicate-check' ) . '</div>';
			}
		);
	},
	1
);

/**
 * Removes the current user's preview autosave of the live page.
 *
 * @param int $live_id Live page id.
 */
function ap_dupcheck_drop_autosave( $live_id ) {
	$old = wp_get_post_autosave( $live_id, get_current_user_id() );
	if ( $old ) {
		wp_delete_post_revision( $old->ID );
	}
}

/**
 * Pushes the ticked parts of a draft to its live page.
 *
 * @param int     $draft_id Draft id.
 * @param WP_Post $live     Live page.
 * @param array   $req      title, content, selected[], live_modified, with_title, seo{}, image_id, trash_draft.
 * @return array|WP_Error
 */
function ap_dupcheck_push( $draft_id, WP_Post $live, array $req ) {
	if ( ( $req['live_modified'] ?? '' ) !== $live->post_modified_gmt ) {
		return new WP_Error( 'ap_dupcheck_live_changed', __( 'The live page changed since you opened this window. Close it and open it again.', 'ap-duplicate-check' ), array( 'status' => 409 ) );
	}
	$plan = ap_dupcheck_plan( (string) $req['content'], $live->post_content );
	// Only ticked rows that exist in this plan count; with none, the body stays byte-for-byte as it is.
	$ticked  = array_values( array_intersect( (array) $req['selected'], array_column( $plan['rows'], 'id' ) ) );
	$content = $ticked ? ap_dupcheck_merge( $plan, $ticked ) : $live->post_content;
	$title   = ! empty( $req['with_title'] ) && '' !== trim( (string) $req['title'] ) ? (string) $req['title'] : $live->post_title;

	$snapshot = array(
		'content' => $live->post_content,
		'title'   => $live->post_title,
		'meta'    => array(),
		'thumb'   => (int) get_post_thumbnail_id( $live ),
		'time'    => time(),
		'user'    => get_current_user_id(),
		'draft'   => (int) $draft_id,
	);
	foreach ( AP_DUPCHECK_SEO_KEYS as $key ) {
		$snapshot['meta'][ $key ] = get_post_meta( $live->ID, $key, true );
	}

	$changed = $content !== $live->post_content || $title !== $live->post_title;
	if ( $changed ) {
		$res = wp_update_post(
			wp_slash(
				array(
					'ID'           => $live->ID,
					'post_content' => $content,
					'post_title'   => $title,
				)
			),
			true
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
	}
	$seo = is_array( $req['seo'] ?? null ) ? $req['seo'] : array();
	foreach ( AP_DUPCHECK_SEO_KEYS as $key ) {
		if ( isset( $seo[ $key ] ) && is_string( $seo[ $key ] ) ) {
			update_post_meta( $live->ID, $key, wp_slash( sanitize_text_field( $seo[ $key ] ) ) );
			$changed = true;
		}
	}
	$image = (int) ( $req['image_id'] ?? 0 );
	if ( $image > 0 && wp_attachment_is_image( $image ) ) {
		set_post_thumbnail( $live->ID, $image );
		$changed = true;
	}
	if ( ! $changed ) {
		return new WP_Error( 'ap_dupcheck_nothing', __( 'Nothing to send: every ticked part already matches the live page.', 'ap-duplicate-check' ), array( 'status' => 400 ) );
	}

	clean_post_cache( $live->ID );
	$after                = get_post( $live->ID );
	$snapshot['modified'] = $after->post_modified_gmt;
	update_post_meta( $live->ID, AP_DUPCHECK_UNDO_META, wp_slash( $snapshot ) );
	ap_dupcheck_drop_autosave( $live->ID );
	if ( $draft_id ) {
		update_post_meta( $draft_id, AP_DUPCHECK_TARGET_META, $live->ID );
		if ( ! empty( $req['trash_draft'] ) ) {
			wp_trash_post( $draft_id );
		}
	}
	return array(
		'ok'     => true,
		'target' => ap_dupcheck_target_info( $live->ID ),
	);
}

/**
 * Puts the live page back as it was before the last push, if nobody changed it since.
 *
 * @param WP_Post $live Live page.
 * @return array|WP_Error
 */
function ap_dupcheck_undo( WP_Post $live ) {
	$snap = get_post_meta( $live->ID, AP_DUPCHECK_UNDO_META, true );
	if ( ! is_array( $snap ) ) {
		return new WP_Error( 'ap_dupcheck_no_undo', __( 'There is no push to undo.', 'ap-duplicate-check' ), array( 'status' => 404 ) );
	}
	if ( ( $snap['modified'] ?? '' ) !== $live->post_modified_gmt ) {
		return new WP_Error( 'ap_dupcheck_undo_stale', __( 'The live page was edited after the push, so it cannot be undone here. Use Revisions in the page editor.', 'ap-duplicate-check' ), array( 'status' => 409 ) );
	}
	$res = wp_update_post(
		wp_slash(
			array(
				'ID'           => $live->ID,
				'post_content' => (string) $snap['content'],
				'post_title'   => (string) $snap['title'],
			)
		),
		true
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	foreach ( (array) $snap['meta'] as $key => $value ) {
		if ( in_array( $key, AP_DUPCHECK_SEO_KEYS, true ) ) {
			if ( '' === $value ) {
				delete_post_meta( $live->ID, $key );
			} else {
				update_post_meta( $live->ID, $key, wp_slash( $value ) );
			}
		}
	}
	if ( (int) $snap['thumb'] > 0 ) {
		set_post_thumbnail( $live->ID, (int) $snap['thumb'] );
	} else {
		delete_post_thumbnail( $live->ID );
	}
	delete_post_meta( $live->ID, AP_DUPCHECK_UNDO_META );
	if ( ! empty( $snap['draft'] ) && 'trash' === get_post_status( (int) $snap['draft'] ) ) {
		wp_untrash_post( (int) $snap['draft'] );
	}
	return array(
		'ok'     => true,
		'target' => ap_dupcheck_target_info( $live->ID ),
	);
}

add_action(
	'rest_api_init',
	static function () {
		$ids = array(
			'draft_id' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'live_id'  => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
		$guard = static function ( WP_REST_Request $req ) {
			$ok = ap_dupcheck_push_guard( (int) $req['draft_id'], (int) $req['live_id'] );
			return is_wp_error( $ok ) ? $ok : true;
		};
		$body = static function ( WP_REST_Request $req ) {
			return array(
				'title'   => (string) $req['title'],
				'content' => substr( (string) $req['content'], 0, 2000000 ),
			);
		};

		// Link a draft to the live page it updates.
		register_rest_route(
			'ap-dupcheck/v1',
			'/link',
			array(
				'methods'             => 'POST',
				'permission_callback' => $guard,
				'args'                => $ids,
				'callback'            => static function ( WP_REST_Request $req ) {
					$draft = (int) $req['draft_id'];
					if ( ! $draft ) {
						return new WP_Error( 'ap_dupcheck_no_draft', __( 'Save the draft first.', 'ap-duplicate-check' ), array( 'status' => 400 ) );
					}
					update_post_meta( $draft, AP_DUPCHECK_TARGET_META, (int) $req['live_id'] );
					return rest_ensure_response( array( 'target' => ap_dupcheck_target_info( (int) $req['live_id'] ) ) );
				},
			)
		);
		register_rest_route(
			'ap-dupcheck/v1',
			'/unlink',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function ( WP_REST_Request $req ) {
					return current_user_can( 'edit_post', (int) $req['draft_id'] );
				},
				'args'                => array(
					'draft_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					// An explicit 0 also detaches a Duplicate copy from its original.
					update_post_meta( (int) $req['draft_id'], AP_DUPCHECK_TARGET_META, -1 );
					return rest_ensure_response( array( 'target' => null ) );
				},
			)
		);

		// What would change.
		register_rest_route(
			'ap-dupcheck/v1',
			'/plan',
			array(
				'methods'             => 'POST',
				'permission_callback' => $guard,
				'args'                => $ids,
				'callback'            => static function ( WP_REST_Request $req ) use ( $body ) {
					$live = get_post( (int) $req['live_id'] );
					$b    = $body( $req );
					$plan = ap_dupcheck_plan( $b['content'], $live->post_content );
					$seo  = array();
					foreach ( AP_DUPCHECK_SEO_KEYS as $key ) {
						$seo[ $key ] = (string) get_post_meta( $live->ID, $key, true );
					}
					return rest_ensure_response(
						array(
							'target'        => ap_dupcheck_target_info( $live->ID ),
							'live_modified' => $live->post_modified_gmt,
							'live_title'    => $live->post_title,
							'live_seo'      => $seo,
							'live_image'    => (int) get_post_thumbnail_id( $live ),
							'rows'          => $plan['rows'],
							'same'          => $plan['same'],
						)
					);
				},
			)
		);

		// Preview the live page with the ticked parts (an autosave; nothing visible changes).
		register_rest_route(
			'ap-dupcheck/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => $guard,
				'args'                => $ids,
				'callback'            => static function ( WP_REST_Request $req ) use ( $body ) {
					$live = get_post( (int) $req['live_id'] );
					if ( $req['discard'] ) {
						ap_dupcheck_drop_autosave( $live->ID );
						return rest_ensure_response( array( 'ok' => true ) );
					}
					$b       = $body( $req );
					$content = ap_dupcheck_merge( ap_dupcheck_plan( $b['content'], $live->post_content ), (array) $req['selected'] );
					$title   = $req['with_title'] && '' !== trim( $b['title'] ) ? $b['title'] : $live->post_title;
					$saved   = ap_dupcheck_put_autosave( $live, $title, $content );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
					return rest_ensure_response(
						array(
							'url' => ap_dupcheck_preview_url( $live ),
						)
					);
				},
			)
		);

		register_rest_route(
			'ap-dupcheck/v1',
			'/push',
			array(
				'methods'             => 'POST',
				'permission_callback' => $guard,
				'args'                => $ids,
				'callback'            => static function ( WP_REST_Request $req ) use ( $body ) {
					$b   = $body( $req );
					$out = ap_dupcheck_push(
						(int) $req['draft_id'],
						get_post( (int) $req['live_id'] ),
						array(
							'title'         => $b['title'],
							'content'       => $b['content'],
							'selected'      => array_map( 'strval', (array) $req['selected'] ),
							'live_modified' => (string) $req['live_modified'],
							'with_title'    => (bool) $req['with_title'],
							'seo'           => $req['seo'],
							'image_id'      => (int) $req['image_id'],
							'trash_draft'   => (bool) $req['trash_draft'],
						)
					);
					return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
				},
			)
		);

		register_rest_route(
			'ap-dupcheck/v1',
			'/undo',
			array(
				'methods'             => 'POST',
				'permission_callback' => $guard,
				'args'                => $ids,
				'callback'            => static function ( WP_REST_Request $req ) {
					$out = ap_dupcheck_undo( get_post( (int) $req['live_id'] ) );
					return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
				},
			)
		);
	}
);
