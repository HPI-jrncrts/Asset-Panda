<?php
/**
 * Listings, filters and singles in live's look (1.3.0).
 *
 * Adapters only: WordPress data → props → the kit's resource-* components
 * (resource-hero, resource-search, resource-cards, resource-header). When the
 * installed kit does not have them yet, every renderer falls back to the 1.2
 * output (render.php), so this plugin can go up before or after the kit.
 *
 * Filter addresses are live's (www.assetpanda.com):
 *   /resource-center/filter/<search>/<industry>/<type>/   (+ /page/N/)
 *       "search" / "category" stand for "none": /resource-center/filter/search/education/category/
 *       is every resource in Education; /resource-center/filter/search/education/case-studies/
 *       the case studies in it (the type listings' tabs use this form so the type is kept);
 *       /resource-center/filter/<words>/category/category/ is a search of everything.
 *   /resource-center/<type>/filter/<search>/<category>/   (+ /page/N/)
 *       the blog's topic tabs: /resource-center/blog/filter/search/accounting-finance/ and its
 *       search: /resource-center/blog/filter/<words>/category/. For other types <category> is
 *       an industry.
 *   ?q=<words> on any listing (the search form without JavaScript; the Press Room's search).
 * Filtered pages print the unfiltered listing as canonical (as live); searches are noindex.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Routes ──────────────────────────────────────────────────────────── */

add_filter(
	'query_vars',
	function ( $vars ) {
		return array_merge( $vars, array( 'ap_q', 'ap_ind', 'ap_ftype', 'ap_fcat' ) );
	}
);

