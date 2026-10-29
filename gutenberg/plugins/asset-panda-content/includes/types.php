<?php
/**
 * Content types, terms, permalinks and routing.
 *
 * URLs reproduced from www.assetpanda.com (checked 2026-10-16 with curl):
 *   single resource   /resource-center/<resource-types term>/<slug>/
 *   resource listing  /resource-center/<term>/ (+ /page/N/)  live: Pages; here: the term archive
 *   all resources     /resource-center/          (+ /page/N/) live: Page 22; here: the post type archive
 *   press release     /pressroom/<slug>/
 *   pressroom         /pressroom/                (+ /page/N/) live: Page 97; here: the post type archive
 *   /resource-center/compare/ 301 → /compare/ (as live), when a page at /compare/ exists.
 * Old taxonomy addresses (/resource-category/<term>/, /resource-types/<term>/, /resource/)
 * 301 to the listings, as live does for the last two.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The six Resource Types: slug → term name (live's "Resource Type" labels) and
 * listing heading (the H1 of live's hub page).
 *
 * @return array<string, array{name: string, heading: string}>
 */
function ap_content_type_defaults() {
	return array(
		'blog'            => array( 'name' => 'Blog', 'heading' => 'Blog' ),
		'case-studies'    => array( 'name' => 'Case Study', 'heading' => 'Case Studies' ),
		'success-stories' => array( 'name' => 'Success Story', 'heading' => 'Success Stories' ),
		'white-papers'    => array( 'name' => 'White Paper', 'heading' => 'White Papers' ),
		'compare'         => array( 'name' => 'Compare Asset Panda', 'heading' => 'Compare Asset Panda' ),
		'webinars'        => array( 'name' => 'Webinar', 'heading' => 'Webinars' ),
	);
}

/**
 * Registers `resource-types`, `resource` and `newsroom`.
 *
 * The taxonomy is registered first so its rules come before the single
 * resource rule (both live under /resource-center/).
 */
