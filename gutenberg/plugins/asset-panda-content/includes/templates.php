<?php
/**
 * How resources and press releases render in the 2026 blog design.
 *
 * Mechanism (the least invasive one that works):
 * 1. NO template copies. The staging theme already has the design as DB
 *    templates `single`, `archive` and `home`. WordPress's own template
 *    hierarchy sends single-resource / single-newsroom to `single` and
 *    taxonomy-resource-types / archive-newsroom to `archive`. The one addition
 *    is a hierarchy filter that sends the resource listing (/resource-center/,
 *    archive-resource) to `home`, the Blog List design. Edits to those
 *    templates in the Site Editor therefore apply to resources too, and a
 *    `single-resource` (etc.) template made later in the Site Editor wins over
 *    this fallback, as usual.
 * 2. Inside those templates, snippet 6's blocks are written for core posts.
 *    On resource / pressroom views only, pre_render_block hands six of them
 *    (post-header, sticky-bar, related-posts, category-hero, blog-grid,
 *    blog-hero) to the type-aware renderers in render.php, which print the
 *    same markup. share-bar and cta-mini are used as they are. Core posts are
 *    never touched.
 * 3. Snippet 6 loads its stylesheet only on core blog views and snippet 8 runs
 *    its design pass (listicles, tables, mini CTAs, hero wash) only on
 *    is_singular('post'): both are switched on here for resource / pressroom
 *    views by calling the snippets' own functions. Neither snippet is edited.
 * 4. Legacy pages: a plugin template `page-article` (title hero + post content
 *    in the design's article column), registered with register_block_template().
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True on a resource / press release single, a Resource Type or a listing. */
function ap_content_is_view() {
	return is_singular( array( 'resource', 'newsroom' ) ) || is_tax( 'resource-types' ) || is_post_type_archive( array( 'resource', 'newsroom' ) );
}

/** True on a page that uses the page-article template. */
function ap_content_is_article_page() {
	return is_page() && 'page-article' === get_page_template_slug( get_queried_object_id() );
}

/**
 * True on a resource built from kit blocks (the comparison guides built as
 * landing pages): it renders full width on the theme's page-no-title template,
 * without the article column, the blog sheets or the design pass.
 */
function ap_content_is_kit_single() {
	if ( ! is_singular( 'resource' ) ) {
		return false;
	}
	$post = get_post( get_queried_object_id() );
	// Only when the page OPENS with a kit block (a landing page): an article that
	// merely embeds a kit block (a comparison table, the floating contents panel)
	// keeps the blog design.
	return $post && (bool) preg_match( '/^\s*<!-- wp:assetpanda\//', (string) $post->post_content );
}

/** Rendering kill switch. */
function ap_content_render_enabled() {
	return ! ( defined( 'AP_CONTENT_DISABLE_RENDER' ) && AP_CONTENT_DISABLE_RENDER );
}

/* 1. /resource-center/ uses the Blog List template (`home`). */
add_filter(
	'archive_template_hierarchy',
	function ( $templates ) {
		if ( ! ap_content_render_enabled() || ! is_post_type_archive( 'resource' ) ) {
			return $templates;
		}
		$at = array_search( 'archive-resource.php', $templates, true );
		array_splice( $templates, false === $at ? 0 : $at + 1, 0, array( 'home.php' ) );
		return $templates;
	}
);

/* 1b. A kit-built resource renders on the page-no-title template (full-width kit sections, no article chrome). */
add_filter(
	'single_template_hierarchy',
	function ( $templates ) {
		if ( ! ap_content_render_enabled() || ! ap_content_is_kit_single() ) {
			return $templates;
		}
		array_unshift( $templates, 'page-no-title.php' );
		return $templates;
	}
);

/* 2. Type-aware output of snippet 6's blocks on resource / pressroom views. */
add_filter(
	'pre_render_block',
	function ( $pre, $block ) {
		if ( null !== $pre || empty( $block['blockName'] ) || 0 !== strpos( $block['blockName'], 'ap26/' ) ) {
			return $pre;
		}
		if ( ! ap_content_render_enabled() || ! ap_content_is_view() ) {
			return $pre;
		}
		$single = is_singular( array( 'resource', 'newsroom' ) );
		// 1.3.0: live's look through the kit's resource-* components (listing.php); each of
		// these falls back to the 1.2 renderer (render.php) when the kit lacks the component.
		$map = array(
			'ap26/post-header'   => $single ? 'ap_content_render_single_header' : '',
			'ap26/sticky-bar'    => $single ? 'ap_content_render_sticky_bar' : '',
			'ap26/related-posts' => $single ? 'ap_content_render_single_related' : '',
			'ap26/category-hero' => $single ? '' : 'ap_content_render_hub_hero',
			'ap26/blog-grid'     => $single ? '' : 'ap_content_render_hub_grid',
			'ap26/blog-hero'     => $single ? '' : 'ap_content_render_hub_hero',
		);
		if ( empty( $map[ $block['blockName'] ] ) ) {
			return $pre;
		}
		try {
			return (string) call_user_func( $map[ $block['blockName'] ] );
		} catch ( \Throwable $e ) {
			return $pre; // The snippet's own output.
		}
	},
	10,
	2
);

