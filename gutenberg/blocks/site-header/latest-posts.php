<?php
/**
 * AP Site Header — latest blog posts per Who We Help cabinet (adapter logic).
 *
 * NOT GENERATED, and NOT LOADED BY ITSELF. tools/gen-blocks.mjs owns
 * block.json and render.php in this folder and rewrites both from
 * ui/components/site-header/component.json; this file sits beside them, where
 * a regeneration never touches it. render.php only runs while the block
 * renders, which is too late for the save/publish hooks below, so the
 * bootstrap that registers the blocks must require this file on every
 * request:
 *
 *   Kit plugin (dist/asset-panda-ui-kit/asset-panda-ui-kit.php), after the
 *   require of src/loader.php:
 *     if ( is_readable( __DIR__ . '/blocks/site-header/latest-posts.php' ) ) {
 *         require_once __DIR__ . '/blocks/site-header/latest-posts.php';
 *     }
 *
 *   Theme bootstrap (gutenberg/inc/asset-panda-ui.php), after the loader:
 *     require_once get_stylesheet_directory() . '/blocks/site-header/latest-posts.php';
 *
 * Loading it twice, even from two copies, is harmless. Without it the header
 * still renders: every cabinet simply falls back to its customer-story card
 * (or to links only), exactly as before this feature.
 *
 * What it does
 *  1. render_block_data (assetpanda/site-header): for every tab whose key is
 *     in ap_sh_posts_map() (the seven Who We Help cabinets), adds `posts` —
 *     the two most recent published blog posts for that industry — to the
 *     tab's data before the generated render.php maps it to the kit. The kit
 *     stays presentation-only: it receives title, url, image, image_alt,
 *     image_width, image_height, date, datetime and category per post and
 *     draws them in place of the customer-story card.
 *  2. Caches each cabinet's picks in its own transient (ap_sh_posts_<key>,
 *     12 h safety TTL).
 *  3. When a blog post is published, updated, unpublished, trashed, deleted,
 *     re-filed or given a new featured image (or an industry/category is
 *     renamed), it drops those transients at once and, at the end of that
 *     request, rebuilds every cabinet's picks. Only if the picks actually
 *     changed does it fire `ap_sh_posts_changed` and purge WP Engine's page
 *     cache (the header is on every cached page) — so a new post reaches the
 *     menu on the next page view with no manual step, while editing an old
 *     post that is in no cabinet costs nothing.
 *
 * Content model (assetpanda1dev, read 2026-10-29): the blog is the `resource`
 * post type of the asset-panda-content plugin, filtered to `resource-types`
 * = blog. Industries are `resource-industry` (Education, Construction,
 * Healthcare, Banking & Finance, First Responders, Government, Non-Profit,
 * Religious Institutions, Manufacturing, Oil & Gas, Transportation, Legal,
 * Hotels, Real Estate — seeded from live) and the blog's `category` topics,
 * a few of which are industries (government, public-administration,
 * first-responders, education, healthcare, construction, oil-gas,
 * religious-institutions, non-profits). Every lookup checks that its
 * taxonomy exists, so on a site without the plugin the same code runs
 * against core `post` + `category`.
 *
 * A cabinet whose terms yield fewer than two blog posts is topped up with the
 * newest blog posts overall; ap_sh_posts_report() shows, per cabinet, how
 * many picks are such fallbacks (`wp eval 'print_r( ap_sh_posts_report() );'`).
 *
 * @package AssetPanda\Gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Load once, even when the theme and the kit plugin each ship and require a
 * copy. The guard has to be a conditional block, not an early `return`: PHP
 * binds a file's top-level functions while compiling it, so a
 * function_exists() test at the top of the same file is always true (and a
 * second copy would already have failed with "cannot redeclare"). Functions
 * declared inside the block are bound only when it runs.
 */
if ( ! function_exists( 'ap_sh_posts_map' ) ) :

/**
 * Cabinet (tab key) → the terms that make a post belong to that industry.
 *
 * `industries` are `resource-industry` slugs, `categories` are `category`
 * slugs; a post matching any of them counts. Keys are the tab keys from
 * component.json (sanitize_title of the key, as the kit computes them).
 *
 * Each list holds the terms that exist on the site today plus the slug
 * WordPress would give a term named after the cabinet (energy-utilities,
 * transportation-logistics, …): creating an Industry or Category with the
 * cabinet's name is enough to steer posts into it. Slugs that do not exist
 * are ignored by the query.
 *
 * @return array<string, array{industries: string[], categories: string[]}>
 */
