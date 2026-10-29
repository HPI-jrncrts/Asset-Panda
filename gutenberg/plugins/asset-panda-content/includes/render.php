<?php
/**
 * Type-aware output for the 2026 blog design's blocks on resource and
 * pressroom views.
 *
 * The design's markup and CSS belong to "AP Blog 2026 Templates" (dev Code
 * Snippet 6; exported copy in the AP-GEO-Studio repo,
 * integrations/wordpress/ap26-blog-templates, commit cf010d0, sha256
 * 2ea9df11…). Its blocks are written for core posts: the hero crumbs say
 * "Resources (text) › Blog", the eyebrow is the post category, related posts
 * are core posts of that category and cards fall back to a "Blog" label.
 * The functions below produce the SAME markup and classes (so the snippet's
 * stylesheet styles them unchanged) with resource-aware values. They call the
 * snippet's own helpers (ap26_icon, ap26_share_icons) when present, so icons
 * and share links stay the snippet's. Keep them in step with the snippet when
 * its markup changes.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippet 6 icon, or a plain fallback.
 *
 * @param string $name Icon name.
 * @return string
 */
function ap_content_icon( $name ) {
	if ( function_exists( 'ap26_icon' ) ) {
		return ap26_icon( $name );
	}
	return 'chevron-right' === $name ? '<span aria-hidden="true">&rsaquo;</span>' : '';
}

/**
 * Snippet 6 share icons.
 *
 * @param int $post_id Post.
 * @return string
 */
function ap_content_share_icons( $post_id ) {
	return function_exists( 'ap26_share_icons' ) ? ap26_share_icons( $post_id ) : '';
}

/**
 * Breadcrumbs in the design's markup. The last item is the current page.
 *
 * @param array $items [ [label, url?], … ].
 * @return string
 */
function ap_content_crumbs( $items ) {
	$home = ap_content_icon( 'home' );
	$out  = '<nav class="ap26-crumbs" aria-label="Breadcrumb">';
	$out .= '<a class="ap26-crumbs__home" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Home">' . ( '' !== $home ? $home : 'Home' ) . '</a>';
	$last = count( $items ) - 1;
	foreach ( array_values( $items ) as $i => $item ) {
		$out .= ap_content_icon( 'chevron-right' );
		if ( $i === $last ) {
			$out .= '<span class="ap26-crumbs__current">' . esc_html( $item[0] ) . '</span>';
		} elseif ( ! empty( $item[1] ) ) {
			$out .= '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>';
		} else {
			$out .= '<span>' . esc_html( $item[0] ) . '</span>';
		}
	}
	return $out . '</nav>';
}

/**
 * A Resource Type's listing heading: term meta ap_heading, else live's hub H1.
 *
 * @param WP_Term $term Term.
 * @return string
 */
function ap_content_type_heading( $term ) {
	$heading = (string) get_term_meta( $term->term_id, 'ap_heading', true );
	if ( '' !== $heading ) {
		return $heading;
	}
	$defaults = ap_content_type_defaults();
	return isset( $defaults[ $term->slug ] ) ? $defaults[ $term->slug ]['heading'] : $term->name;
}

/**
 * A post type archive's heading (Resource Center / Press Room).
 *
 * @param string $pt Post type.
 * @return string
 */
function ap_content_archive_heading( $pt ) {
	$headings = get_option( 'ap_content_archive_headings' );
	if ( is_array( $headings ) && ! empty( $headings[ $pt ] ) ) {
		return (string) $headings[ $pt ];
	}
	return 'newsroom' === $pt ? 'Press Room' : 'Resource Center';
}

/**
 * Hero label, breadcrumb trail and listing for a resource or press release.
 *
 * @param WP_Post $post Post.
 * @return array{eyebrow: string, trail: array, tag: string, tag_url: string}
 */
function ap_content_post_context( $post ) {
	if ( 'newsroom' === $post->post_type ) {
		$url = get_post_type_archive_link( 'newsroom' );
		return array(
			'eyebrow' => (string) apply_filters( 'ap_content_press_label', 'Press Release' ),
			'trail'   => array( array( ap_content_archive_heading( 'newsroom' ), $url ) ),
			'tag'     => (string) apply_filters( 'ap_content_press_label', 'Press Release' ),
			'tag_url' => $url,
		);
	}
	$term  = ap_content_primary_type( $post );
	$trail = array( array( 'Resources', get_post_type_archive_link( 'resource' ) ) );
	if ( $term ) {
		$trail[] = array( ap_content_type_heading( $term ), ap_content_type_url( $term ) );
	}
	return array(
		'eyebrow' => $term ? $term->name : '',
		'trail'   => $trail,
		'tag'     => $term ? $term->name : '',
		'tag_url' => $term ? ap_content_type_url( $term ) : '',
	);
}