/* 3a. Styles: snippet 6's sheet + script, this plugin's sheet, snippet 8's sheet. */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! ap_content_render_enabled() ) {
			return;
		}
		if ( is_page() && ! ap_content_is_article_page() && ap_content_has_gate( get_queried_object_id() ) ) {
			// A form panel on any other page (e.g. a kit page): only this plugin's sheet.
			wp_enqueue_style( 'ap-content', plugins_url( 'assets/ap-content.css', AP_CONTENT_FILE ), array(), AP_CONTENT_VERSION );
			return;
		}
		if ( ap_content_is_kit_single() || ! ( ap_content_is_view() || ap_content_is_article_page() ) ) {
			return;
		}
		if ( function_exists( 'ap26_blog_css' ) && ! wp_style_is( 'ap26-blog', 'enqueued' ) ) {
			wp_register_style( 'ap26-blog', false, array(), '1.0.0' );
			wp_enqueue_style( 'ap26-blog' );
			wp_add_inline_style( 'ap26-blog', ap26_blog_css() );
		}
		if ( function_exists( 'ap26_blog_js' ) && ! wp_script_is( 'ap26-blog', 'enqueued' ) ) {
			wp_register_script( 'ap26-blog', '', array(), '1.0.0', true );
			wp_enqueue_script( 'ap26-blog' );
			wp_add_inline_script( 'ap26-blog', ap26_blog_js() );
		}
		wp_enqueue_style( 'ap-content', plugins_url( 'assets/ap-content.css', AP_CONTENT_FILE ), array(), AP_CONTENT_VERSION );
	},
	21
);

/* The CTA-box quote style, wherever a quote block renders (and in the editor). */
add_action(
	'init',
	function () {
		if ( function_exists( 'register_block_style' ) ) {
			register_block_style(
				'core/quote',
				array(
					'name'  => 'ap-cta-quote',
					'label' => 'CTA box',
				)
			);
		}
		if ( function_exists( 'wp_enqueue_block_style' ) ) {
			wp_enqueue_block_style(
				'core/quote',
				array(
					'handle' => 'ap-content-cta-quote',
					'src'    => plugins_url( 'assets/cta-quote.css', AP_CONTENT_FILE ),
					'path'   => AP_CONTENT_DIR . '/assets/cta-quote.css',
					'ver'    => AP_CONTENT_VERSION,
				)
			);
		}
	}
);

/* Group wrappers with the class (no quote block on the page) still get the style. */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ap_content_is_view() || ap_content_is_article_page() ) {
			wp_enqueue_style( 'ap-content-cta-quote', plugins_url( 'assets/cta-quote.css', AP_CONTENT_FILE ), array(), AP_CONTENT_VERSION );
		}
	},
	22
);

