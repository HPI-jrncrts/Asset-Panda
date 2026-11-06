<?php
/**
 * Builder v1 (2026-11): resources, press releases and posts look in the block
 * editor as they do on the page, and writers get ready-made article sections.
 *
 * 1. The article's stylesheets in the editor canvas: snippet 6's blog sheet
 *    (ap26_blog_css), snippet 8's design pass (ap26x_css) and this plugin's
 *    ap-content.css, re-scoped from the front end's article column
 *    (.ap26-article, .entry-content, body.single-*) to the editor's post
 *    content (.editor-styles-wrapper .wp-block-post-content). Nothing is
 *    copied: each editor load reads the same functions and file the front end
 *    prints, so a design change reaches the editor with no second edit.
 * 2. assets/editor.js: the page header over the title (the type eyebrow and
 *    the featured image where they sit on the page) and the key-content
 *    classes callouts.php adds on the front end, so Key Takeaways, Quick
 *    Answer and FAQ sections look the same while they are written.
 * 3. A "Sections" pattern category: ready-made article pieces (key takeaways,
 *    quick answer, mini CTA, comparison table, FAQ, image with caption,
 *    video, table of contents, download box).
 *
 * Editor only: nothing here loads on a visitor's page.
 *
 * @package AssetPandaContent
 */

defined( 'ABSPATH' ) || exit;

/** Post types edited as articles. */
function ap_content_editor_types() {
	return array( 'resource', 'newsroom', 'post' );
}

/** The post open in the block editor, when it is an article; else null. */
function ap_content_editor_post() {
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return null;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() ) {
		return null;
	}
	if ( ! in_array( (string) $screen->post_type, ap_content_editor_types(), true ) ) {
		return null;
	}
	$post = get_post();
	return $post ? $post : (object) array(
		'ID'        => 0,
		'post_type' => (string) $screen->post_type,
	);
}

/**
 * The article stylesheets, re-scoped to the editor canvas.
 *
 * strtr() replaces the longest selector first and never re-scans what it
 * wrote, so `.ap26-article .entry-content` and `.ap26-article` each map once.
 *
 * @return string CSS.
 */
function ap_content_editor_css() {
	$parts = array();
	foreach ( array( 'ap26_blog_css', 'ap26x_css' ) as $fn ) {
		if ( function_exists( $fn ) ) {
			try {
				$parts[] = (string) call_user_func( $fn );
			} catch ( \Throwable $e ) {
				continue;
			}
		}
	}
	$file = AP_CONTENT_DIR . '/assets/ap-content.css';
	if ( is_readable( $file ) ) {
		$parts[] = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	if ( ! $parts ) {
		return '';
	}
	$body    = '.editor-styles-wrapper';
	$article = '.editor-styles-wrapper .wp-block-post-content';
	$css     = strtr(
		implode( "\n", $parts ),
		array(
			'.ap-content .ap26-article .entry-content'                  => $article,
			'.ap26-article .entry-content'                              => $article,
			'.ap-content .ap26-article'                                 => $article,
			'.ap26-article'                                             => $article,
			'body:is(.single-resource,.single-newsroom)'                => $body,
			':is(.single-resource,.single-newsroom) .entry-content'     => $article,
			':is(.single-resource,.single-newsroom)'                    => $body,
			'body.single-post'                                          => $body,
			'.single-post .entry-content'                               => $article,
			'.single-post'                                              => $body,
			'body.ap-content'                                           => $body,
			'.entry-content'                                            => $article,
			// Front end: callouts.php marks a key label's <strong>; editor.js can only mark the block itself.
			'.ap26-key__title'                                          => ':is(.ap26-key__title, .ap26-key > strong:first-child, .ap26-key > p:first-child > strong:first-child)',
		)
	);

	// The page header over the title, as the article header draws it (resource-header, --article).
	$css .= "\n" . '.editor-styles-wrapper{background:#fff}'
		. '.editor-styles-wrapper .editor-post-title{box-sizing:border-box;max-width:928px;margin:40px auto 0;padding:0 24px;'
		. 'font-family:Manrope,sans-serif;font-size:56px;font-weight:800;line-height:1.15;letter-spacing:normal;color:#25272c}'
		. '.ap-ed-eyebrow .editor-styles-wrapper .editor-post-title::before{content:var(--ap-ed-eyebrow);display:block;margin:0 0 16px;'
		. 'font:700 14px/1.2 Manrope,sans-serif;letter-spacing:.04em;text-transform:uppercase;color:#5044e1}'
		. '.ap-ed-featured .editor-styles-wrapper .wp-block-post-content::before{content:"";display:block;box-sizing:border-box;'
		. 'position:relative;left:50%;width:min(928px,calc(100vw - 48px));max-width:none;aspect-ratio:16/10;margin:32px 0 8px;'
		. 'transform:translateX(-50%);border-radius:16px;'
		. 'background:#f2f4f7 var(--ap-ed-featured) center/cover no-repeat}'
		. '@media (max-width:1023px){.editor-styles-wrapper .editor-post-title{font-size:40px}}'
		. '@media (max-width:767px){.editor-styles-wrapper .editor-post-title{font-size:30px;padding:0 16px}}';
	return $css;
}

add_action(
	'enqueue_block_assets',
	function () {
		if ( ! ap_content_render_enabled() || ! ap_content_editor_post() ) {
			return;
		}
		$css = ap_content_editor_css();
		if ( '' === $css ) {
			return;
		}
		wp_register_style( 'ap-content-editor', false, array(), AP_CONTENT_VERSION );
		wp_enqueue_style( 'ap-content-editor' );
		wp_add_inline_style( 'ap-content-editor', $css );
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		$post = ap_content_editor_post();
		if ( ! ap_content_render_enabled() || ! $post ) {
			return;
		}
		$path = AP_CONTENT_DIR . '/assets/editor.js';
		if ( ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'ap-content-editor',
			plugins_url( 'assets/editor.js', AP_CONTENT_FILE ),
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-editor' ),
			AP_CONTENT_VERSION,
			true
		);
		wp_add_inline_script(
			'ap-content-editor',
			'window.AP_CONTENT_EDITOR = ' . wp_json_encode(
				array(
					'postType'  => (string) $post->post_type,
					'keyLabels' => ap_content_key_labels(),
					'keyHeads'  => ap_content_key_labels( true ),
					'postLabel' => 'post' === $post->post_type ? 'Blog' : ( 'newsroom' === $post->post_type ? 'Press release' : '' ),
				)
			) . ';',
			'before'
		);
	}
);

/* 3. The "Sections" library. */
add_action(
	'init',
	function () {
		if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}
		register_block_pattern_category(
			'ap-article-sections',
			array(
				'label'       => __( 'Asset Panda: article sections', 'asset-panda-content' ),
				'description' => __( 'Ready-made pieces for blog posts and resources, in the site design. Insert one, then click its text to edit it.', 'asset-panda-content' ),
			)
		);
		foreach ( ap_content_section_patterns() as $slug => $pattern ) {
			register_block_pattern(
				'asset-panda-content/' . $slug,
				array(
					'title'         => $pattern[0],
					'description'   => $pattern[1],
					'content'       => $pattern[2],
					'categories'    => array( 'ap-article-sections' ),
					'postTypes'     => ap_content_editor_types(),
					'keywords'      => isset( $pattern[3] ) ? $pattern[3] : array(),
					'viewportWidth' => 900,
				)
			);
		}
	},
	20
);