/** ap26/post-header on a resource / press release. */
function ap_content_render_post_header() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$id    = $post->ID;
	$ctx   = ap_content_post_context( $post );
	$title = get_the_title( $id );
	$trail = $ctx['trail'];
	$trail[] = array( wp_strip_all_tags( $title ) );
	$out  = '<header class="ap26-post-header">' . ap_content_crumbs( $trail );
	$out .= '<div class="ap26-post-header__head">';
	if ( '' !== $ctx['eyebrow'] ) {
		$out .= '<p class="ap26-eyebrow">' . esc_html( $ctx['eyebrow'] ) . '</p>';
	}
	$out .= '<h1 class="ap26-post-header__title">' . esc_html( $title ) . '</h1>';
	$out .= ap_content_share_icons( $id ) . '</div>';
	if ( has_post_thumbnail( $id ) ) {
		$out .= '<figure class="ap26-post-header__media">' . get_the_post_thumbnail( $id, 'full', array( 'class' => 'ap26-post-header__img' ) ) . '</figure>';
	}
	return $out . '</header>';
}

/**
 * 1.3.4 — The post design's header (Figma Blog Detail 5434:79954 / compare post
 * 7219:87997) for a blog or compare resource, drawn as the 2026 templates' own
 * ap26/post-header (crumbs, eyebrow, H1, "Share on:" row, featured image) from
 * the props listing.php builds for the kit's resource-header. Used while the
 * installed kit's resource-header has no "article" variant.
 *
 * The header sits in the same full-width .ap26-post-header wrapper as live's
 * band, so the design pass's glow (snippet 8, on that class) spans the page and
 * the sticky title bar still finds it; ap-content.css switches the inner
 * header's own glow off and sets its spacing to the design's.
 *
 * @param array   $props resource-header props (title, crumbs, label, image…, share_url).
 * @param WP_Post $post  The resource.
 * @return string
 */
function ap_content_render_article_header( $props, $post ) {
	$title = (string) ( $props['title'] ?? '' );
	$trail = array();
	foreach ( array_values( (array) ( $props['crumbs'] ?? array() ) ) as $i => $crumb ) {
		$label = is_array( $crumb ) ? trim( (string) ( $crumb['label'] ?? '' ) ) : '';
		// ap_content_crumbs() draws Home as the leading icon.
		if ( '' === $label || ( 0 === $i && ( 'home' === ( $crumb['icon'] ?? '' ) || 'Home' === $label ) ) ) {
			continue;
		}
		$trail[] = array( $label, (string) ( $crumb['url'] ?? '' ) );
	}
	$trail[] = array( $title );
	$out     = '<div class="ap26-post-header ap-content-header ap-content-header--ap26"><header class="ap26-post-header">' . ap_content_crumbs( $trail );
	$out    .= '<div class="ap26-post-header__head">';
	if ( '' !== trim( (string) ( $props['label'] ?? '' ) ) ) {
		$out .= '<p class="ap26-eyebrow">' . esc_html( (string) $props['label'] ) . '</p>';
	}
	$out .= '<h1 class="ap26-post-header__title">' . esc_html( $title ) . '</h1>';
	if ( '' !== trim( (string) ( $props['share_url'] ?? '' ) ) ) {
		$out .= ap_content_share_icons( $post->ID );
	}
	$out .= '</div>';
	$img  = trim( (string) ( $props['image'] ?? '' ) );
	if ( '' !== $img ) {
		if ( function_exists( 'ap_ui_preload_image' ) ) {
			$out .= ap_ui_preload_image( $img );
		}
		$w    = (int) ( $props['image_w'] ?? 0 );
		$h    = (int) ( $props['image_h'] ?? 0 );
		$out .= '<figure class="ap26-post-header__media"><img class="ap26-post-header__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( (string) ( $props['image_alt'] ?? '' ) ) . '"'
			. ( $w > 0 && $h > 0 ? ' width="' . $w . '" height="' . $h . '"' : '' ) . ' fetchpriority="high" decoding="async" /></figure>';
	}
	return $out . '</header></div>';
}

/** ap26/sticky-bar on a resource / press release. */
function ap_content_render_sticky_bar() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$ctx  = ap_content_post_context( $post );
	$out  = '<div class="ap26-sticky" id="ap26-sticky" aria-hidden="true"><div class="ap26-sticky__inner"><div class="ap26-sticky__titles">';
	if ( '' !== $ctx['eyebrow'] ) {
		$out .= '<span class="ap26-sticky__eyebrow">' . esc_html( $ctx['eyebrow'] ) . '</span>';
	}
	$out .= '<span class="ap26-sticky__title">' . esc_html( get_the_title( $post->ID ) ) . '</span></div>';
	return $out . ap_content_share_icons( $post->ID ) . '</div></div>';
}

