<?php
/**
 * REST: POST /ap-dupcheck/v1/check — the editor sends the draft's title,
 * content, address and keyphrase; the reply lists live pages on the same or a
 * similar topic.
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds live pages that cover the same topic as a draft.
 *
 * @param array $draft post_id, title, content, slug, keyphrase, post_type.
 * @param int   $limit Most matches returned.
 * @return array{matches:array, checked:int, pending:int}
 */
function ap_dupcheck_find( array $draft, $limit = 5 ) {
	$post_id = (int) ( $draft['post_id'] ?? 0 );
	$corpus  = ap_dupcheck_corpus();
	$docs    = $corpus['docs'];
	unset( $docs[ $post_id ] );
	$out = array(
		'matches' => array(),
		'checked' => count( $docs ),
		'pending' => $corpus['pending'],
	);
	if ( count( $docs ) < 2 ) {
		return $out;
	}

	$df  = ap_dupcheck_df( $docs );
	$tdf = ap_dupcheck_title_df( $docs );
	$n   = count( $docs );
	foreach ( $docs as $id => $d ) {
		$docs[ $id ]['vec'] = ap_dupcheck_vector( $d['tf'], $df, $n );
	}
	$me        = ap_dupcheck_terms( (string) ( $draft['title'] ?? '' ), (string) ( $draft['content'] ?? '' ) );
	$me['vec'] = ap_dupcheck_vector( $me['tf'], $df, $n );
	if ( ! $me['vec'] ) {
		return $out;
	}
	$slug = ap_dupcheck_slug_base( (string) ( $draft['slug'] ?? '' ) );
	$kw   = ap_dupcheck_keyphrase( (string) ( $draft['keyphrase'] ?? '' ) );

	// Text the writer started from rather than wrote: the page a Duplicate copy
	// came from, and a starter template ("Start from: Blog post" holds a real
	// published post's blocks) while the draft still reads like it. Pages the
	// draft only resembles through that text are not flagged.
	// The live page this draft is linked to (it will be pushed there, not
	// published beside it) is treated the same way.
	// A draft started from a template keeps its model page as an origin only:
	// a title naming that page's topic still warns.
	$copy    = $post_id ? ap_dupcheck_copy_source( $post_id ) : 0;
	$source  = ( $copy && ! ap_dupcheck_from_template( $post_id ) ) ? $copy : 0;
	$linked  = $post_id ? ap_dupcheck_target_of( $post_id ) : 0;
	$origins = array();
	foreach ( array_unique( array_filter( array( $copy, $linked ) ) ) as $origin ) {
		if ( isset( $docs[ $origin ] ) ) {
			$origins[] = $docs[ $origin ]['vec'];
		}
	}
	foreach ( ap_dupcheck_starter_vectors( (string) ( $draft['post_type'] ?? '' ), $df, $n ) as $pv ) {
		if ( ap_dupcheck_cosine( $me['vec'], $pv ) >= AP_DUPCHECK_STARTER_COS ) {
			$origins[] = $pv;
		}
	}

	$found = array();
	foreach ( $docs as $id => $d ) {
		if ( $id === $source || $id === $linked ) {
			continue;
		}
		$c         = ap_dupcheck_compare( $me, $d, $df, $tdf, $n );
		$same_slug = '' !== $slug && strlen( $slug ) > 3 && ! ctype_digit( $slug ) && ap_dupcheck_slug_base( $d['slug'] ) === $slug;
		$same_kw   = '' !== $kw && '' !== $d['kw'] && ap_dupcheck_keyphrase( $d['kw'] ) === $kw;
		$verdict   = ap_dupcheck_verdict( $c['cos'], $c['title'], $c['conflict'], $c['short'], $same_slug, $same_kw, $c['phrase'], $c['narrower'] );
		if ( '' === $verdict ) {
			continue;
		}
		// A title naming the same topic always counts, even for a draft started
		// from a template or a copy (only the copy's own original is skipped).
		$title_says_same = $c['phrase'] >= AP_DUPCHECK_PHRASE_SAME || ( $c['title'] >= AP_DUPCHECK_SAME_TITLE && ! $c['conflict'] );
		if ( ! $same_slug && ! $same_kw && ! $title_says_same ) {
			foreach ( $origins as $ov ) {
				if ( ap_dupcheck_cosine( $ov, $d['vec'] ) >= $c['cos'] - 0.05 ) {
					continue 2;
				}
			}
		}
		$why = array();
		if ( $same_slug ) {
			$why[] = 'address';
		}
		if ( $same_kw ) {
			$why[] = 'keyphrase';
		}
		if ( $c['cos'] >= AP_DUPCHECK_SIMILAR_COS && ! $c['short'] ) {
			$why[] = 'content';
		}
		if ( $c['title'] >= AP_DUPCHECK_SAME_TITLE || $c['phrase'] >= 3 ) {
			$why[] = 'title';
		}
		$found[] = array(
			'id'      => $id,
			'verdict' => $verdict,
			'score'   => round( $c['cos'], 3 ),
			'title'   => round( $c['title'], 2 ),
			'why'     => $why ? $why : array( 'topic' ),
		);
	}
	usort(
		$found,
		static function ( $a, $b ) {
			return ( 'same' === $b['verdict'] ) <=> ( 'same' === $a['verdict'] ) ?: $b['score'] <=> $a['score'];
		}
	);
	foreach ( array_slice( $found, 0, $limit ) as $f ) {
		$post = get_post( $f['id'] );
		if ( ! $post ) {
			continue;
		}
		$out['matches'][] = array_merge(
			$f,
			array(
				'name'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'url'      => get_permalink( $post ),
				'edit_url' => current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : '',
				'kind'     => ap_dupcheck_kind( $post ),
				'updated'  => mysql2date( get_option( 'date_format' ), $post->post_modified ),
			)
		);
	}
	return $out;
}

