<?php
/**
 * Instant search index (1.3.0).
 *
 * GET /wp-json/ap-content/v1/search-index → every published resource and
 * press release as one compact JSON document: title, path, type, categories
 * (topics + industries), a short excerpt, a thumbnail and the date. The kit's
 * resource-search component fetches it the first time a visitor focuses,
 * hovers or touches the field and searches it in the browser, so results
 * appear while typing with no further requests.
 *
 * Cost: built once and kept in a transient; rebuilt on the next request after
 * a resource, press release or term changes. Pages link to it with
 * ?v=<version> (the version changes on every rebuild), so browsers and the WP
 * Engine / Cloudflare edge may keep each version for a day without ever
 * serving a stale list. Images go through ap_media_local_url() when that
 * plugin is present (page HTML is rewritten for it automatically; REST is not).
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The current index version (changes whenever the index is invalidated). */
function ap_content_search_version() {
	$v = (string) get_option( 'ap_content_search_ver', '' );
	if ( '' === $v ) {
		$v = substr( md5( AP_CONTENT_VERSION . '|' . microtime() ), 0, 10 );
		update_option( 'ap_content_search_ver', $v, true );
	}
	return $v;
}

/** URL of the index for the current version. */
function ap_content_search_index_url() {
	return add_query_arg( 'v', rawurlencode( ap_content_search_version() ), rest_url( 'ap-content/v1/search-index' ) );
}

/**
 * Marks the index stale. Cheap: the new version is written once, at the end
 * of the request, however many posts or terms changed in it.
 */
function ap_content_search_bump() {
	static $queued = false;
	delete_transient( 'ap_content_search_index' );
	delete_transient( 'ap_content_listing_counts' );
	if ( $queued ) {
		return;
	}
	$queued = true;
	add_action(
		'shutdown',
		function () {
			update_option( 'ap_content_search_ver', substr( md5( AP_CONTENT_VERSION . '|' . microtime() . '|' . wp_rand() ), 0, 10 ), true );
		}
	);
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'resource', 'newsroom' ), true ) && ! wp_is_post_revision( $post_id ) ) {
			ap_content_search_bump();
		}
	},
	10,
	2
);
foreach ( array( 'deleted_post', 'trashed_post', 'untrashed_post' ) as $ap_content_hook ) {
	add_action(
		$ap_content_hook,
		function ( $post_id ) {
			if ( in_array( get_post_type( $post_id ), array( 'resource', 'newsroom' ), true ) ) {
				ap_content_search_bump();
			}
		}
	);
}
unset( $ap_content_hook );
add_action(
	'set_object_terms',
	function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'category', 'resource-types', 'resource-industry' ), true ) ) {
			ap_content_search_bump();
		}
	},
	10,
	4
);
foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $ap_content_hook ) {
	add_action(
		$ap_content_hook,
		function ( $term_id, $tt_id, $taxonomy ) {
			if ( in_array( $taxonomy, array( 'category', 'resource-types', 'resource-industry' ), true ) ) {
				ap_content_search_bump();
			}
		},
		10,
		3
	);
}
unset( $ap_content_hook );
add_action(
	'updated_post_meta',
	function ( $meta_id, $post_id, $key ) {
		if ( in_array( $key, array( '_thumbnail_id', '_ap_featured_image_url' ), true ) && in_array( get_post_type( $post_id ), array( 'resource', 'newsroom' ), true ) ) {
			ap_content_search_bump();
		}
	},
	10,
	3
);

/**
 * Maps a www.assetpanda.com upload to the dev site's local copy when the
 * media plugin is present.
 *
 * @param string $url URL.
 * @return string
 */
function ap_content_local_media( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}
	return function_exists( 'ap_media_local_url' ) ? (string) ap_media_local_url( $url ) : $url;
}

/**
 * A small image for a search row / card: the attachment's medium (or large)
 * size, else the external featured image.
 *
 * @param int    $post_id Post.
 * @param string $size    Attachment size.
 * @return array{url: string, w: int, h: int, alt: string}
 */
function ap_content_post_image( $post_id, $size = 'medium' ) {
	$out   = array(
		'url' => '',
		'w'   => 0,
		'h'   => 0,
		'alt' => '',
	);
	$thumb = (int) get_post_meta( $post_id, '_thumbnail_id', true );
	if ( $thumb ) {
		$src = wp_get_attachment_image_src( $thumb, $size );
		if ( $src ) {
			$out['url'] = (string) $src[0];
			$out['w']   = (int) $src[1];
			$out['h']   = (int) $src[2];
			$out['alt'] = trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
			return $out;
		}
	}
	if ( function_exists( 'ap_content_external_image' ) ) {
		$out['url'] = ap_content_external_image( $post_id );
		$out['alt'] = trim( (string) get_post_meta( $post_id, '_ap_featured_image_alt', true ) );
	}
	return $out;
}