/* 3b. Snippet 8's sheet, re-scoped from .single-post to resource / press singles. */
add_action(
	'wp_head',
	function () {
		if ( ! ap_content_render_enabled() || ! is_singular( array( 'resource', 'newsroom' ) ) || ap_content_is_kit_single() || ! function_exists( 'ap26x_css' ) ) {
			return;
		}
		try {
			$css = (string) ap26x_css();
		} catch ( \Throwable $e ) {
			return;
		}
		$css = str_replace( 'body.single-post', 'body:is(.single-resource,.single-newsroom)', $css );
		$css = str_replace( '.single-post', ':is(.single-resource,.single-newsroom)', $css );
		echo '<style id="ap-content-ap26x">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
);

/*
 * 3c. Snippet 8's design pass for the main resource / press release body.
 * Its render_block and closing filters act whenever its state is armed; it
 * arms only for core posts (priority 8, is_singular('post')), so arm it the
 * same way here, once, for the queried resource.
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! ap_content_render_enabled() || ! function_exists( 'ap26x_state' ) || ! function_exists( 'ap26x_parse_rank' ) || ! function_exists( 'ap26x_norm' ) ) {
			return $content;
		}
		if ( ! is_singular( array( 'resource', 'newsroom' ) ) || ap_content_is_kit_single() || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id()
			|| doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		try {
			$post  = get_post();
			$title = $post ? ap26x_norm( get_the_title( $post ) ) : '';
			$ranks = 0;
			foreach ( parse_blocks( $content ) as $block ) {
				if ( 'core/heading' === ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) && (int) ( isset( $block['attrs']['level'] ) ? $block['attrs']['level'] : 2 ) === 2
					&& ap26x_parse_rank( $block['innerHTML'] ) ) {
					++$ranks;
				}
			}
			// A ranked list only when the title says so: how-to posts number their sections too
			// ("1. Straight-Line" in What Is Depreciation), and those are not vendors to draw logos for.
			$listicle = $ranks >= 2
				&& preg_match( '/\b(best|top|alternatives?|vs\.?|compared|comparison)\b/i', $title )
				&& ! preg_match( '/\bbest practices?\b/i', $title );
			ap26x_state(
				array(
					'active'     => true,
					'title'      => $title,
					'listicle'   => (bool) $listicle,
					'first_seen' => false,
					'in_brand'   => false,
					'cta_done'   => false,
					'skip_pair'  => false,
				)
			);
		} catch ( \Throwable $e ) {
			return $content;
		}
		return $content;
	},
	8
);

/*
 * 3d. A listicle's own Asset Panda box (its "See Asset Panda in Action" mini CTA,
 * sometimes with a line of copy of its own) stands in for the one the design
 * pass adds where the Asset Panda entry ends, so the entry keeps one box.
 */
add_filter(
	'render_block',
	function ( $html, $block ) {
		if ( 'ap26/cta-mini' !== ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) || ! function_exists( 'ap26x_state' ) ) {
			return $html;
		}
		$s = ap26x_state();
		if ( is_array( $s ) && ! empty( $s['active'] ) && ! empty( $s['in_brand'] ) && empty( $s['cta_done'] ) ) {
			$s['cta_done'] = true;
			ap26x_state( $s );
		}
		return $html;
	},
	9,
	2
);

/*
 * 3e. Vendor logos the live listicles already show (uploads on www.assetpanda.com),
 * added to the design pass's logo library so their ranked entries keep them.
 * Rows: upload path, CSS width and height, the vendor names they stand for.
 */
function ap_content_listicle_logos() {
	return array(
		array( '2026/05/firstdue_logo.jpg', 120, 120, array( 'first due' ) ),
		array( '2026/05/eso_solutions_logo.jpg', 120, 120, array( 'eso logistics management' ) ),
		array( '2026/05/Vector-Check-It.png', 120, 120, array( 'vector check it', 'vector solutions police asset management' ) ),
		array( '2026/05/pstrax-logo-600x400-1.jpg', 180, 120, array( 'pstrax', 'pstrax police asset tracking' ) ),
		array( '2026/05/Operative-IQ.jpg', 120, 120, array( 'operative iq' ) ),
		array( '2026/05/firemate_software_logo.jpg', 120, 120, array( 'firemate' ) ),
		array( '2026/05/mcm_technology_logo.jpg', 120, 120, array( 'mcm technology' ) ),
		array( '2026/05/StationSmarts.jpg', 120, 120, array( 'stationsmarts' ) ),
		array( '2026/05/RedNMX-by-Alpine-Software.jpg', 120, 120, array( 'rednmx by alpine software' ) ),
		array( '2026/05/Fire-Rescue-Systems.jpg', 120, 120, array( 'fire rescue systems' ) ),
		array( '2026/05/Intterra.jpg', 120, 120, array( 'intterra' ) ),
		array( '2026/05/inspect_point_logo.jpg', 120, 120, array( 'inspect point' ) ),
		array( '2026/05/OpenGov-Enterprise-Asset-Management.webp', 120, 120, array( 'opengov enterprise asset management' ) ),
		array( '2026/05/Trimble-Cityworks.webp', 256, 71, array( 'trimble cityworks' ) ),
		array( '2026/05/brightly-asset-essentials.webp', 120, 120, array( 'brightly asset essentials / confirm', 'brightly asset essentials' ) ),
		array( '2026/05/EZO-io.png', 120, 120, array( 'ezo / ezofficeinventory', 'ezo' ) ),
		array( '2026/05/PSD-Citywide.webp', 155, 120, array( 'psd citywide' ) ),
		array( '2026/05/FMX.webp', 96, 74, array( 'fmx' ) ),
		array( '2026/05/TMA-Systems.jpg', 120, 120, array( 'tma systems' ) ),
		array( '2026/05/govpilot_logo.jpg', 120, 120, array( 'govpilot' ) ),
		array( '2026/05/CityReporter.webp', 256, 85, array( 'cityreporter' ) ),
		array( '2026/05/Asset-Infinity.webp', 120, 120, array( 'asset infinity' ) ),
		array( '2026/05/FSI-CMMS.png', 120, 120, array( 'fsi cmms' ) ),
		array( '2026/05/Omnigo-Logo.avif', 120, 120, array( 'omnigo' ) ),
		array( '2026/05/collective_data_logo.jpg', 120, 120, array( 'collective data quartermaster' ) ),
		array( '2026/05/PMI-Evidence-Tracker.jpg', 120, 120, array( 'pmi evidence tracker' ) ),
		array( '2026/05/pts_solutions_inc__logo.jpg', 120, 120, array( 'pts solutions asset management' ) ),
		array( '2026/05/Apptricity-logo.jpg', 120, 120, array( 'apptricity' ) ),
		array( '2026/05/real_time_networks_logo.jpg', 120, 120, array( 'real time networks' ) ),
		array( '2026/05/traka_assa_abloy_logo.jpg', 120, 120, array( 'traka' ) ),
		array( '2026/05/axon_protect_life_logo.jpg', 120, 120, array( 'axon evidence' ) ),
		array( '2026/05/EvidenceOnQ.jpg', 120, 120, array( 'evidenceonq' ) ),
	);
}