/**
 * Vectors of the starter patterns WordPress offers for a new post of this type
 * (patterns for core/post-content: the AP Template Library's "Start from: …").
 *
 * @param string $post_type Post type ('' = all).
 * @param array  $df        Document frequencies.
 * @param int    $n         Number of documents.
 * @return array[]
 */
function ap_dupcheck_starter_vectors( $post_type, array $df, $n ) {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return array();
	}
	$out = array();
	foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $p ) {
		if ( ! in_array( 'core/post-content', (array) ( $p['blockTypes'] ?? array() ), true ) ) {
			continue;
		}
		$types = (array) ( $p['postTypes'] ?? array() );
		if ( $types && '' !== $post_type && ! in_array( $post_type, $types, true ) ) {
			continue;
		}
		$t = ap_dupcheck_terms( '', (string) ( $p['content'] ?? '' ) );
		$v = ap_dupcheck_vector( $t['tf'], $df, $n );
		if ( $v ) {
			$out[] = $v;
		}
	}
	return $out;
}

/**
 * "Blog", "Compare", "Page", "Press Release"… for the notice.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function ap_dupcheck_kind( WP_Post $post ) {
	foreach ( array( 'resource-types', 'resource-type', 'resource_type' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$terms = get_the_terms( $post, $tax );
			if ( is_array( $terms ) && $terms ) {
				return html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
			}
		}
	}
	$obj = get_post_type_object( $post->post_type );
	return $obj ? $obj->labels->singular_name : $post->post_type;
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'ap-dupcheck/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'permission_callback' => static function ( WP_REST_Request $req ) {
					$id = (int) $req['post_id'];
					return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'title'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'content'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'slug'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'keyphrase' => array(
						'type'    => 'string',
						'default' => '',
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					return rest_ensure_response(
						ap_dupcheck_find(
							array(
								'post_id'   => (int) $req['post_id'],
								'title'     => (string) $req['title'],
								'content'   => substr( (string) $req['content'], 0, 600000 ),
								'slug'      => sanitize_title( (string) $req['slug'] ),
								'keyphrase' => (string) $req['keyphrase'],
								'post_type' => sanitize_key( (string) $req['post_type'] ),
							)
						)
					);
				},
			)
		);
	}
);