function ap_sh_posts_map() {
	$map = array(
		'public-sector'                   => array(
			'industries' => array( 'government', 'first-responders', 'public-sector' ),
			'categories' => array( 'government', 'public-administration', 'first-responders', 'fire-responders', 'public-sector' ),
		),
		'education'                       => array(
			'industries' => array( 'education' ),
			'categories' => array( 'education' ),
		),
		'healthcare'                      => array(
			'industries' => array( 'healthcare' ),
			'categories' => array( 'healthcare' ),
		),
		'industrial-and-field-operations' => array(
			'industries' => array( 'construction', 'manufacturing', 'industry-field-operations' ),
			'categories' => array( 'construction', 'manufacturing', 'industry-field-operations' ),
		),
		'energy-and-utilities'            => array(
			'industries' => array( 'oil-and-gas', 'energy-utilities', 'utilities' ),
			'categories' => array( 'oil-gas', 'energy-utilities', 'utilities' ),
		),
		'transportation-and-logistics'    => array(
			'industries' => array( 'transportation', 'transportation-logistics', 'logistics' ),
			'categories' => array( 'transportation', 'transportation-logistics', 'logistics' ),
		),
		'business-and-nonprofit'          => array(
			'industries' => array( 'non-profit', 'religious-institutions', 'banking-finance', 'legal', 'hotels', 'real-estate', 'business-nonprofit' ),
			'categories' => array( 'non-profits', 'religious-institutions', 'business-nonprofit' ),
		),
	);

	/**
	 * Filters the cabinet → terms mapping.
	 *
	 * @param array $map Tab key => { industries: slugs, categories: slugs }.
	 */
	return (array) apply_filters( 'ap_sh_posts_map', $map );
}

/**
 * The post type that holds the blog: `resource` where the content plugin
 * registers it, else core `post`.
 *
 * @return string
 */
function ap_sh_posts_type() {
	return (string) apply_filters( 'ap_sh_posts_type', post_type_exists( 'resource' ) ? 'resource' : 'post' );
}

/**
 * Posts per cabinet.
 *
 * @return int
 */
function ap_sh_posts_count() {
	return max( 1, (int) apply_filters( 'ap_sh_posts_count', 4 ) ); // a 2×2 grid per cabinet (QA 2026-10-29)
}

/**
 * Safety TTL of a cabinet's cached picks. Changes are pushed by the hooks at
 * the end of this file; the TTL only bounds a change made behind WordPress's
 * back (a direct database import).
 *
 * @return int Seconds.
 */
function ap_sh_posts_ttl() {
	return max( 60, (int) apply_filters( 'ap_sh_posts_ttl', 12 * HOUR_IN_SECONDS ) );
}

/**
 * Transient key for a cabinet.
 *
 * @param string $key Tab key.
 * @return string
 */
function ap_sh_posts_cache_key( $key ) {
	// The count is part of the key, so changing it never serves a cached list of the old length.
	return 'ap_sh_posts_' . ap_sh_posts_count() . '_' . sanitize_key( $key );
}

/**
 * Limits a query to the blog (resource-types = blog) when that taxonomy exists.
 *
 * @return array|null tax_query clause, or null when there is no such taxonomy.
 */
function ap_sh_posts_blog_clause() {
	if ( ! taxonomy_exists( 'resource-types' ) ) {
		return null;
	}
	return array(
		'taxonomy' => 'resource-types',
		'field'    => 'slug',
		'terms'    => array( (string) apply_filters( 'ap_sh_posts_blog_type', 'blog' ) ),
	);
}

/**
 * Limits a query to a cabinet's industry: resource-industry OR category slugs.
 *
 * @param array $spec { industries: slugs, categories: slugs }.
 * @return array|null tax_query clause, or null when the spec names no usable term.
 */
function ap_sh_posts_industry_clause( array $spec ) {
	$any = array( 'relation' => 'OR' );
	if ( taxonomy_exists( 'resource-industry' ) && ! empty( $spec['industries'] ) ) {
		$any[] = array(
			'taxonomy' => 'resource-industry',
			'field'    => 'slug',
			'terms'    => array_values( array_map( 'strval', (array) $spec['industries'] ) ),
		);
	}
	if ( taxonomy_exists( 'category' ) && ! empty( $spec['categories'] ) ) {
		$any[] = array(
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => array_values( array_map( 'strval', (array) $spec['categories'] ) ),
		);
	}
	return count( $any ) > 1 ? $any : null;
}

