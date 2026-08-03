<?php
/**
 * AP Hero block — server render (adapter).
 *
 * This file is the block's dynamic render. Its ONLY job is to map the block's
 * attributes into the kit's prop names and hand them to ap_render_component().
 * There is no markup, no escaping, and no styling here — all of that lives in
 * ui/components/hero/. Attribute names are the block's concern (camelCase);
 * prop names are the kit's concern (snake_case). Map between them explicitly.
 *
 * WordPress includes this file with $attributes, $content and $block in scope
 * (see block.json "render"). ap_render_component() returns an escaped HTML
 * string; we echo it.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block HTML (unused — this block has none).
 * @var WP_Block $block      Block instance (unused).
 *
 * @package AssetPanda\Gutenberg
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$props = array(
	'title'     => isset( $attributes['title'] ) ? $attributes['title'] : '',
	'subtitle'  => isset( $attributes['subtitle'] ) ? $attributes['subtitle'] : '',
	'eyebrow'   => isset( $attributes['eyebrow'] ) ? $attributes['eyebrow'] : '',
	'cta_label' => isset( $attributes['ctaLabel'] ) ? $attributes['ctaLabel'] : '',
	'cta_url'   => isset( $attributes['ctaUrl'] ) ? $attributes['ctaUrl'] : '',
	'alignment' => isset( $attributes['alignment'] ) ? $attributes['alignment'] : 'center',
	'theme'     => isset( $attributes['theme'] ) ? $attributes['theme'] : 'light',
);

echo ap_render_component( 'hero', $props ); // phpcs:ignore WordPress.Security.EscapeOutput -- kit escapes on output.
