<?php
/**
 * Animations on the page: the library's Animations cabinet puts an ap-anim-*
 * class on a block (its "Additional CSS class"). When a block with one renders:
 * - the kit's animation sheet is enqueued (ap_ui_enqueue_anim(): ~2 KB, only on
 *   pages that use an animation);
 * - a scroll entrance (ap-anim-reveal, -reveal-left, -right, -fade, -zoom)
 *   also gets the motion layer's data-ap-reveal on the block's outer element,
 *   and the motion layer (motion.css + motion.js) is enqueued: the same
 *   runtime the kit's own sections use, so nothing new runs on the page.
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The animations the library offers: class => [ label, group, note ]. */
function ap_builder_animations() {
	return array(
		'ap-anim-float'        => array( 'Float', 'ambient', 'Drifts gently up and down.' ),
		'ap-anim-float-slow'   => array( 'Float, slow', 'ambient', 'A slower, softer drift.' ),
		'ap-anim-sway'         => array( 'Sway', 'ambient', 'Rocks a little from side to side.' ),
		'ap-anim-pulse'        => array( 'Pulse', 'ambient', 'Breathes in and out.' ),
		'ap-anim-glow'         => array( 'Glow', 'ambient', 'An indigo glow comes and goes (best on pandas, icons, orbs).' ),
		'ap-anim-spin-slow'    => array( 'Spin, slow', 'ambient', 'Turns slowly (orbs, badges, circles).' ),
		'ap-anim-hover-lift'      => array( 'Lift on hover', 'hover', 'Rises a little under the mouse.' ),
		'ap-anim-hover-grow'      => array( 'Grow on hover', 'hover', 'Grows a little under the mouse.' ),
		'ap-anim-hover-glow'      => array( 'Glow on hover', 'hover', 'A soft indigo glow appears around it (cards, boxes, buttons).' ),
		'ap-anim-hover-brighten'  => array( 'Brighten on hover', 'hover', 'Gets a little brighter (photos, screenshots).' ),
		'ap-anim-hover-tilt'      => array( 'Tilt on hover', 'hover', 'Tips slightly to one side.' ),
		'ap-anim-hover-underline' => array( 'Underline grows', 'hover', 'An underline grows under the text. Also for words: select them on the page.' ),
		'ap-anim-hover-colour'    => array( 'Colour change', 'hover', 'The text turns indigo. Also for words: select them on the page.' ),
		'ap-anim-hover-highlight' => array( 'Highlight', 'hover', 'A soft teal highlight sweeps in behind the text.' ),
		'ap-anim-hover-gradient'  => array( 'Gradient text', 'hover', 'The text turns to the brand gradient.' ),
		'ap-anim-reveal'       => array( 'Rise in', 'scroll', 'Fades and rises in when scrolled to.' ),
		'ap-anim-reveal-left'  => array( 'Slide in from the left', 'scroll', 'Slides in from the left when scrolled to.' ),
		'ap-anim-reveal-right' => array( 'Slide in from the right', 'scroll', 'Slides in from the right when scrolled to.' ),
		'ap-anim-reveal-fade'  => array( 'Fade in', 'scroll', 'Fades in when scrolled to.' ),
		'ap-anim-reveal-zoom'  => array( 'Zoom in', 'scroll', 'Grows into place when scrolled to.' ),
	);
}

add_filter(
	'render_block',
	static function ( $html, $block ) {
		$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
		if ( '' === $class || false === strpos( $class, 'ap-anim-' ) || ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( function_exists( 'ap_ui_enqueue_anim' ) ) {
			ap_ui_enqueue_anim();
		}
		if ( preg_match( '/(?:^|\s)ap-anim-reveal(?:-(left|right|fade|zoom))?(?:\s|$)/', $class, $m ) && false === strpos( $html, 'data-ap-reveal' ) ) {
			$attr = isset( $m[1] ) && '' !== $m[1] ? ' data-ap-reveal="' . $m[1] . '"' : ' data-ap-reveal';
			$html = preg_replace_callback(
				'/^(\s*<[a-zA-Z][a-zA-Z0-9-]*)/',
				static function ( $open ) use ( $attr ) {
					return $open[1] . $attr;
				},
				$html,
				1
			);
			if ( function_exists( 'ap_ui_enqueue_motion' ) ) {
				ap_ui_enqueue_motion();
			}
		}
		return $html;
	},
	10,
	2
);