/**
 * The newest published blog posts, optionally within an industry.
 *
 * @param array|null $spec    Cabinet spec, or null for the blog as a whole.
 * @param int        $count   How many.
 * @param int[]      $exclude Post ids already picked.
 * @return WP_Post[]
 */
function ap_sh_posts_query( $spec, $count, array $exclude = array() ) {
	$tax  = array( 'relation' => 'AND' );
	$blog = ap_sh_posts_blog_clause();
	if ( $blog ) {
		$tax[] = $blog;
	}
	if ( null !== $spec ) {
		$industry = ap_sh_posts_industry_clause( $spec );
		if ( ! $industry ) {
			return array();
		}
		$tax[] = $industry;
	}

	$args = array(
		'post_type'              => ap_sh_posts_type(),
		'post_status'            => 'publish',
		'posts_per_page'         => (int) $count,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_term_cache' => true,
		'update_post_meta_cache' => true,
	);
	if ( $exclude ) {
		$args['post__not_in'] = array_map( 'intval', $exclude );
	}
	if ( count( $tax ) > 1 ) {
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- two posts, cached in a transient.
	}

	/**
	 * Filters the WP_Query arguments for a cabinet's posts.
	 *
	 * @param array      $args WP_Query arguments.
	 * @param array|null $spec Cabinet spec, or null for the fallback query.
	 */
	$args = (array) apply_filters( 'ap_sh_posts_query_args', $args, $spec );

	$query = new WP_Query( $args );
	return array_values(
		array_filter(
			$query->posts,
			static function ( $post ) {
				return $post instanceof WP_Post;
			}
		)
	);
}

/**
 * Image for a post: the featured image; else the content plugin's resolution
 * (its imported posts keep their hero in `_ap_featured_image_url`); else the
 * first image in the content; else Yoast's social image. Width and height
 * are given when known so the kit can size the box before the image loads.
 *
 * @param WP_Post $post Post.
 * @return array{url: string, alt: string, width: int, height: int}
 */