/**
 * Card text: the excerpt, else Yoast's description, else the body's text
 * (never through the_content).
 *
 * @param WP_Post $post Post.
 * @return string
 */
function ap_content_card_text( $post ) {
	$text = trim( (string) $post->post_excerpt );
	if ( '' === $text ) {
		$text = trim( (string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ) );
	}
	if ( '' === $text ) {
		$text = wp_strip_all_tags( excerpt_remove_blocks( strip_shortcodes( (string) $post->post_content ) ) );
	}
	return wp_trim_words( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), 48 );
}

/**
 * One card, in snippet 6's ap26_card markup. Label: the resource's industry
 * category when it has one, else its Resource Type ("Press Release" for the
 * newsroom).
 *
 * @param WP_Post $post Post.
 * @return string
 */
function ap_content_card( $post ) {
	$id   = $post->ID;
	$link = get_permalink( $post );
	$tag  = '';
	if ( 'resource' === $post->post_type ) {
		$cats    = get_the_category( $id );
		$default = (int) get_option( 'default_category' );
		foreach ( (array) $cats as $cat ) {
			if ( (int) $cat->term_id !== $default ) {
				$tag = '<a class="ap26-card__tag" href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
				break;
			}
		}
	}
	if ( '' === $tag ) {
		$ctx = ap_content_post_context( $post );
		$tag = '' !== $ctx['tag']
			? '<a class="ap26-card__tag" href="' . esc_url( $ctx['tag_url'] ) . '">' . esc_html( $ctx['tag'] ) . '</a>'
			: '<span class="ap26-card__tag">Resource</span>';
	}
	$img = get_the_post_thumbnail(
		$id,
		'large',
		array(
			'class'   => 'ap26-card__img',
			'loading' => 'lazy',
		)
	);
	if ( ! $img ) {
		$img = '<span class="ap26-card__img ap26-card__img--placeholder" aria-hidden="true"></span>';
	}
	$out  = '<article class="ap26-card">';
	$out .= '<a class="ap26-card__media" href="' . esc_url( $link ) . '" aria-hidden="true" tabindex="-1">' . $img . '</a>';
	$out .= '<div class="ap26-card__body">' . $tag;
	$out .= '<h3 class="ap26-card__title"><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>';
	$out .= '<p class="ap26-card__excerpt">' . esc_html( ap_content_card_text( $post ) ) . '</p>';
	$out .= '<a class="ap26-card__more" href="' . esc_url( $link ) . '">Read More ' . ap_content_icon( 'arrow-right-long' ) . '</a>';
	return $out . '</div></article>';
}

/** ap26/related-posts: three of the same Resource Type (press releases: the latest three). */
function ap_content_render_related() {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$args = array(
		'post_type'           => $post->post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'post__not_in'        => array( $post->ID ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( 'resource' === $post->post_type ) {
		$term = ap_content_primary_type( $post );
		if ( ! $term ) {
			return '';
		}
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'resource-types',
				'field'    => 'term_id',
				'terms'    => array( (int) $term->term_id ),
			),
		);
	}
	$args = (array) apply_filters( 'ap_content_related_query_args', $args, $post );
	$q    = new WP_Query( $args );
	if ( ! $q->posts ) {
		return '';
	}
	$heading = (string) apply_filters( 'ap_content_related_heading', 'Related News & Press', $post );
	$out     = '<section class="ap26-related"><h2 class="ap26-related__heading">' . esc_html( $heading ) . '</h2><div class="ap26-cards__grid ap26-cards__grid--related">';
	foreach ( $q->posts as $p ) {
		$out .= ap_content_card( $p );
	}
	return $out . '</div></section>';
}

/** ap26/category-hero on a Resource Type, the resource listing or the pressroom. */
function ap_content_render_archive_hero() {
	$eyebrow = 'Resource Center';
	if ( is_tax( 'resource-types' ) ) {
		$term    = get_queried_object();
		$heading = $term instanceof WP_Term ? ap_content_type_heading( $term ) : '';
		$trail   = array( array( 'Resources', get_post_type_archive_link( 'resource' ) ), array( $heading ) );
	} elseif ( is_post_type_archive( 'newsroom' ) ) {
		$heading = ap_content_archive_heading( 'newsroom' );
		$eyebrow = 'Newsroom';
		$trail   = array( array( $heading ) );
	} else {
		$heading = ap_content_archive_heading( 'resource' );
		$trail   = array( array( 'Resources' ) );
	}
	$out  = '<section class="ap26-cat-hero">' . ap_content_crumbs( $trail );
	$out .= '<div class="ap26-cat-hero__head"><p class="ap26-eyebrow">' . esc_html( (string) apply_filters( 'ap_content_archive_eyebrow', $eyebrow ) ) . '</p>';
	return $out . '<h1 class="ap26-cat-hero__title">' . esc_html( $heading ) . '</h1></div></section>';
}