function ap_content_register_types() {
	if ( ! taxonomy_exists( 'resource-types' ) ) {
		register_taxonomy(
			'resource-types',
			array( 'resource' ),
			array(
				'labels'            => array(
					'name'          => 'Resource Types',
					'singular_name' => 'Resource Type',
					'menu_name'     => 'Resource Types',
					'all_items'     => 'All Resource Types',
					'edit_item'     => 'Edit Resource Type',
					'add_new_item'  => 'Add New Resource Type',
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'resource-types',
				'query_var'         => 'resource-types',
				'rewrite'           => array(
					'slug'         => 'resource-center',
					'with_front'   => false,
					'hierarchical' => false,
					'walk_dirs'    => false,
					'ep_mask'      => EP_NONE,
				),
			)
		);
	}

	if ( ! post_type_exists( 'resource' ) ) {
		register_post_type(
			'resource',
			array(
				'labels'        => array(
					'name'               => 'Resources',
					'singular_name'      => 'Resource',
					'add_new_item'       => 'Add New Resource',
					'edit_item'          => 'Edit Resource',
					'new_item'           => 'New Resource',
					'view_item'          => 'View Resource',
					'search_items'       => 'Search Resources',
					'not_found'          => 'No resources found',
					'all_items'          => 'All Resources',
					'archives'           => 'Resource Center',
					'menu_name'          => 'Resources',
				),
				'public'        => true,
				'show_in_rest'  => true,
				'rest_base'     => 'resource',
				'menu_position' => 5,
				'menu_icon'     => 'dashicons-media-document',
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'taxonomies'    => array( 'resource-types', 'category' ),
				'has_archive'   => 'resource-center',
				'rewrite'       => array(
					'slug'       => 'resource-center/%resource-types%',
					'with_front' => false,
					'feeds'      => false,
					'pages'      => true,
					'walk_dirs'  => false,
				),
			)
		);
	}

	if ( ! post_type_exists( 'newsroom' ) ) {
		register_post_type(
			'newsroom',
			array(
				'labels'        => array(
					'name'          => 'Newsroom',
					'singular_name' => 'Press Release',
					'add_new_item'  => 'Add New Press Release',
					'edit_item'     => 'Edit Press Release',
					'view_item'     => 'View Press Release',
					'all_items'     => 'All Press Releases',
					'archives'      => 'Press Room',
					'menu_name'     => 'Newsroom',
				),
				'public'        => true,
				'show_in_rest'  => true,
				'rest_base'     => 'newsroom',
				'menu_position' => 6,
				'menu_icon'     => 'dashicons-megaphone',
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'has_archive'   => 'pressroom',
				'rewrite'       => array(
					'slug'       => 'pressroom',
					'with_front' => false,
					'feeds'      => false,
					'pages'      => true,
					'walk_dirs'  => false,
				),
			)
		);
	}

	// Industry categories ("By Business Type" on live) also apply to resources.
	register_taxonomy_for_object_type( 'category', 'resource' );

	// Listing pagination ahead of the single-resource rule, so /resource-center/blog/page/2/
	// is page 2 of Blog and never a resource called "page".
	add_rewrite_rule( '^resource-center/([^/]+)/page/?([0-9]{1,})/?$', 'index.php?resource-types=$matches[1]&paged=$matches[2]', 'top' );
}
add_action( 'init', 'ap_content_register_types', 5 );

/** Creates the six Resource Types when missing (slug is the key; names are never overwritten). */
function ap_content_ensure_terms() {
	if ( ! taxonomy_exists( 'resource-types' ) ) {
		return;
	}
	foreach ( ap_content_type_defaults() as $slug => $type ) {
		if ( ! term_exists( $slug, 'resource-types' ) ) {
			wp_insert_term( $type['name'], 'resource-types', array( 'slug' => $slug ) );
		}
	}
	update_option( 'ap_content_terms_ver', AP_CONTENT_VERSION, false );
}

add_action(
	'init',
	function () {
		if ( get_option( 'ap_content_terms_ver' ) !== AP_CONTENT_VERSION ) {
			ap_content_ensure_terms();
		}
	},
	20
);

/*
 * One rewrite flush, the request after activation or an upgrade (wp_loaded:
 * every permastruct is registered by then).
 */
add_action(
	'wp_loaded',
	function () {
		if ( get_option( 'ap_content_rewrite_ver' ) !== AP_CONTENT_REWRITE_VER ) {
			flush_rewrite_rules( false );
			update_option( 'ap_content_rewrite_ver', AP_CONTENT_REWRITE_VER, true );
		}
	}
);

/**
 * The Resource Type a resource's URL uses: Yoast's primary term when it is one
 * of the post's types, else the post's type with the lowest term id.
 *
 * @param WP_Post|int $post Post.
 * @return WP_Term|null
 */
function ap_content_primary_type( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'resource' !== $post->post_type ) {
		return null;
	}
	$terms = get_the_terms( $post, 'resource-types' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	$primary = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_resource-types', true );
	foreach ( $terms as $term ) {
		if ( (int) $term->term_id === $primary ) {
			return $term;
		}
	}
	usort(
		$terms,
		function ( $a, $b ) {
			return (int) $a->term_id - (int) $b->term_id;
		}
	);
	return $terms[0];
}

add_filter(
	'post_type_link',
	function ( $link, $post ) {
		if ( 'resource' !== $post->post_type || false === strpos( $link, '%resource-types%' ) ) {
			return $link;
		}
		$term = ap_content_primary_type( $post );
		$slug = $term ? $term->slug : (string) apply_filters( 'ap_content_default_type_slug', 'blog' );
		return str_replace( '%resource-types%', $slug, $link );
	},
	10,
	2
);

/**
 * The id of a resource/newsroom/post with this slug, any status but trash.
 *
 * @param string $type Post type.
 * @param string $slug Post name.
 * @return int 0 when none.
 */
function ap_content_find_by_slug( $type, $slug ) {
	global $wpdb;
	if ( '' === (string) $slug ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY (post_status = 'publish') DESC, ID ASC LIMIT 1",
			$type,
			$slug
		)
	);
}

/*
 * Routing. /resource-center/<a>/<b>/ is first a resource named <b> (whatever
 * <a> says: a wrong type 301s to the right one below). When no resource has
 * that name, the address goes to what used to own it: a page at that path,
 * or, for /resource-center/blog/<b>/, a core post (the dev site's posts use
 * /resource-center/blog/%postname%/). /resource-center/<a>/ is the Resource
 * Type <a>, else a child page at that path.
 */