function ap_sh_posts_image( WP_Post $post ) {
	$size = (string) apply_filters( 'ap_sh_posts_image_size', 'medium_large' );
	$none = array(
		'url'    => '',
		'alt'    => '',
		'width'  => 0,
		'height' => 0,
	);

	$thumb = (int) get_post_thumbnail_id( $post );
	if ( $thumb ) {
		$src = wp_get_attachment_image_src( $thumb, $size );
		if ( $src && ! empty( $src[0] ) ) {
			return array(
				'url'    => (string) $src[0],
				'alt'    => trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ),
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	if ( function_exists( 'ap_content_post_image' ) ) {
		$got = ap_content_post_image( $post->ID, $size );
		if ( is_array( $got ) && ! empty( $got['url'] ) ) {
			return array(
				'url'    => (string) $got['url'],
				'alt'    => (string) ( $got['alt'] ?? '' ),
				'width'  => (int) ( $got['w'] ?? 0 ),
				'height' => (int) ( $got['h'] ?? 0 ),
			);
		}
	} else {
		$external = trim( (string) get_post_meta( $post->ID, '_ap_featured_image_url', true ) );
		if ( '' !== $external && '' !== esc_url_raw( $external ) ) {
			return array(
				'url'    => esc_url_raw( $external ),
				'alt'    => trim( (string) get_post_meta( $post->ID, '_ap_featured_image_alt', true ) ),
				'width'  => 0,
				'height' => 0,
			);
		}
	}

	if ( preg_match( '/<img\b[^>]*>/i', (string) $post->post_content, $tag ) ) {
		$tag = $tag[0];
		if ( preg_match( '/\bwp-image-(\d+)/', $tag, $id ) ) {
			$src = wp_get_attachment_image_src( (int) $id[1], $size );
			if ( $src && ! empty( $src[0] ) ) {
				return array(
					'url'    => (string) $src[0],
					'alt'    => trim( (string) get_post_meta( (int) $id[1], '_wp_attachment_image_alt', true ) ),
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}
		if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src ) ) {
			$url = esc_url_raw( html_entity_decode( $src[1], ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $url && 0 !== stripos( $url, 'data:' ) ) {
				return array(
					'url'    => $url,
					'alt'    => preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $alt ) ? html_entity_decode( $alt[1], ENT_QUOTES, 'UTF-8' ) : '',
					'width'  => preg_match( '/\bwidth=["\']?(\d+)/i', $tag, $w ) ? (int) $w[1] : 0,
					'height' => preg_match( '/\bheight=["\']?(\d+)/i', $tag, $h ) ? (int) $h[1] : 0,
				);
			}
		}
	}

	$og = esc_url_raw( trim( (string) get_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', true ) ) );
	if ( '' !== $og ) {
		return array(
			'url'    => $og,
			'alt'    => '',
			'width'  => 0,
			'height' => 0,
		);
	}

	return $none;
}

/**
 * The label shown over a post: the industry term that matched the cabinet,
 * else the matched category, else the post's first industry or category.
 *
 * @param WP_Post    $post Post.
 * @param array|null $spec Cabinet spec, or null for a fallback pick.
 * @return string
 */
function ap_sh_posts_label( WP_Post $post, $spec ) {
	$passes = array();
	if ( is_array( $spec ) ) {
		$passes[] = array( 'resource-industry', array_map( 'strval', (array) ( $spec['industries'] ?? array() ) ) );
		$passes[] = array( 'category', array_map( 'strval', (array) ( $spec['categories'] ?? array() ) ) );
	}
	$passes[] = array( 'resource-industry', array() );
	$passes[] = array( 'category', array() );

	foreach ( $passes as $pass ) {
		list( $taxonomy, $slugs ) = $pass;
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, array( 'uncategorized', 'blog' ), true ) && ! $slugs ) {
				continue;
			}
			if ( ! $slugs || in_array( $term->slug, $slugs, true ) ) {
				return html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}
	}
	return 'Blog';
}

/**
 * One post as the kit's `posts` entry.
 *
 * @param WP_Post    $post Post.
 * @param array|null $spec Cabinet spec, or null for a fallback pick.
 * @return array
 */
function ap_sh_posts_entry( WP_Post $post, $spec ) {
	$image = ap_sh_posts_image( $post );
	$when  = get_post_datetime( $post );

	return array(
		'title'        => html_entity_decode( wp_strip_all_tags( (string) get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		'url'          => (string) get_permalink( $post ),
		'image'        => $image['url'],
		'image_alt'    => $image['alt'],
		'image_width'  => $image['width'],
		'image_height' => $image['height'],
		'date'         => (string) get_the_date( 'M j, Y', $post ),
		'datetime'     => $when ? $when->format( DATE_W3C ) : '',
		'category'     => ap_sh_posts_label( $post, $spec ),
	);
}

/**
 * A cabinet's picks, freshly queried (no cache): the newest blog posts in its
 * industry, topped up with the newest blog posts overall when the industry
 * has fewer than ap_sh_posts_count().
 *
 * @param string $key  Tab key (unused by the query; kept for filters and reports).
 * @param array  $spec Cabinet spec.
 * @return array{items: array[], fallback: int, at: int}
 */
function ap_sh_posts_build( $key, array $spec ) {
	$count    = ap_sh_posts_count();
	$items    = array();
	$ids      = array();
	$fallback = 0;

	foreach ( ap_sh_posts_query( $spec, $count ) as $post ) {
		$items[] = ap_sh_posts_entry( $post, $spec );
		$ids[]   = $post->ID;
	}
	if ( count( $items ) < $count ) {
		foreach ( ap_sh_posts_query( null, $count - count( $items ), $ids ) as $post ) {
			$items[] = ap_sh_posts_entry( $post, null );
			++$fallback;
		}
	}

	return array(
		'items'    => $items,
		'fallback' => $fallback,
		'at'       => time(),
	);
}

/**
 * A cabinet's posts, from its transient or built and cached.
 *
 * @param string $key  Tab key.
 * @param array  $spec Cabinet spec.
 * @return array[] Kit `posts` entries.
 */
function ap_sh_latest_posts( $key, array $spec ) {
	$cached = get_transient( ap_sh_posts_cache_key( $key ) );
	if ( is_array( $cached ) && isset( $cached['items'] ) && is_array( $cached['items'] ) ) {
		return $cached['items'];
	}

	$built = ap_sh_posts_build( $key, $spec );
	set_transient( ap_sh_posts_cache_key( $key ), $built, ap_sh_posts_ttl() );
	return $built['items'];
}

/**
 * Per cabinet: how many picks are cached, how many of them are fallbacks
 * (newest posts overall because the industry had too few), and the picks'
 * titles. For checking a site's tagging after a deploy:
 * `wp eval 'print_r( ap_sh_posts_report() );'`.
 *
 * @return array<string, array{picked: int, fallback: int, titles: string[]}>
 */
function ap_sh_posts_report() {
	$out = array();
	foreach ( ap_sh_posts_map() as $key => $spec ) {
		if ( ! is_array( $spec ) ) {
			continue;
		}
		ap_sh_latest_posts( (string) $key, $spec );
		$cached      = get_transient( ap_sh_posts_cache_key( $key ) );
		$items       = is_array( $cached ) && isset( $cached['items'] ) ? (array) $cached['items'] : array();
		$out[ $key ] = array(
			'picked'   => count( $items ),
			'fallback' => is_array( $cached ) && isset( $cached['fallback'] ) ? (int) $cached['fallback'] : 0,
			'titles'   => array_map(
				static function ( $item ) {
					return ( $item['date'] ?? '' ) . ' — ' . ( $item['category'] ?? '' ) . ' — ' . ( $item['title'] ?? '' );
				},
				$items
			),
		);
	}
	return $out;
}

/**
 * Adds `posts` to every mapped tab of the site-header block before it renders.
 *
 * The block is normally rendered from its defaults (the header template part
 * holds a bare <!-- wp:assetpanda/site-header /-->), so the nav is read from
 * the registered block type when the parsed block carries none. A tab that
 * already has posts (an editor override) is left alone.
 *
 * @param array $parsed_block Parsed block.
 * @return array
 */
function ap_sh_posts_inject( $parsed_block ) {
	if ( ! is_array( $parsed_block ) || empty( $parsed_block['blockName'] ) || 'assetpanda/site-header' !== $parsed_block['blockName'] ) {
		return $parsed_block;
	}

	$nav = isset( $parsed_block['attrs']['nav'] ) && is_array( $parsed_block['attrs']['nav'] ) ? $parsed_block['attrs']['nav'] : null;
	if ( null === $nav ) {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'assetpanda/site-header' );
		$nav  = $type && isset( $type->attributes['nav']['default'] ) && is_array( $type->attributes['nav']['default'] )
			? $type->attributes['nav']['default']
			: array();
	}
	if ( ! $nav ) {
		return $parsed_block;
	}

	$map     = ap_sh_posts_map();
	$changed = false;

	foreach ( $nav as &$item ) {
		if ( ! is_array( $item ) || empty( $item['tabs'] ) || ! is_array( $item['tabs'] ) ) {
			continue;
		}
		foreach ( $item['tabs'] as &$tab ) {
			if ( ! is_array( $tab ) || ! empty( $tab['posts'] ) ) {
				continue;
			}
			$key = trim( (string) ( $tab['key'] ?? '' ) );
			$key = sanitize_title( '' !== $key ? $key : (string) ( $tab['label'] ?? '' ) );
			if ( '' === $key || ! isset( $map[ $key ] ) || ! is_array( $map[ $key ] ) ) {
				continue;
			}
			$posts = ap_sh_latest_posts( $key, $map[ $key ] );
			if ( $posts ) {
				$tab['posts'] = $posts;
				$changed      = true;
			}
		}
		unset( $tab );
	}
	unset( $item );

	if ( $changed ) {
		if ( ! isset( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
			$parsed_block['attrs'] = array();
		}
		$parsed_block['attrs']['nav'] = $nav;
	}
	return $parsed_block;
}
add_filter( 'render_block_data', 'ap_sh_posts_inject', 10 );

/**
 * Something the menu may show has changed: forget every cabinet's picks now
 * and rebuild them once, at the end of this request, when every write of the
 * save (terms, meta, featured image) has landed.
 */
function ap_sh_posts_mark_dirty() {
	static $marked = false;
	if ( $marked ) {
		return;
	}
	$marked = true;

	foreach ( array_keys( ap_sh_posts_map() ) as $key ) {
		delete_transient( ap_sh_posts_cache_key( (string) $key ) );
	}
	add_action( 'shutdown', 'ap_sh_posts_refresh', 1 );
}

/**
 * Rebuilds and caches every cabinet's picks; when they differ from the last
 * published set, fires `ap_sh_posts_changed` and purges the host's page cache
 * so the change reaches every cached page (the header is on all of them).
 */
function ap_sh_posts_refresh() {
	try {
		$all = array();
		foreach ( ap_sh_posts_map() as $key => $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}
			$built = ap_sh_posts_build( (string) $key, $spec );
			set_transient( ap_sh_posts_cache_key( (string) $key ), $built, ap_sh_posts_ttl() );
			$all[ $key ] = $built['items'];
		}

		$sig = md5( (string) wp_json_encode( $all ) );
		if ( get_option( 'ap_sh_posts_sig' ) === $sig ) {
			return; // The menu shows the same posts as before: nothing to purge.
		}
		update_option( 'ap_sh_posts_sig', $sig, false );

		/**
		 * Fires when the Who We Help posts change, after the new picks are cached.
		 * Hook a non-WP Engine cache purge here.
		 *
		 * @param array $all Tab key => kit `posts` entries.
		 */
		do_action( 'ap_sh_posts_changed', $all );

		ap_sh_posts_purge_host();
	} catch ( \Throwable $e ) {
		// Leave nothing half-built: the next page view rebuilds from scratch.
		foreach ( array_keys( ap_sh_posts_map() ) as $key ) {
			delete_transient( ap_sh_posts_cache_key( (string) $key ) );
		}
	}
}

/**
 * WP Engine page-cache purge (no-op elsewhere). The object cache is left
 * alone: the picks were just rewritten in it, so nothing there is stale.
 */
function ap_sh_posts_purge_host() {
	if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
		WpeCommon::purge_varnish_cache();
	}
}

/**
 * Whether a post is one the menu could show (any status).
 *
 * @param int|WP_Post|null $post Post.
 * @return bool
 */
function ap_sh_posts_is_blog_post( $post ) {
	$post = get_post( $post );
	return $post instanceof WP_Post && ap_sh_posts_type() === $post->post_type;
}

/**
 * Whether a post is a published one the menu could show.
 *
 * @param int|WP_Post|null $post Post.
 * @return bool
 */
function ap_sh_posts_is_live_blog_post( $post ) {
	$post = get_post( $post );
	return $post instanceof WP_Post && ap_sh_posts_type() === $post->post_type && 'publish' === $post->post_status;
}

// Publish, unpublish, schedule going live, trash.
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status || ! ap_sh_posts_is_blog_post( $post ) ) {
			return;
		}
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			ap_sh_posts_mark_dirty();
		}
	},
	10,
	3
);