/** ap26/blog-grid: the main query's items as cards, with the snippet's pagination. */
function ap_content_render_grid() {
	$out = '<section class="ap26-cards"><div class="ap26-cards__grid">';
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			$out .= ap_content_card( get_post() );
		}
		rewind_posts();
		wp_reset_postdata();
	} else {
		$out .= '<p class="ap26-cards__empty">No articles found.</p>';
	}
	$out  .= '</div>';
	$links = paginate_links(
		array(
			'type'      => 'array',
			'mid_size'  => 1,
			'end_size'  => 2,
			'prev_text' => ap_content_icon( 'arrow-left' ) . '<span>Previous</span>',
			'next_text' => '<span>Next</span>' . ap_content_icon( 'arrow-right' ),
		)
	);
	if ( $links ) {
		$prev = '';
		$next = '';
		$nums = '';
		foreach ( $links as $link ) {
			if ( false !== strpos( $link, 'prev ' ) ) {
				$prev = $link;
				continue;
			}
			if ( false !== strpos( $link, 'next ' ) ) {
				$next = $link;
				continue;
			}
			$nums .= $link;
		}
		$out .= '<nav class="ap26-pagination" aria-label="Pagination"><div class="ap26-pagination__prev">';
		$out .= $prev ? $prev : '<span class="ap26-pagination__spacer"></span>';
		$out .= '</div><div class="ap26-pagination__numbers">' . $nums . '</div><div class="ap26-pagination__next">';
		$out .= $next ? $next : '<span class="ap26-pagination__spacer"></span>';
		$out .= '</div></nav>';
	}
	return $out . '</section>';
}

/**
 * ap26/blog-hero on /resource-center/: the snippet's hero with "All" pointing
 * at (and active on) the resource listing, and a site-wide search.
 */
function ap_content_render_blog_hero() {
	$cats    = get_categories(
		array(
			'orderby'    => 'name',
			'order'      => 'ASC',
			'hide_empty' => true,
		)
	);
	$visible = array_slice( $cats, 0, 6 );
	$rest    = array_slice( $cats, 6 );
	$out     = '<section class="ap26-blog-hero"><div class="ap26-blog-hero__inner">';
	$out    .= '<p class="ap26-eyebrow">Resource Center</p>';
	$out    .= '<h1 class="ap26-blog-hero__title">Expert Insights for Every Asset</h1>';
	$out    .= '<p class="ap26-blog-hero__subtitle">Explore practical guides, expert insights, industry trends, and proven strategies to help you manage assets more efficiently, improve compliance, and make smarter operational decisions.</p>';
	$out    .= '<form role="search" method="get" class="ap26-search" action="' . esc_url( home_url( '/' ) ) . '">' . ap_content_icon( 'search' );
	$out    .= '<input type="search" name="s" placeholder="Search" value="' . esc_attr( get_search_query() ) . '" aria-label="Search the resource center" /></form></div>';
	$out    .= '<div class="ap26-tabs"><div class="ap26-tabs__rail">';
	$out    .= '<a class="ap26-tab is-active" href="' . esc_url( get_post_type_archive_link( 'resource' ) ) . '">All</a>';
	foreach ( $visible as $cat ) {
		$out .= '<a class="ap26-tab" href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
	}
	$out .= '</div>';
	if ( $rest ) {
		$out .= '<div class="ap26-tabs__moremenu"><button type="button" class="ap26-tabs__more" aria-expanded="false" aria-label="More categories">' . ap_content_icon( 'dots' ) . '</button><div class="ap26-tabs__dropdown" hidden>';
		foreach ( $rest as $cat ) {
			$out .= '<a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a>';
		}
		$out .= '</div></div>';
	}
	return $out . '</div></section>';
}

/** ap-content/page-hero: the legacy page's title hero (category-hero look). */
function ap_content_render_page_hero() {
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		$post = get_queried_object();
	}
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$trail = array();
	foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
		$trail[] = array( wp_strip_all_tags( get_the_title( $ancestor ) ), get_permalink( $ancestor ) );
	}
	$title   = get_the_title( $post );
	$trail[] = array( wp_strip_all_tags( $title ) );
	$out     = '<section class="ap26-cat-hero ap-content-page-hero">' . ap_content_crumbs( $trail );
	return $out . '<div class="ap26-cat-hero__head"><h1 class="ap26-cat-hero__title">' . esc_html( $title ) . '</h1></div></section>';
}

