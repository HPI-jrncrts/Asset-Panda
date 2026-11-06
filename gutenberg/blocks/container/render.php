<?php
/**
 * AP Container block — server render (adapter, hand-written: tools/gen-blocks.mjs
 * skips it because a generated adapter has no inner blocks).
 *
 * Maps the block's attributes to kit props like every AP block. The one
 * addition: the kit draws the box with a <!--ap-slot--> marker inside, and the
 * block's rendered inner blocks ($content, the post's own blocks, already
 * rendered and filtered by WordPress) take its place. No markup, no escaping,
 * no styling here.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance (unused).
 *
 * @package AssetPandaGutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$props = array(
	'look'             => $attributes['look'] ?? 'card',
	'width'            => $attributes['width'] ?? 'content',
	'padding'          => $attributes['padding'] ?? 'l',
	'text_align'       => $attributes['text_align'] ?? 'left',
	'radius'           => $attributes['radius'] ?? 'l',
	'background_image' => $attributes['background_image'] ?? '',
	'overlay'          => $attributes['overlay'] ?? 'none',
	'decoration'       => $attributes['decoration'] ?? 'none',
);

$ap_html = ap_render_component( 'container', $props );

if ( '' === trim( (string) $ap_html ) ) {
	return;
}

$ap_slot = strpos( $ap_html, '<!--ap-slot-->' );
if ( false !== $ap_slot ) {
	$ap_html = substr_replace( $ap_html, (string) $content, $ap_slot, strlen( '<!--ap-slot-->' ) );
}

/*
 * Same wrapper as the generated adapters: the alignment class from the block
 * and zero margins (the box sets its own spacing).
 */
printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes( array( 'style' => 'margin-top:0;margin-bottom:0' ) ), // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped attribute string.
	$ap_html // phpcs:ignore WordPress.Security.EscapeOutput -- kit escapes on output; $content is the post's own rendered blocks.
);