// Edits to a published post (title, slug, date, content image).
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ap_sh_posts_is_live_blog_post( $post ) ) {
			ap_sh_posts_mark_dirty();
		}
	},
	20,
	2
);

// Permanent deletion of a post that was still published (a trashed post left the menu when it was trashed).
add_action(
	'deleted_post',
	static function ( $post_id, $post = null ) {
		if ( $post instanceof WP_Post && ap_sh_posts_type() === $post->post_type && 'publish' === $post->post_status ) {
			ap_sh_posts_mark_dirty();
		}
	},
	10,
	2
);

// Re-filed under another industry, category or resource type.
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'category', 'resource-industry', 'resource-types' ), true ) && ap_sh_posts_is_live_blog_post( $object_id ) ) {
			ap_sh_posts_mark_dirty();
		}
	},
	10,
	4
);

// Featured image set, changed or removed.
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $ap_sh_meta_hook ) {
	add_action(
		$ap_sh_meta_hook,
		static function ( $meta_id, $object_id, $meta_key ) {
			if ( in_array( $meta_key, array( '_thumbnail_id', '_ap_featured_image_url' ), true ) && ap_sh_posts_is_live_blog_post( $object_id ) ) {
				ap_sh_posts_mark_dirty();
			}
		},
		10,
		3
	);
}
unset( $ap_sh_meta_hook );

// An industry or category renamed or removed (the label over a post).
foreach ( array( 'edited_term', 'delete_term' ) as $ap_sh_term_hook ) {
	add_action(
		$ap_sh_term_hook,
		static function ( $term_id, $tt_id, $taxonomy ) {
			if ( in_array( $taxonomy, array( 'category', 'resource-industry', 'resource-types' ), true ) ) {
				ap_sh_posts_mark_dirty();
			}
		},
		10,
		3
	);
}
unset( $ap_sh_term_hook );

endif; // function_exists( 'ap_sh_posts_map' ).