add_filter(
	'ap26x_assets',
	function ( $assets ) {
		if ( ! is_array( $assets ) || ! isset( $assets['logos'] ) || ! is_array( $assets['logos'] ) ) {
			return $assets;
		}
		foreach ( ap_content_listicle_logos() as $logo ) {
			$assets['logos'][] = array(
				'url'     => 'https://www.assetpanda.com/wp-content/uploads/' . $logo[0],
				'w'       => $logo[1],
				'h'       => $logo[2],
				'aliases' => $logo[3],
			);
		}
		return $assets;
	}
);

/*
 * The form panel after the body: white papers (1.0) and pages (1.1), plus the
 * optional jump button (`_ap_gate_button_label`).
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! ap_content_render_enabled() || ! is_singular( array( 'resource', 'page' ) ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id()
			|| doing_filter( 'get_the_excerpt' ) || false !== strpos( $content, 'ap-content-gate' ) ) {
			return $content;
		}
		try {
			$id = (int) get_the_ID();
			if ( ! ap_content_has_gate( $id ) ) {
				return $content;
			}
			return ap_content_gate_jump( $content, $id ) . ap_content_gate_html( $id );
		} catch ( \Throwable $e ) {
			return $content;
		}
	},
	20
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( is_singular( 'resource' ) ) {
			$term      = ap_content_primary_type( get_queried_object_id() );
			$classes[] = 'ap-content';
			if ( $term ) {
				$classes[] = 'ap-content-type-' . sanitize_html_class( $term->slug );
			}
		} elseif ( ap_content_is_view() || ap_content_is_article_page() ) {
			$classes[] = 'ap-content';
		}
		return $classes;
	}
);

/* 4. Legacy pages: the page-article template and its title hero block. */
add_action(
	'init',
	function () {
		register_block_type(
			'ap-content/page-hero',
			array(
				'title'           => 'Article page title',
				'category'        => 'theme',
				'description'     => 'Breadcrumbs and the page title, in the 2026 blog category-hero style.',
				'supports'        => array(
					'html'     => false,
					'inserter' => false,
				),
				'render_callback' => function () {
					try {
						return ap_content_render_page_hero();
					} catch ( \Throwable $e ) {
						return '';
					}
				},
			)
		);
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		$content = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n"
			. '<!-- wp:group {"tagName":"main","className":"ap26-blog-main ap-content-article-main","layout":{"type":"default"}} -->' . "\n"
			. '<main class="wp-block-group ap26-blog-main ap-content-article-main">'
			. '<!-- wp:ap-content/page-hero /-->' . "\n"
			. '<!-- wp:post-content {"className":"ap26-article ap-content-article","layout":{"type":"default"}} /-->'
			. '</main>' . "\n"
			. '<!-- /wp:group -->' . "\n"
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		register_block_template(
			'asset-panda-content//page-article',
			array(
				'title'       => 'Article (legacy page)',
				'description' => 'Title hero and the 2026 article body styles, for imported pages that have no Figma design.',
				'content'     => $content,
				'post_types'  => array( 'page' ),
			)
		);
	},
	15
);
