<?php
/**
 * AP Hero shortcode — view (adapter render).
 *
 * The shortcode's ONLY job: map the builder's option values ($atts) into the
 * kit's prop names and hand them to ap_render_component(). No markup, no
 * escaping, no styling here — those live in ui/components/hero/. Unyson passes
 * the resolved option values in $atts (keyed by option id from options.php).
 *
 * ap_render_component() returns an escaped HTML string; we echo it.
 *
 * @var array  $atts    Resolved shortcode option values.
 * @var string $content Shortcode inner content (unused).
 * @var string $tag     Shortcode tag (unused).
 *
 * @package AssetPanda\Unyson
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_render_component' ) ) {
	// Kit loader not wired. See unyson/inc/asset-panda-ui.php — the theme's
	// functions.php must require it once.
	return;
}

$props = array(
	'title'     => isset( $atts['title'] ) ? $atts['title'] : '',
	'subtitle'  => isset( $atts['subtitle'] ) ? $atts['subtitle'] : '',
	'eyebrow'   => isset( $atts['eyebrow'] ) ? $atts['eyebrow'] : '',
	'cta_label' => isset( $atts['cta_label'] ) ? $atts['cta_label'] : '',
	'cta_url'   => isset( $atts['cta_url'] ) ? $atts['cta_url'] : '',
	'alignment' => isset( $atts['alignment'] ) ? $atts['alignment'] : 'center',
	'theme'     => isset( $atts['theme'] ) ? $atts['theme'] : 'light',
);

echo ap_render_component( 'hero', $props ); // phpcs:ignore WordPress.Security.EscapeOutput -- kit escapes on output.