/**
 * True when the post has a usable form id for the panel.
 *
 * @param int $post_id Post.
 * @return bool
 */
function ap_content_has_gate( $post_id ) {
	$form = trim( (string) get_post_meta( (int) $post_id, '_ap_gate_form_id', true ) );
	return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $form );
}

/**
 * The panel's anchor: #get-the-white-paper on resources (1.0), #ap-gate on pages.
 *
 * @param int $post_id Post.
 * @return string
 */
function ap_content_gate_anchor( $post_id ) {
	return 'resource' === get_post_type( $post_id ) ? 'get-the-white-paper' : 'ap-gate';
}

/**
 * The optional button that scrolls to the panel (`_ap_gate_button_label`),
 * in core button markup so it matches the page's other buttons. It goes in
 * as the first button of the body's first Buttons block (where live's popup
 * buttons sat beside the other CTAs), else in its own Buttons block at the
 * top of the body.
 *
 * @param string $content Rendered body.
 * @param int    $post_id Post.
 * @return string
 */
function ap_content_gate_jump( $content, $post_id ) {
	$label = trim( (string) get_post_meta( $post_id, '_ap_gate_button_label', true ) );
	if ( '' === $label || ! ap_content_has_gate( $post_id ) || false !== strpos( $content, 'ap-content-gate-jump' ) ) {
		return $content;
	}
	$button = '<div class="wp-block-button ap-content-gate-jump-wrap"><a class="wp-block-button__link wp-element-button ap-content-gate-jump" href="#'
		. esc_attr( ap_content_gate_anchor( $post_id ) ) . '">' . esc_html( $label ) . '</a></div>';
	if ( preg_match( '/<div\s+class="(?:[^"]*\s)?wp-block-buttons(?:\s[^"]*)?"[^>]*>/', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		$at = $m[0][1] + strlen( $m[0][0] );
		return substr( $content, 0, $at ) . $button . substr( $content, $at );
	}
	return '<div class="wp-block-buttons ap-content-gate-jump-row">' . $button . '</div>' . $content;
}

/**
 * The form panel (white papers since 1.0, pages since 1.1): a CTA box holding
 * the HubSpot form.
 *
 * @param int $post_id Post.
 * @return string
 */
function ap_content_gate_html( $post_id ) {
	if ( ! ap_content_has_gate( $post_id ) ) {
		return '';
	}
	$form    = trim( (string) get_post_meta( $post_id, '_ap_gate_form_id', true ) );
	$portal  = trim( (string) get_post_meta( $post_id, '_ap_gate_portal_id', true ) );
	$portal  = preg_match( '/^\d{3,12}$/', $portal ) ? $portal : '4155061';
	$region  = trim( (string) get_post_meta( $post_id, '_ap_gate_region', true ) );
	$region  = preg_match( '/^[a-z0-9]{2,6}$/', $region ) ? $region : 'na2';
	$heading = trim( (string) get_post_meta( $post_id, '_ap_gate_heading', true ) );
	if ( '' === $heading && 'resource' === get_post_type( $post_id ) ) {
		$heading = 'Get The White Paper';
	}
	$text    = trim( (string) get_post_meta( $post_id, '_ap_gate_text', true ) );
	$target  = 'ap-gate-form-' . (int) $post_id;

	wp_enqueue_script( 'ap-content-hsforms', 'https://js.hsforms.net/forms/embed/v2.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_script(
		'ap-content-hsforms',
		'(function(){try{if(window.hbspt&&hbspt.forms){hbspt.forms.create(' . wp_json_encode(
			array(
				'region'   => $region,
				'portalId' => $portal,
				'formId'   => $form,
				'target'   => '#' . $target,
			)
		) . ');}}catch(e){}})();'
	);

	$out = '<aside class="ap26-cta-mini ap-content-gate" id="' . esc_attr( ap_content_gate_anchor( $post_id ) ) . '"><div class="ap26-cta-mini__copy">';
	if ( '' !== $heading ) {
		$out .= '<h2 class="ap26-cta-mini__heading ap-content-gate__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== $text ) {
		$out .= '<p class="ap26-cta-mini__text">' . esc_html( $text ) . '</p>';
	}
	return $out . '<div class="ap-content-gate__form" id="' . esc_attr( $target ) . '"></div></div></aside>';
}