add_action(
	'init',
	function () {
		$rc = 'index.php?post_type=resource';
		$n  = '([^/]+)';
		$p  = '/page/?([0-9]{1,})/?$';
		add_rewrite_rule( "^resource-center/filter/$n/$n/$n$p", $rc . '&ap_q=$matches[1]&ap_ind=$matches[2]&ap_ftype=$matches[3]&paged=$matches[4]', 'top' );
		add_rewrite_rule( "^resource-center/filter/$n/$n/$n/?$", $rc . '&ap_q=$matches[1]&ap_ind=$matches[2]&ap_ftype=$matches[3]', 'top' );
		add_rewrite_rule( "^resource-center/filter/$n/$n$p", $rc . '&ap_q=$matches[1]&ap_ind=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( "^resource-center/filter/$n/$n/?$", $rc . '&ap_q=$matches[1]&ap_ind=$matches[2]', 'top' );
		add_rewrite_rule( "^resource-center/filter/$n/?$", $rc . '&ap_q=$matches[1]', 'top' );
		add_rewrite_rule( "^resource-center/$n/filter/$n/$n$p", 'index.php?resource-types=$matches[1]&ap_q=$matches[2]&ap_fcat=$matches[3]&paged=$matches[4]', 'top' );
		add_rewrite_rule( "^resource-center/$n/filter/$n/$n/?$", 'index.php?resource-types=$matches[1]&ap_q=$matches[2]&ap_fcat=$matches[3]', 'top' );
		add_rewrite_rule( "^resource-center/$n/filter/$n$p", 'index.php?resource-types=$matches[1]&ap_q=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( "^resource-center/$n/filter/$n/?$", 'index.php?resource-types=$matches[1]&ap_q=$matches[2]', 'top' );
	},
	7
);

/**
 * A path segment of a filter address: '' for the "none" placeholders.
 *
 * @param string $value       Raw query var.
 * @param string $placeholder search|category.
 * @param bool   $words       Free text (search) rather than a slug.
 * @return string
 */
function ap_content_filter_part( $value, $placeholder, $words = false ) {
	$value = trim( urldecode( (string) $value ) );
	if ( '' === $value || $placeholder === strtolower( $value ) ) {
		return '';
	}
	if ( $words ) {
		return trim( preg_replace( '/\s+/', ' ', sanitize_text_field( $value ) ) );
	}
	return sanitize_title( $value );
}

/**
 * The filter on the current main query.
 *
 * @return array{q: string, ind: string, ftype: string, fcat: string, active: bool}
 */
function ap_content_listing_filter() {
	static $f = null;
	if ( null !== $f && did_action( 'wp' ) ) {
		return $f;
	}
	$q = ap_content_filter_part( get_query_var( 'ap_q' ), 'search', true );
	if ( '' === $q && isset( $_GET['q'] ) && is_string( $_GET['q'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$q = ap_content_filter_part( wp_unslash( $_GET['q'] ), '', true ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	$res = array(
		'q'     => mb_substr( $q, 0, 80 ),
		'ind'   => ap_content_filter_part( get_query_var( 'ap_ind' ), 'category' ),
		'ftype' => ap_content_filter_part( get_query_var( 'ap_ftype' ), 'category' ),
		'fcat'  => ap_content_filter_part( get_query_var( 'ap_fcat' ), 'category' ),
	);
	$res['active'] = '' !== $res['q'] || '' !== $res['ind'] || '' !== $res['ftype'] || '' !== $res['fcat'];
	if ( did_action( 'wp' ) ) {
		$f = $res;
	}
	return $res;
}

/** True on a resource listing (all, a type, a filter) or the Press Room. */
function ap_content_is_listing() {
	return is_tax( 'resource-types' ) || is_post_type_archive( array( 'resource', 'newsroom' ) );
}

/* Apply the filter to the main query. */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		$type_qv = (string) $q->get( 'resource-types' );
		$is_rc   = 'resource' === $q->get( 'post_type' ) && $q->is_post_type_archive();
		$is_type = '' !== $type_qv && ! $q->get( 'resource' ) && $q->is_tax();
		$is_pr   = 'newsroom' === $q->get( 'post_type' ) && $q->is_post_type_archive();
		if ( ! $is_rc && ! $is_type && ! $is_pr ) {
			return;
		}
		$f = ap_content_listing_filter();
		if ( ! $f['active'] ) {
			return;
		}
		$tax = array();
		if ( $is_type ) {
			// The listing's own type first, so it stays the queried object.
			$tax[] = array(
				'taxonomy' => 'resource-types',
				'field'    => 'slug',
				'terms'    => array( sanitize_title( $type_qv ) ),
			);
			if ( '' !== $f['fcat'] ) {
				$tax[] = array(
					'taxonomy' => 'blog' === $type_qv ? 'category' : 'resource-industry',
					'field'    => 'slug',
					'terms'    => array( $f['fcat'] ),
				);
			}
		} elseif ( $is_rc ) {
			if ( '' !== $f['ftype'] ) {
				$tax[] = array(
					'taxonomy' => 'resource-types',
					'field'    => 'slug',
					'terms'    => array( $f['ftype'] ),
				);
			}
			if ( '' !== $f['ind'] ) {
				$tax[] = array(
					'taxonomy' => 'resource-industry',
					'field'    => 'slug',
					'terms'    => array( $f['ind'] ),
				);
			}
		}
		if ( $tax ) {
			$q->set( 'tax_query', count( $tax ) > 1 ? array_merge( array( 'relation' => 'AND' ), $tax ) : $tax );
		}
		if ( '' !== $f['q'] ) {
			// Set after parse_query, so the page stays a listing (is_search stays false) but WordPress searches.
			$q->set( 's', $f['q'] );
		}
	},
	20
);

/**
 * A looser form of a search for a second try: each long word cut to its stem
 * ("barcode scaner" → "barco scan"), so one typo near the end of a word, or a
 * plural, still finds the page. The instant search is typo-tolerant itself;
 * this keeps its "View all results" page in step with it.
 *
 * @param string $q Search words.
 * @return string '' when nothing would change.
 */
function ap_content_loose_terms( $q ) {
	$out = array();
	foreach ( preg_split( '/\s+/', trim( (string) $q ) ) as $w ) {
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $w ) : strlen( $w );
		$out[] = $len >= 5 ? ( function_exists( 'mb_substr' ) ? mb_substr( $w, 0, max( 4, (int) floor( $len * 0.6 ) ) ) : substr( $w, 0, max( 4, (int) floor( $len * 0.6 ) ) ) ) : $w;
	}
	$loose = implode( ' ', array_filter( $out, 'strlen' ) );
	return $loose === trim( (string) $q ) ? '' : $loose;
}

/* A search that found nothing gets one looser try (see ap_content_loose_terms). */
add_action(
	'wp',
	function () {
		global $wp_query;
		if ( is_admin() || ! ap_content_is_listing() || $wp_query->found_posts || max( 1, (int) get_query_var( 'paged' ) ) > 1 ) {
			return;
		}
		$f = ap_content_listing_filter();
		if ( '' === $f['q'] ) {
			return;
		}
		$loose = ap_content_loose_terms( $f['q'] );
		if ( '' === $loose ) {
			return;
		}
		try {
			$vars      = $wp_query->query_vars;
			$vars['s'] = $loose;
			$second    = new WP_Query( $vars );
			if ( $second->posts ) {
				$wp_query->posts         = $second->posts;
				$wp_query->post_count    = $second->post_count;
				$wp_query->found_posts   = $second->found_posts;
				$wp_query->max_num_pages = $second->max_num_pages;
				$wp_query->post          = $second->posts[0];
				$wp_query->current_post  = -1;
				$GLOBALS['ap_content_loose_search'] = true;
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}
);

/* ── Context ─────────────────────────────────────────────────────────── */

/**
 * Live's one line under each listing's H1.
 *
 * @param string $key Type slug, 'all' or 'press'.
 * @return string
 */
function ap_content_listing_description( $key ) {
	$d = array(
		'all'             => 'Explore practical guides, expert insights, industry trends, and proven strategies to help you manage assets more efficiently, improve compliance, and make smarter operational decisions.',
		'blog'            => 'Explore practical guides, expert insights, industry trends, and proven strategies to help you manage assets more efficiently, improve compliance, and make smarter operational decisions.',
		'case-studies'    => 'See how organizations across industries use Asset Panda to track, audit, and manage their physical assets with confidence.',
		'success-stories' => 'Real results from teams that transformed how they track and manage assets with Asset Panda.',
		'white-papers'    => 'In-depth research, benchmarks, and practical frameworks for smarter asset management.',
		'webinars'        => 'Live sessions and on-demand recordings from Asset Panda experts and customers.',
		'press'           => 'Company news, product announcements and press releases from Asset Panda.',
	);
	$d = (array) apply_filters( 'ap_content_listing_descriptions', $d );
	return isset( $d[ $key ] ) ? (string) $d[ $key ] : (string) $d['all'];
}

/**
 * The blog's topic tabs in live's order.
 *
 * @return string[]
 */
function ap_content_blog_topics() {
	return (array) apply_filters(
		'ap_content_blog_topics',
		array( 'asset-management-tracking', 'construction', 'education', 'government', 'healthcare', 'information-technology', 'inventory-management', 'equipment', 'tool-tracking', 'first-responders', 'compliance', 'maintenance', 'fixed-assets', 'barcode-tracking', 'calibration', 'accounting-finance', 'oil-gas', 'reservations' )
	);
}

/**
 * Published resources per (taxonomy, term, type): ['category|slug|type' => n, 'resource-industry|slug|*' => n].
 *
 * @return array<string, int>
 */
function ap_content_listing_counts() {
	$cached = get_transient( 'ap_content_listing_counts' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$out = array();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		"SELECT tt.taxonomy AS tax, t.slug AS slug, ty.slug AS type, COUNT(DISTINCT p.ID) AS n
		FROM {$wpdb->posts} p
		JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('category','resource-industry')
		JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		JOIN {$wpdb->term_relationships} tr2 ON tr2.object_id = p.ID
		JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id AND tt2.taxonomy = 'resource-types'
		JOIN {$wpdb->terms} ty ON ty.term_id = tt2.term_id
		WHERE p.post_type = 'resource' AND p.post_status = 'publish'
		GROUP BY tt.taxonomy, t.slug, ty.slug",
		ARRAY_A
	);
	foreach ( (array) $rows as $r ) {
		$out[ $r['tax'] . '|' . $r['slug'] . '|' . $r['type'] ] = (int) $r['n'];
	}
	$rows = $wpdb->get_results(
		"SELECT tt.taxonomy AS tax, t.slug AS slug, COUNT(DISTINCT p.ID) AS n
		FROM {$wpdb->posts} p
		JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy IN ('category','resource-industry')
		JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		WHERE p.post_type = 'resource' AND p.post_status = 'publish'
		GROUP BY tt.taxonomy, t.slug",
		ARRAY_A
	);
	// phpcs:enable
	foreach ( (array) $rows as $r ) {
		$out[ $r['tax'] . '|' . $r['slug'] . '|*' ] = (int) $r['n'];
	}
	set_transient( 'ap_content_listing_counts', $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * Filter address for a term.
 *
 * @param string $tax       category|resource-industry.
 * @param string $slug      Term slug.
 * @param string $type_slug Keep this type ('' = every type).
 * @return string
 */
function ap_content_term_filter_url( $tax, $slug, $type_slug = '' ) {
	if ( 'category' === $tax ) {
		return home_url( '/resource-center/blog/filter/search/' . rawurlencode( $slug ) . '/' );
	}
	return home_url( '/resource-center/filter/search/' . rawurlencode( $slug ) . '/' . ( '' !== $type_slug ? rawurlencode( $type_slug ) : 'category' ) . '/' );
}

/**
 * What the current listing is.
 *
 * @return array{kind: string, type: ?WP_Term, key: string, heading: string, base: string, filter: array}
 */
function ap_content_listing_context() {
	$f    = ap_content_listing_filter();
	$type = null;
	if ( is_post_type_archive( 'newsroom' ) ) {
		return array(
			'kind'    => 'press',
			'type'    => null,
			'key'     => 'press',
			'heading' => ap_content_archive_heading( 'newsroom' ),
			'base'    => (string) get_post_type_archive_link( 'newsroom' ),
			'filter'  => $f,
		);
	}
	if ( is_tax( 'resource-types' ) ) {
		$type = get_queried_object();
	} elseif ( '' !== $f['ftype'] ) {
		$type = get_term_by( 'slug', $f['ftype'], 'resource-types' );
	}
	if ( $type instanceof WP_Term ) {
		return array(
			'kind'    => 'type',
			'type'    => $type,
			'key'     => $type->slug,
			'heading' => ap_content_type_heading( $type ),
			'base'    => ap_content_type_url( $type ),
			'filter'  => $f,
		);
	}
	return array(
		'kind'    => 'rc',
		'type'    => null,
		'key'     => 'all',
		'heading' => ap_content_archive_heading( 'resource' ),
		'base'    => (string) get_post_type_archive_link( 'resource' ),
		'filter'  => $f,
	);
}

/**
 * The tab rail for a listing.
 *
 * @param array $ctx Listing context.
 * @return array
 */
function ap_content_listing_tabs( $ctx ) {
	if ( 'press' === $ctx['kind'] ) {
		return array();
	}
	$f      = $ctx['filter'];
	$counts = ap_content_listing_counts();
	$tabs   = array();
	if ( 'type' === $ctx['kind'] && 'blog' === $ctx['key'] ) {
		$active = $f['fcat'];
		foreach ( ap_content_blog_topics() as $slug ) {
			$term = get_term_by( 'slug', $slug, 'category' );
			if ( ! $term || empty( $counts[ 'category|' . $slug . '|blog' ] ) ) {
				continue;
			}
			$tabs[] = array(
				'label'  => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'url'    => ap_content_term_filter_url( 'category', $slug ),
				'active' => $active === $slug,
			);
		}
	} else {
		$type   = 'type' === $ctx['kind'] ? $ctx['key'] : '';
		$active = '' !== $f['ind'] ? $f['ind'] : $f['fcat'];
		foreach ( ap_content_industry_defaults() as $slug => $name ) {
			$n = $counts[ 'resource-industry|' . $slug . '|' . ( '' !== $type ? $type : '*' ) ] ?? 0;
			if ( ! $n ) {
				continue;
			}
			$term   = get_term_by( 'slug', $slug, 'resource-industry' );
			$tabs[] = array(
				'label'  => $term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : $name,
				'url'    => ap_content_term_filter_url( 'resource-industry', $slug, $type ),
				'active' => $active === $slug,
			);
		}
	}
	if ( ! $tabs ) {
		return array();
	}
	$any = false;
	foreach ( $tabs as $t ) {
		$any = $any || $t['active'];
	}
	array_unshift(
		$tabs,
		array(
			'label'  => 'All',
			'url'    => $ctx['base'],
			'active' => ! $any,
		)
	);
	return $tabs;
}

/**
 * resource-search props for a listing.
 *
 * @param array $ctx Listing context.
 * @return array
 */
function ap_content_search_props( $ctx ) {
	$all = home_url( '/resource-center/filter/{q}/category/category/' );
	switch ( $ctx['kind'] ) {
		case 'press':
			$action = home_url( '/pressroom/' ) . '?q={q}';
			$scope  = 'press';
			break;
		case 'type':
			$action = 'blog' === $ctx['key'] ? home_url( '/resource-center/blog/filter/{q}/category/' ) : home_url( '/resource-center/filter/{q}/category/' . rawurlencode( $ctx['key'] ) . '/' );
			$scope  = $ctx['key'];
			break;
		default:
			$action = $all;
			$scope  = '';
	}
	$heading = $ctx['heading'];
	return array(
		'index_url'    => ap_content_search_index_url(),
		'label'        => 'Search ' . $heading,
		'placeholder'  => 'Search ' . ( 'rc' === $ctx['kind'] ? 'resource center' : strtolower( $heading ) ),
		'value'        => $ctx['filter']['q'],
		'fallback_url' => $ctx['base'],
		'action'       => $action,
		'action_all'   => $all,
		'scope'        => $scope,
		'scope_label'  => $heading,
		'max_results'  => 8,
	);
}

/** True when the kit has these components (the 1.2 output is used otherwise). */
function ap_content_kit_ready( ...$names ) {
	if ( ! function_exists( 'ap_render_component' ) || ! defined( 'AP_UI_DIR' ) || (bool) apply_filters( 'ap_content_classic_design', defined( 'AP_CONTENT_CLASSIC' ) && AP_CONTENT_CLASSIC ) ) {
		return false;
	}
	foreach ( $names as $name ) {
		if ( ! is_readable( AP_UI_DIR . '/components/' . $name . '/render.php' ) ) {
			return false;
		}
	}
	return true;
}

/* ── Cards ───────────────────────────────────────────────────────────── */

/**
 * The resource type label and listing of a post ("Press Release" for the newsroom).
 *
 * @param WP_Post $post Post.
 * @return array{0: string, 1: string, 2: string} label, url, type slug.
 */
function ap_content_post_type_label( $post ) {
	if ( 'newsroom' === $post->post_type ) {
		return array( (string) apply_filters( 'ap_content_press_label', 'Press Release' ), (string) get_post_type_archive_link( 'newsroom' ), 'press' );
	}
	$term = ap_content_primary_type( $post );
	if ( ! $term ) {
		return array( '', '', '' );
	}
	return array( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ), ap_content_type_url( $term ), $term->slug );
}

/**
 * A post's categories for display: topics then industries, each with its filter address.
 *
 * @param WP_Post $post Post.
 * @return array<int, array{label: string, url: string, tax: string, slug: string}>
 */
function ap_content_post_categories( $post ) {
	if ( 'resource' !== $post->post_type ) {
		return array();
	}
	$out     = array();
	$default = (int) get_option( 'default_category' );
	$counts  = ap_content_listing_counts();
	$type    = ap_content_primary_type( $post );
	$tslug   = $type ? $type->slug : '';
	foreach ( (array) get_the_terms( $post, 'category' ) as $c ) {
		if ( ! $c instanceof WP_Term || (int) $c->term_id === $default ) {
			continue;
		}
		// A topic links to the blog's topic tab when that tab has posts.
		$out[] = array(
			'label' => html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' ),
			'url'   => empty( $counts[ 'category|' . $c->slug . '|blog' ] ) ? '' : ap_content_term_filter_url( 'category', $c->slug ),
			'tax'   => 'category',
			'slug'  => $c->slug,
		);
	}
	foreach ( ap_content_post_industries( $post->ID ) as $c ) {
		$dup = false;
		foreach ( $out as $o ) {
			$dup = $dup || $o['slug'] === $c->slug;
		}
		if ( $dup && 'blog' === $tslug ) {
			continue;
		}
		if ( $dup ) {
			// Same word as a topic (Education, Government…): the industry tab is the one for this type.
			$out = array_values(
				array_filter(
					$out,
					function ( $o ) use ( $c ) {
						return $o['slug'] !== $c->slug;
					}
				)
			);
		}
		$out[] = array(
			'label' => html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' ),
			'url'   => ap_content_term_filter_url( 'resource-industry', $c->slug, 'blog' === $tslug ? '' : $tslug ),
			'tax'   => 'resource-industry',
			'slug'  => $c->slug,
		);
	}
	return $out;
}

/**
 * The category a card names: a blog post's first topic, another type's first industry.
 *
 * @param WP_Post $post Post.
 * @return array{label: string, url: string}|null
 */
function ap_content_post_primary_category( $post ) {
	$cats = ap_content_post_categories( $post );
	if ( ! $cats ) {
		return null;
	}
	$type = ap_content_primary_type( $post );
	$want = ( $type && 'blog' === $type->slug ) ? 'category' : 'resource-industry';
	foreach ( $cats as $c ) {
		if ( $want === $c['tax'] ) {
			return $c;
		}
	}
	return $cats[0];
}

/**
 * resource-cards item for a post.
 *
 * @param WP_Post $post Post.
 * @return array
 */
function ap_content_card_props( $post ) {
	list( $label, $label_url ) = ap_content_post_type_label( $post );
	$img                       = ap_content_post_image( $post->ID, 'large' );
	$cat                       = ap_content_post_primary_category( $post );
	$item                      = array(
		'title'     => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		'url'       => (string) get_permalink( $post ),
		'image'     => $img['url'],
		'image_alt' => $img['alt'],
		'image_w'   => $img['w'],
		'image_h'   => $img['h'],
		'label'     => $label,
		'label_url' => $label_url,
		'excerpt'   => ap_content_card_text( $post ),
	);
	if ( $cat ) {
		$item['category']     = $cat['label'];
		$item['category_url'] = $cat['url'];
	}
	if ( 'newsroom' === $post->post_type ) {
		$item['date']     = get_the_date( 'F j, Y', $post );
		$item['date_iso'] = get_the_date( 'Y-m-d', $post );
	}
	return $item;
}

/* ── Renderers (pre_render_block, see render.php) ─────────────────────── */

/** ap26/blog-hero and ap26/category-hero on a listing. */
function ap_content_render_hub_hero() {
	if ( ! ap_content_kit_ready( 'resource-hero', 'resource-search' ) ) {
		return is_post_type_archive( 'resource' ) && ! ap_content_listing_filter()['active'] ? ap_content_render_blog_hero() : ap_content_render_archive_hero();
	}
	$ctx = ap_content_listing_context();
	return ap_render_component(
		'resource-hero',
		array(
			'eyebrow'     => 'press' === $ctx['kind'] ? 'Newsroom' : 'Resource Center',
			'title'       => $ctx['heading'],
			'description' => ap_content_listing_description( $ctx['key'] ),
			'search'      => ap_content_search_props( $ctx ),
			'tabs'        => ap_content_listing_tabs( $ctx ),
			'tabs_label'  => 'blog' === $ctx['key'] ? 'Filter by topic' : 'Filter by industry',
		)
	);
}

/**
 * "Showing 1–6 of 268" for the main query.
 *
 * @param array $ctx Listing context.
 * @return string
 */
function ap_content_listing_count_text( $ctx ) {
	global $wp_query;
	$total = (int) $wp_query->found_posts;
	if ( ! $total ) {
		return '';
	}
	$per   = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$from  = ( $paged - 1 ) * $per + 1;
	$to    = min( $total, $from + count( $wp_query->posts ) - 1 );
	$text  = sprintf( 'Showing %1$s–%2$s of %3$s', number_format_i18n( $from ), number_format_i18n( $to ), number_format_i18n( $total ) );
	$f     = $ctx['filter'];
	if ( '' !== $f['q'] ) {
		$text .= ' ' . ( ! empty( $GLOBALS['ap_content_loose_search'] ) ? ( 1 === $total ? 'close match' : 'close matches' ) : ( 1 === $total ? 'result' : 'results' ) ) . ' for “' . $f['q'] . '”';
	}
	$slug = '' !== $f['ind'] ? $f['ind'] : $f['fcat'];
	if ( '' !== $slug ) {
		$tax  = ( '' === $f['ind'] && 'blog' === $ctx['key'] ) ? 'category' : 'resource-industry';
		$term = get_term_by( 'slug', $slug, $tax );
		if ( $term ) {
			$text .= ' in ' . html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
		}
	}
	return $text;
}

/**
 * Page links for the main query, live's pattern: 1 2 3 … 42 43 44 / 1 … 9 10 11 … 44.
 *
 * @return array{pages: array, prev: string, next: string}
 */
function ap_content_listing_pages() {
	global $wp_query;
	$max = (int) $wp_query->max_num_pages;
	$cur = max( 1, (int) get_query_var( 'paged' ) );
	$out = array(
		'pages' => array(),
		'prev'  => '',
		'next'  => '',
	);
	if ( $max < 2 ) {
		return $out;
	}
	if ( $max <= 7 ) {
		$seq = range( 1, $max );
	} elseif ( $cur <= 3 ) {
		$seq = array( 1, 2, 3, 4, 0, $max - 1, $max );
	} elseif ( $cur >= $max - 2 ) {
		$seq = array( 1, 2, 0, $max - 3, $max - 2, $max - 1, $max );
	} else {
		$seq = array( 1, 0, $cur - 1, $cur, $cur + 1, 0, $max );
	}
	foreach ( $seq as $n ) {
		$out['pages'][] = $n ? array(
			'label'   => (string) $n,
			'url'     => get_pagenum_link( $n ),
			'current' => $n === $cur,
		) : array( 'gap' => true );
	}
	$out['prev'] = $cur > 1 ? get_pagenum_link( $cur - 1 ) : '';
	$out['next'] = $cur < $max ? get_pagenum_link( $cur + 1 ) : '';
	return $out;
}

/** ap26/blog-grid on a listing. */
function ap_content_render_hub_grid() {
	if ( ! ap_content_kit_ready( 'resource-cards' ) ) {
		return ap_content_render_grid();
	}
	global $wp_query;
	$ctx   = ap_content_listing_context();
	$items = array();
	foreach ( (array) $wp_query->posts as $post ) {
		if ( $post instanceof WP_Post ) {
			$items[] = ap_content_card_props( $post );
		}
	}
	$pages = ap_content_listing_pages();
	$f     = $ctx['filter'];
	$empty = array(
		array(
			'label' => 'rc' === $ctx['kind'] ? 'Browse every resource' : ( 'press' === $ctx['kind'] ? 'All press releases' : 'All ' . $ctx['heading'] ),
			'url'   => $ctx['base'],
		),
	);
	if ( 'rc' !== $ctx['kind'] ) {
		$empty[] = array(
			'label' => 'The whole Resource Center',
			'url'   => (string) get_post_type_archive_link( 'resource' ),
		);
	}
	return ap_render_component(
		'resource-cards',
		array(
			'count_text'  => ap_content_listing_count_text( $ctx ),
			'items'       => $items,
			'eager'       => max( 1, (int) get_query_var( 'paged' ) ) > 1 ? 0 : 3,
			'pages'       => $pages['pages'],
			'prev_url'    => $pages['prev'],
			'next_url'    => $pages['next'],
			'empty_title' => '' !== $f['q'] ? 'No results for “' . $f['q'] . '”.' : 'Nothing here yet.',
			'empty_text'  => '' !== $f['q'] ? 'Check the spelling, try a broader word, or browse the listings below.' : 'There are no resources in this category yet.',
			'empty_links' => $empty,
		)
	);
}

/**
 * Resource Types whose singles use the 2026 post design's header (Figma Blog
 * Detail 5434:79954 for blog posts, the compare post 7219:87997 for compare
 * posts — the listicles are compare posts too) instead of live's band. The
 * other types (case studies, success stories, white papers, webinars) and
 * press releases keep the band, as on www.assetpanda.com.
 *
 * @return string[] Type slugs.
 */
function ap_content_article_header_types() {
	return (array) apply_filters( 'ap_content_article_header_types', array( 'blog', 'compare' ) );
}

/**
 * True when the installed kit's resource-header declares the "article"
 * variant (not in kit 1.3.114 or earlier). Without it the same props are drawn
 * as the 2026 blog templates' own post header (snippet 6's ap26/post-header
 * markup, render.php), which the ap26 stylesheet draws to the same design.
 *
 * @return bool
 */
function ap_content_kit_has_article_header() {
	if ( ! ap_content_kit_ready( 'resource-header' ) || ! function_exists( 'ap_ui_contract' ) ) {
		return false;
	}
	$contract = ap_ui_contract( 'resource-header' );
	return is_array( $contract ) && isset( $contract['props']['variant']['enum'] ) && in_array( 'article', (array) $contract['props']['variant']['enum'], true );
}

/** ap26/post-header on a resource / press release. */
function ap_content_render_single_header() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || ! ap_content_kit_ready( 'resource-header' ) ) {
		return ap_content_render_post_header();
	}
	list( $label, $label_url, $tslug ) = ap_content_post_type_label( $post );
	// 1.3.4: blog and compare posts follow the Figma post designs (light header, image below the title).
	$article = 'resource' === $post->post_type && in_array( $tslug, ap_content_article_header_types(), true );
	$title  = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );
	$crumbs = array(
		array(
			'label' => 'Home',
			'url'   => home_url( '/' ),
		),
	);
	if ( $article ) {
		$crumbs[0]['icon'] = 'home';
	}
	if ( 'newsroom' === $post->post_type ) {
		$crumbs[] = array(
			'label' => ap_content_archive_heading( 'newsroom' ),
			'url'   => (string) get_post_type_archive_link( 'newsroom' ),
		);
	} else {
		$crumbs[] = array(
			'label' => 'Resources',
			'url'   => (string) get_post_type_archive_link( 'resource' ),
		);
		$term = ap_content_primary_type( $post );
		if ( $term ) {
			$crumbs[] = array(
				'label' => ap_content_type_heading( $term ),
				'url'   => ap_content_type_url( $term ),
			);
		}
		$primary = ap_content_post_primary_category( $post );
		if ( $primary && '' !== $primary['url'] ) {
			$crumbs[] = array(
				'label' => $primary['label'],
				'url'   => $primary['url'],
			);
		}
	}
	$cats = array();
	foreach ( ap_content_post_categories( $post ) as $c ) {
		$cats[] = array(
			'label' => $c['label'],
			'url'   => $c['url'],
		);
	}
	// The article header shows the image at the full 928px column: the 1536px size (full when smaller).
	$img   = ap_content_post_image( $post->ID, $article ? '1536x1536' : 'large' );
	$props = array(
		'title'       => $title,
		'crumbs'      => $crumbs,
		'label'       => $label,
		'label_url'   => $label_url,
		'categories'  => $cats,
		'image'       => $img['url'],
		'image_alt'   => '' !== $img['alt'] ? $img['alt'] : $title,
		'image_w'     => $img['w'],
		'image_h'     => $img['h'],
		'image_side'  => 'case-studies' === $tslug ? 'left' : 'right',
		'share_url'   => (string) get_permalink( $post ),
		'share_title' => $title,
	);
	if ( 'newsroom' === $post->post_type ) {
		$props['date']     = get_the_date( 'F j, Y', $post );
		$props['date_iso'] = get_the_date( 'Y-m-d', $post );
	}
	if ( $article ) {
		$props['variant']     = 'article';
		$props['categories']  = array();
		$props['share_label'] = 'Share on:';
		// The eyebrow: a compare post says "Compare Asset Panda" (its type, as in the design); a blog
		// post names its topic (Construction, Education…), as the 2026 blog templates do for core posts.
		if ( 'blog' === $tslug ) {
			$primary = ap_content_post_primary_category( $post );
			if ( $primary ) {
				$props['label']     = $primary['label'];
				$props['label_url'] = $primary['url'];
			}
		}
	}
	$props = (array) apply_filters( 'ap_content_header_props', $props, $post );
	if ( $article && ! ap_content_kit_has_article_header() ) {
		// A kit without the article variant: the same props, drawn as the 2026 templates' own post header.
		return ap_content_render_article_header( $props, $post );
	}
	// The ap26 sticky title bar watches .ap26-post-header to know when the header has scrolled away.
	$wrap = 'ap26-post-header ap-content-header' . ( $article ? ' ap-content-header--article' : '' );
	return '<div class="' . esc_attr( $wrap ) . '">' . ap_render_component( 'resource-header', $props ) . '</div>';
}

/** ap26/related-posts on a resource / press release: three of the same type, closest topics first. */
function ap_content_render_single_related() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || ! ap_content_kit_ready( 'resource-cards' ) ) {
		return ap_content_render_related();
	}
	$base = array(
		'post_type'           => $post->post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post->ID ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$type_q = array();
	if ( 'resource' === $post->post_type ) {
		$term = ap_content_primary_type( $post );
		if ( $term ) {
			$type_q = array(
				'taxonomy' => 'resource-types',
				'field'    => 'term_id',
				'terms'    => array( (int) $term->term_id ),
			);
		}
	}
	$picked = array();
	$cats   = 'resource' === $post->post_type ? ap_content_post_categories( $post ) : array();
	if ( $cats ) {
		$by = array( 'relation' => 'OR' );
		foreach ( array( 'category', 'resource-industry' ) as $tax ) {
			$slugs = array();
			foreach ( $cats as $c ) {
				if ( $tax === $c['tax'] ) {
					$slugs[] = $c['slug'];
				}
			}
			if ( $slugs ) {
				$by[] = array(
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}
		$args              = $base;
		$args['tax_query'] = $type_q ? array( 'relation' => 'AND', $type_q, $by ) : $by; // phpcs:ignore WordPress.DB.SlowDBQuery
		$picked            = ( new WP_Query( $args ) )->posts;
	}
	if ( count( $picked ) < 3 ) {
		$args                   = $base;
		$args['posts_per_page'] = 3 - count( $picked );
		$args['post__not_in']   = array_merge( array( $post->ID ), wp_list_pluck( $picked, 'ID' ) );
		if ( $type_q ) {
			$args['tax_query'] = array( $type_q ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$picked = array_merge( $picked, ( new WP_Query( $args ) )->posts );
	}
	$picked = (array) apply_filters( 'ap_content_related_posts', $picked, $post );
	if ( ! $picked ) {
		return '';
	}
	$items = array();
	foreach ( $picked as $p ) {
		$items[] = ap_content_card_props( $p );
	}
	$heading = 'newsroom' === $post->post_type ? 'Related News & Press' : 'Related resources';
	return ap_render_component(
		'resource-cards',
		array(
			'variant' => 'related',
			'heading' => (string) apply_filters( 'ap_content_related_heading', $heading, $post ),
			'items'   => $items,
		)
	);
}

/*
 * Success stories open with "The Summary" + paragraph + "Key Asset Panda Features:" + list (78 of
 * them on dev). Live draws that as a panel with a features card; the run becomes the kit's
 * resource-summary. Anything else in the body is untouched.
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'resource' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id()
			|| doing_filter( 'get_the_excerpt' ) || ! ap_content_kit_ready( 'resource-summary' ) || false !== strpos( $content, 'ap-resource-summary' ) ) {
			return $content;
		}
		try {
			$type = ap_content_primary_type( get_queried_object_id() );
			if ( ! $type || 'success-stories' !== $type->slug ) {
				return $content;
			}
			$re = '#^(\s*)<h2[^>]*>\s*(The Summary)\s*</h2>\s*<p[^>]*>(.*?)</p>\s*<h3[^>]*>\s*(Key Asset Panda Features:?)\s*</h3>\s*<ul[^>]*>(.*?)</ul>#is';
			if ( ! preg_match( $re, $content, $m ) ) {
				return $content;
			}
			preg_match_all( '#<li[^>]*>(.*?)</li>#is', $m[5], $lis );
			$features = array();
			foreach ( $lis[1] as $li ) {
				$li = trim( preg_replace( '/\s+/', ' ', $li ) );
				if ( '' !== $li && false === stripos( $li, '<ul' ) ) {
					$features[] = $li;
				}
			}
			$html = ap_render_component(
				'resource-summary',
				array(
					'title'          => wp_strip_all_tags( $m[2] ),
					'text'           => trim( $m[3] ),
					'features_title' => wp_strip_all_tags( $m[4] ),
					'features'       => $features,
				)
			);
			return '' === $html ? $content : $m[1] . $html . substr( $content, strlen( $m[0] ) );
		} catch ( \Throwable $e ) {
			return $content;
		}
	},
	11
);

/* ── SEO for filtered listings ───────────────────────────────────────── */

/** True on a filtered or searched listing. */
function ap_content_listing_is_filtered() {
	return ap_content_is_listing() && ap_content_listing_filter()['active'];
}

/* Canonical: the unfiltered listing (as live). */
add_filter(
	'wpseo_canonical',
	function ( $canonical ) {
		if ( ! ap_content_listing_is_filtered() ) {
			return $canonical;
		}
		$ctx = ap_content_listing_context();
		return '' !== $ctx['base'] ? $ctx['base'] : $canonical;
	},
	20
);
add_filter(
	'ap_content_seo_data',
	function ( $data ) {
		if ( ap_content_listing_is_filtered() ) {
			$ctx               = ap_content_listing_context();
			$data['canonical'] = $ctx['base'];
			if ( '' !== $ctx['filter']['q'] ) {
				$data['noindex'] = true;
			}
		}
		return $data;
	}
);
/* Yoast's rel=next/prev would point at /page/N/ of the filter: fine. Search results stay out of the index. */
add_filter(
	'wpseo_robots',
	function ( $robots ) {
		if ( ap_content_listing_is_filtered() && '' !== ap_content_listing_filter()['q'] ) {
			return 'noindex, follow';
		}
		return $robots;
	},
	20
);
add_filter(
	'wpseo_robots_array',
	function ( $robots ) {
		if ( is_array( $robots ) && ap_content_listing_is_filtered() && '' !== ap_content_listing_filter()['q'] ) {
			$robots['index'] = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	},
	20
);

/* ── Old category addresses ──────────────────────────────────────────── */

/*
 * /category/<topic>/ (WordPress's own category archive) → the blog's topic tab, when the
 * category holds blog resources and no published core post (the import's pages linked to the
 * category archives; live has no such addresses).
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_category() || is_feed() || is_preview() ) {
			return;
		}
		try {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return;
			}
			$counts = ap_content_listing_counts();
			if ( empty( $counts[ 'category|' . $term->slug . '|blog' ] ) ) {
				return;
			}
			$core = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'cat'            => (int) $term->term_id,
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( $core ) {
				return;
			}
			$url   = ap_content_term_filter_url( 'category', $term->slug );
			$paged = (int) get_query_var( 'paged' );
			if ( $paged > 1 ) {
				$url .= 'page/' . $paged . '/';
			}
			wp_safe_redirect( $url, 301, 'Asset Panda Content' );
			exit;
		} catch ( \Throwable $e ) {
			return;
		}
	},
	2
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( ap_content_is_listing() ) {
			$classes[] = 'ap-content-listing';
			if ( ap_content_listing_is_filtered() ) {
				$classes[] = 'ap-content-filtered';
			}
		}
		return $classes;
	}
);