/**
 * The article sections: slug => [ title, description, block markup, keywords ].
 *
 * Each uses the markup the front end already styles (callouts.php labels,
 * the CTA-box quote style, snippet 6's mini CTA, the kit's compare-table and
 * page-toc blocks), so nothing new is needed to render them. Text is sample
 * copy for the writer to replace.
 *
 * @return array
 */
function ap_content_section_patterns() {
	$h2 = static function ( $text, $anchor = '' ) {
		$attrs = '' !== $anchor ? ' {"anchor":"' . $anchor . '"}' : '';
		$id    = '' !== $anchor ? ' id="' . $anchor . '"' : '';
		return '<!-- wp:heading' . $attrs . ' --><h2 class="wp-block-heading"' . $id . '>' . $text . '</h2><!-- /wp:heading -->';
	};
	$p = static function ( $html ) {
		return '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->';
	};
	$list = static function ( array $items ) {
		$out = '<!-- wp:list --><ul class="wp-block-list">';
		foreach ( $items as $item ) {
			$out .= '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->';
		}
		return $out . '</ul><!-- /wp:list -->';
	};
	$json = static function ( $data ) {
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	};

	return array(
		'key-takeaways'  => array(
			__( 'Key takeaways', 'asset-panda-content' ),
			__( 'A "Key Takeaways" heading and the points under it, drawn as the key-content box.', 'asset-panda-content' ),
			$h2( 'Key Takeaways' ) . $list(
				array(
					'The first thing a reader should remember.',
					'The second point, in one short sentence.',
					'The third point, with the number or fact that proves it.',
				)
			),
			array( 'summary', 'tldr', 'takeaways' ),
		),
		'quick-answer'   => array(
			__( 'Quick answer', 'asset-panda-content' ),
			__( 'A one-paragraph answer that opens with a bold "Quick Answer:" label, drawn as the key-content box.', 'asset-panda-content' ),
			'<!-- wp:quote --><blockquote class="wp-block-quote">' . $p( '<strong>Quick Answer:</strong> The direct answer to the question in the title, in two or three sentences, before the details.' ) . '</blockquote><!-- /wp:quote -->',
			array( 'answer', 'summary', 'definition' ),
		),
		'mini-cta'       => array(
			__( 'Mini CTA', 'asset-panda-content' ),
			__( 'The small demo box: heading, one line of copy and a button (default "Talk to an Expert" → /get-a-demo/).', 'asset-panda-content' ),
			'<!-- wp:ap26/cta-mini ' . $json(
				array(
					'heading' => 'See Asset Panda in Action',
					'text'    => 'A personalized demo is just one click away.',
				)
			) . ' /-->',
			array( 'cta', 'demo', 'button' ),
		),
		'article-cta'    => array(
			__( 'CTA box with button', 'asset-panda-content' ),
			__( 'The wide in-article CTA: panda mark, bold heading, a line of copy and the Free Demo button.', 'asset-panda-content' ),
			'<!-- wp:assetpanda/article-cta ' . $json(
				array(
					'title' => 'See why Asset Panda is the stronger choice',
					'body'  => 'Track every asset, every location and every check-out in one place.',
				)
			) . ' /-->',
			array( 'cta', 'demo', 'banner' ),
		),
		'pull-quote'     => array(
			__( 'Pull quote', 'asset-panda-content' ),
			__( 'A customer or expert quote with the person\'s name and role.', 'asset-panda-content' ),
			'<!-- wp:quote --><blockquote class="wp-block-quote">' . $p( '"A sentence a customer actually said about the problem or the result."' ) . '<cite>Name Surname, Role at Organization</cite></blockquote><!-- /wp:quote -->',
			array( 'quote', 'testimonial', 'customer' ),
		),
		'comparison'     => array(
			__( 'Comparison table', 'asset-panda-content' ),
			__( 'Asset Panda against another tool, row by row, with check marks.', 'asset-panda-content' ),
			'<!-- wp:assetpanda/compare-table ' . $json(
				array(
					'before_title' => 'Other tool',
					'rows'         => array(
						array(
							'label'        => 'Mobile barcode scanning',
							'after'        => 'Included',
							'before'       => 'Add-on',
							'before_check' => false,
							'winner'       => 'Asset Panda',
						),
						array(
							'label'        => 'Custom fields and workflows',
							'after'        => 'Unlimited',
							'before'       => 'Limited',
							'before_check' => false,
							'winner'       => 'Asset Panda',
						),
						array(
							'label'        => 'Audit trail',
							'after'        => 'Every change, every user',
							'before'       => 'Basic',
							'before_check' => true,
							'winner'       => 'Asset Panda',
						),
					),
				)
			) . ' /-->',
			array( 'compare', 'versus', 'table' ),
		),
		'faq'            => array(
			__( 'FAQ', 'asset-panda-content' ),
			__( 'A "Frequently Asked Questions" heading with questions and answers, drawn in the FAQ style.', 'asset-panda-content' ),
			$h2( 'Frequently Asked Questions' )
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">The first question a reader asks?</h3><!-- /wp:heading -->'
			. $p( 'The answer, in two or three sentences.' )
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">The second question?</h3><!-- /wp:heading -->'
			. $p( 'The answer, in two or three sentences.' ),
			array( 'faq', 'questions', 'answers' ),
		),
		'image-caption'  => array(
			__( 'Image with caption', 'asset-panda-content' ),
			__( 'An image across the article column with a caption under it. Upload or pick the image, then write its alt text.', 'asset-panda-content' ),
			'<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img alt=""/><figcaption class="wp-element-caption">What the image shows, in one sentence.</figcaption></figure><!-- /wp:image -->',
			array( 'image', 'photo', 'screenshot', 'figure' ),
		),
		'video'          => array(
			__( 'Video', 'asset-panda-content' ),
			__( 'A YouTube video. Paste the video link into the box.', 'asset-panda-content' ),
			'<!-- wp:embed {"providerNameSlug":"youtube","responsive":true,"type":"video"} /-->',
			array( 'video', 'youtube', 'embed' ),
		),
		'toc'            => array(
			__( 'Table of contents', 'asset-panda-content' ),
			__( 'The floating contents panel. Each entry links to a heading by its HTML anchor (Advanced → HTML anchor on the heading).', 'asset-panda-content' ),
			'<!-- wp:assetpanda/page-toc ' . $json(
				array(
					'items' => array(
						array(
							'label'  => 'First section',
							'anchor' => 'first-section',
						),
						array(
							'label'  => 'Second section',
							'anchor' => 'second-section',
						),
					),
				)
			) . ' /-->',
			array( 'contents', 'toc', 'navigation' ),
		),
		'download-box'   => array(
			__( 'Download box', 'asset-panda-content' ),
			__( 'A mini CTA pointing to a white paper, guide or checklist.', 'asset-panda-content' ),
			'<!-- wp:ap26/cta-mini ' . $json(
				array(
					'heading' => 'Download the free checklist',
					'text'    => 'Everything in this article on one page, ready to print.',
					'button'  => 'Get the checklist',
					'url'     => '/resource-center/white-papers/',
				)
			) . ' /-->',
			array( 'download', 'white paper', 'checklist', 'guide' ),
		),
		'section'        => array(
			__( 'Section: heading, text and list', 'asset-panda-content' ),
			__( 'A new section of the article: a heading, an opening paragraph and a short list.', 'asset-panda-content' ),
			$h2( 'Section heading' ) . $p( 'The opening paragraph of the section.' ) . $list( array( 'A point.', 'Another point.', 'A third point.' ) ),
			array( 'section', 'heading', 'list' ),
		),
	);
}