add_filter(
	'request',
	function ( $qv ) {
		try {
			// WP::parse_request has already added post_type=resource and name=<b> for the CPT query var.
			if ( ! empty( $qv['resource'] ) && isset( $qv['resource-types'] ) && ( empty( $qv['post_type'] ) || 'resource' === $qv['post_type'] ) ) {
				$type_slug = (string) $qv['resource-types'];
				$slug      = (string) $qv['resource'];
				unset( $qv['resource-types'] );
				$qv['ap_rtype'] = $type_slug;
				if ( ! ap_content_find_by_slug( 'resource', $slug ) ) {
					$path = 'resource-center/' . $type_slug . '/' . $slug;
					if ( get_page_by_path( $path, OBJECT, array( 'page' ) ) ) {
						return array_filter( array( 'pagename' => $path, 'page' => isset( $qv['page'] ) ? $qv['page'] : '' ) );
					}
					if ( 'blog' === $type_slug && ap_content_find_by_slug( 'post', $slug )
						&& 0 === strpos( (string) get_option( 'permalink_structure' ), '/resource-center/blog/%postname%' ) ) {
						return array_filter( array( 'name' => $slug, 'page' => isset( $qv['page'] ) ? $qv['page'] : '' ) );
					}
				}
				return $qv;
			}
			if ( isset( $qv['resource-types'] ) && empty( $qv['resource'] ) && ! term_exists( (string) $qv['resource-types'], 'resource-types' ) ) {
				$path = 'resource-center/' . $qv['resource-types'];
				if ( get_page_by_path( $path, OBJECT, array( 'page' ) ) ) {
					return array_filter( array( 'pagename' => $path, 'paged' => isset( $qv['paged'] ) ? $qv['paged'] : '' ) );
				}
			}
		} catch ( \Throwable $e ) {
			return $qv;
		}
		return $qv;
	}
);

/** URL of a Resource Type's listing: /compare/ for Compare when that page exists, as live. */
function ap_content_type_url( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return get_post_type_archive_link( 'resource' );
	}
	$redirects = ap_content_listing_redirects();
	if ( isset( $redirects[ $term->slug ] ) ) {
		$page = get_page_by_path( $redirects[ $term->slug ], OBJECT, array( 'page' ) );
		if ( $page && 'publish' === $page->post_status ) {
			return get_permalink( $page );
		}
	}
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? get_post_type_archive_link( 'resource' ) : $link;
}

/**
 * Resource Type listings that live 301s to a page (term slug → page path).
 *
 * @return array<string, string>
 */
function ap_content_listing_redirects() {
	return (array) apply_filters( 'ap_content_listing_redirects', array( 'compare' => 'compare' ) );
}

add_action(
	'template_redirect',
	function () {
		try {
			// Old taxonomy and post type addresses.
			if ( is_404() ) {
				$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
				if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
					$path = '/' . substr( $path, strlen( $home ) );
				}
				if ( preg_match( '#^/resource-(?:category|types)/([a-z0-9_-]+)/?(?:page/([0-9]+)/?)?$#i', $path, $m ) ) {
					$term = get_term_by( 'slug', strtolower( $m[1] ), 'resource-types' );
					if ( $term ) {
						$url = ap_content_type_url( $term );
						if ( ! empty( $m[2] ) && (int) $m[2] > 1 ) {
							$url = trailingslashit( $url ) . 'page/' . (int) $m[2] . '/';
						}
						wp_safe_redirect( $url, 301, 'Asset Panda Content' );
						exit;
					}
				}
				if ( preg_match( '#^/resource/?$#', $path ) ) {
					wp_safe_redirect( get_post_type_archive_link( 'resource' ), 301, 'Asset Panda Content' );
					exit;
				}
				return;
			}

			// A listing live sends elsewhere (Compare → /compare/).
			if ( is_tax( 'resource-types' ) ) {
				$term      = get_queried_object();
				$redirects = ap_content_listing_redirects();
				if ( $term instanceof WP_Term && isset( $redirects[ $term->slug ] ) ) {
					$target = ap_content_type_url( $term );
					if ( $target && false === strpos( $target, '/resource-center/' . $term->slug . '/' ) ) {
						wp_safe_redirect( $target, 301, 'Asset Panda Content' );
						exit;
					}
				}
				return;
			}

			// A resource asked for under the wrong type: one 301 to its address.
			if ( is_singular( 'resource' ) && ! is_preview() ) {
				$asked = (string) get_query_var( 'ap_rtype' );
				$post  = get_queried_object();
				if ( '' !== $asked && $post instanceof WP_Post && 'publish' === $post->post_status ) {
					$term = ap_content_primary_type( $post );
					if ( $term && $term->slug !== $asked ) {
						$url   = get_permalink( $post );
						$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						if ( '' !== $query ) {
							$url .= '?' . $query;
						}
						wp_safe_redirect( $url, 301, 'Asset Panda Content' );
						exit;
					}
				}
			}
		} catch ( \Throwable $e ) {
			return;
		}
	},
	1
);

/*
 * Category (industry) archives list resources too: the blog hero's category
 * tabs count them, so their archives must show them.
 */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_category() || $q->get( 'post_type' ) ) {
			return;
		}
		$q->set( 'post_type', array( 'post', 'resource' ) );
	}
);
