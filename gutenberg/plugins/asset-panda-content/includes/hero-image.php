<?php
/**
 * The header image as a picture in the editor, 1.3.25.
 *
 * Joseph, 2026-11-10, on a new "Best" draft in the editor (title, then straight
 * into Key Takeaways): "what do you think is missing? I said it previously" —
 * the page shows a large image under the title, and the editor showed nothing
 * there: Template Library drafts carry that image as an address (meta
 * _ap_featured_image_url) rather than a featured image, and even a featured
 * image was only painted behind the content, not something to click.
 *
 * 1. Editor: every article opens with WordPress's own Featured Image block
 *    right under the title (assets/hero-image.js adds it when missing, without
 *    marking the post as changed). Writers click it → Replace, or Upload when
 *    empty, like a picture in a document. It edits the post's featured image,
 *    which the page header, the resource cards and the social previews use.
 *    A post whose image is only an address shows that image in the box.
 * 2. Page: the header draws the image, so the block itself prints nothing.
 * 3. Template Library: a draft whose header image is an address that belongs
 *    to an image in the media library gets that image as its featured image.
 *
 * @package AssetPandaContent
 */

defined( 'ABSPATH' ) || exit;

const AP_CONTENT_HERO_CLASS = 'ap-hero-image';

/** Post types whose header draws the featured image. */
function ap_content_hero_types() {
	return array( 'resource', 'newsroom', 'post' );
}

// 2. Priority 7, before the blocks are rendered (9): the header already draws this image.
add_filter(
	'the_content',
	function ( $content ) {
		if ( false === strpos( (string) $content, 'wp:post-featured-image' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, ap_content_hero_types(), true ) ) {
			return $content;
		}
		return (string) preg_replace(
			'#<!--\s+wp:post-featured-image(?:\s+\{.*?\})?\s+/-->\s*|<!--\s+wp:post-featured-image(?:\s+\{.*?\})?\s+-->.*?<!--\s+/wp:post-featured-image\s+-->\s*#s',
			'',
			(string) $content
		);
	},
	7
);

/**
 * The media-library image at an address (also its resized and "-scaled" copies), or 0.
 *
 * @param string $url Image address.
 * @return int
 */
function ap_content_attachment_for_url( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return 0;
	}
	foreach ( array( $url, preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url ), preg_replace( '/-scaled(?=\.[a-z0-9]+$)/i', '', $url ) ) as $try ) {
		$id = (int) attachment_url_to_postid( (string) $try );
		if ( $id ) {
			return $id;
		}
	}
	return 0;
}

// 3. Priority 4: before the draft's copy is touched (5, 10).
add_action(
	'ap_tl_draft_created',
	function ( $post_id ) {
		if ( ! in_array( get_post_type( $post_id ), ap_content_hero_types(), true ) || get_post_thumbnail_id( $post_id ) ) {
			return;
		}
		$id = ap_content_attachment_for_url( (string) get_post_meta( $post_id, '_ap_featured_image_url', true ) );
		if ( $id && wp_attachment_is_image( $id ) ) {
			set_post_thumbnail( $post_id, $id );
		}
	},
	4,
	1
);

/** The editor post, when its header draws the featured image; else null. */
function ap_content_hero_editor_post() {
	$post = function_exists( 'ap_content_editor_post' ) ? ap_content_editor_post() : null;
	return ( $post && in_array( (string) $post->post_type, ap_content_hero_types(), true ) ) ? $post : null;
}

// 1. The block, shaped like the header image (928px wide, 16:10, rounded), in the editor canvas.
add_action(
	'enqueue_block_assets',
	function () {
		if ( ! ap_content_render_enabled() || ! ap_content_hero_editor_post() ) {
			return;
		}
		$b = '.editor-styles-wrapper .wp-block-post-featured-image.' . AP_CONTENT_HERO_CLASS;
		wp_register_style( 'ap-content-hero-image', false, array(), AP_CONTENT_VERSION );
		wp_enqueue_style( 'ap-content-hero-image' );
		wp_add_inline_style(
			'ap-content-hero-image',
			$b . '{position:relative;left:50%;transform:translateX(-50%);box-sizing:border-box;width:min(928px,calc(100vw - 48px));max-width:none!important;margin-top:32px!important;margin-bottom:8px!important}'
			. $b . ' img{display:block;width:100%!important;height:auto!important;aspect-ratio:16/10;object-fit:cover;border-radius:16px}'
			. $b . ' .components-placeholder{min-height:0;aspect-ratio:16/10;border-radius:16px}'
			// A post whose image is an address only: that image in the box, the upload buttons over it.
			. '.ap-ed-featured ' . $b . ':not(:has(img)) .components-placeholder{background:linear-gradient(rgba(255,255,255,.55),rgba(255,255,255,.55)),var(--ap-ed-featured) center/cover no-repeat}'
			. $b . '::after{content:"Header image \2014  click it to change it (Replace, or Upload when empty)";display:block;margin-top:6px;font:500 12px/1.4 Manrope,sans-serif;color:#667085;text-align:center}'
			// The picture painted behind the content (editor.php) is not needed once the block shows it.
			. '.ap-ed-featured .editor-styles-wrapper .wp-block-post-content:has(.' . AP_CONTENT_HERO_CLASS . ')::before{content:none;display:none}'
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		$path = AP_CONTENT_DIR . '/assets/hero-image.js';
		if ( ! ap_content_render_enabled() || ! ap_content_hero_editor_post() || ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'ap-content-hero-image',
			plugins_url( 'assets/hero-image.js', AP_CONTENT_FILE ),
			array( 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-editor' ),
			AP_CONTENT_VERSION . '.' . filemtime( $path ),
			true
		);
		wp_add_inline_script( 'ap-content-hero-image', 'window.AP_HERO_IMAGE = ' . wp_json_encode( array( 'className' => AP_CONTENT_HERO_CLASS ) ) . ';', 'before' );
	}
);