/**
 * Builds the index document.
 *
 * @return array
 */
function ap_content_search_build() {
	$types = array( 'press' => (string) apply_filters( 'ap_content_press_label', 'Press Release' ) );
	$terms = get_terms(
		array(
			'taxonomy'   => 'resource-types',
			'hide_empty' => false,
		)
	);
	foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
		$types[ $t->slug ] = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
	}
	$cats    = array();
	$default = (int) get_option( 'default_category' );
	$items   = array();
	$q       = new WP_Query(
		array(
			'post_type'              => array( 'resource', 'newsroom' ),
			'post_status'            => 'publish',
			'posts_per_page'         => 2000,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => true,
			'update_post_meta_cache' => true,
			'suppress_filters'       => true,
		)
	);
	$home = untrailingslashit( home_url() );
	foreach ( $q->posts as $post ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			continue;
		}
		$slugs = array();
		if ( 'resource' === $post->post_type ) {
			$type = ap_content_primary_type( $post );
			$y    = $type ? $type->slug : '';
			foreach ( (array) get_the_terms( $post, 'category' ) as $c ) {
				if ( $c instanceof WP_Term && (int) $c->term_id !== $default ) {
					$slugs[]          = $c->slug;
					$cats[ $c->slug ] = html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' );
				}
			}
			foreach ( ap_content_post_industries( $post->ID ) as $c ) {
				if ( ! in_array( $c->slug, $slugs, true ) ) {
					$slugs[] = $c->slug;
				}
				$cats[ $c->slug ] = html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' );
			}
		} else {
			$y = 'press';
		}
		$excerpt = function_exists( 'ap_content_card_text' ) ? ap_content_card_text( $post ) : '';
		$excerpt = wp_html_excerpt( $excerpt, 150, '…' );
		$img     = ap_content_post_image( $post->ID, 'medium' );
		$path    = 0 === strpos( $url, $home ) ? substr( $url, strlen( $home ) ) : $url;
		$item    = array(
			't' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
			'u' => $path,
			'y' => $y,
			'd' => mysql2date( 'Y-m-d', $post->post_date ),
		);
		if ( $slugs ) {
			$item['c'] = $slugs;
		}
		if ( '' !== $excerpt ) {
			$item['x'] = $excerpt;
		}
		if ( '' !== $img['url'] ) {
			$item['i'] = ap_content_local_media( $img['url'] );
		}
		$items[] = $item;
	}
	return array(
		'v'     => ap_content_search_version(),
		'n'     => count( $items ),
		'types' => $types,
		'cats'  => $cats,
		'items' => $items,
	);
}

/** The cached index (built when missing). */
function ap_content_search_index() {
	$cached = get_transient( 'ap_content_search_index' );
	if ( is_array( $cached ) && isset( $cached['v'] ) && ap_content_search_version() === $cached['v'] ) {
		return $cached;
	}
	$doc = ap_content_search_build();
	set_transient( 'ap_content_search_index', $doc, DAY_IN_SECONDS );
	return $doc;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap-content/v1',
			'/search-index',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'v' => array( 'type' => 'string' ),
				),
				'callback'            => ap_content_rest_safe(
					function ( WP_REST_Request $request ) {
						$doc      = ap_content_search_index();
						$etag     = '"' . $doc['v'] . '"';
						$response = rest_ensure_response( $doc );
						$response->header( 'ETag', $etag );
						// A versioned URL never changes: cache it hard. The bare URL is short-lived.
						$versioned = (string) $request['v'] === (string) $doc['v'];
						$response->header( 'Cache-Control', $versioned ? 'public, max-age=86400, s-maxage=86400, stale-while-revalidate=604800' : 'public, max-age=300, s-maxage=300' );
						$match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						if ( '' !== $match && $match === $etag ) {
							$response->set_status( 304 );
							$response->set_data( null );
						}
						return $response;
					}
				),
			)
		);
	}
);

/* The index is public, read-only data: let browsers fetch it with credentials omitted (no cookie → cacheable). */
add_filter(
	'rest_post_dispatch',
	function ( $result, $server, $request ) {
		if ( $result instanceof WP_REST_Response && '/ap-content/v1/search-index' === $request->get_route() ) {
			$result->header( 'X-Robots-Tag', 'noindex' );
			$result->header( 'Vary', 'Accept-Encoding' );
		}
		return $result;
	},
	10,
	3
);
