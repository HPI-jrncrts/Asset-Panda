<?php
/**
 * Header buttons that lead to the page you are on are not shown.
 *
 * Joseph, 2026-11-12: "On the book a demo page, users still see the 'Free
 * Demo' CTA button on the header. That's just redundant yeah? I think we can
 * hide the button when users get there. Same goes with get a quote pro."
 *
 * The kit's site-header component leaves a header button out when its label is
 * empty (desktop bar, phone header and phone menu alike). Here, before the
 * assetpanda/site-header block renders, a button's label is emptied when:
 *   - its link is the page being viewed (Free Demo on /get-a-demo/, Try for
 *     Free on the free-trial page), or
 *   - for Free Demo, the page is one where a visitor is already asking for a
 *     demo or a quote (/get-a-demo/, /get-a-quote-pro/; filter
 *     ap_site_header_no_demo_paths).
 * The kit and the header template are unchanged.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A URL's path as "/a/b/" (lowercase, one slash each end), "/" for the home page, '' when it has none.
 *
 * @param string $url URL or path.
 * @return string
 */
function ap_site_cta_path( $url ) {
	$path = wp_parse_url( (string) $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return '';
	}
	$path = trim( strtolower( rawurldecode( $path ) ), '/' );
	return '' === $path ? '/' : '/' . $path . '/';
}

/**
 * The path of the page being viewed.
 *
 * @return string
 */
function ap_site_cta_here() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared as a path.
	return ap_site_cta_path( $uri );
}

/**
 * Pages where the header's Free Demo button is left out.
 *
 * @return string[] Paths.
 */
function ap_site_header_no_demo_paths() {
	$paths = (array) apply_filters( 'ap_site_header_no_demo_paths', array( '/get-a-demo/', '/get-a-quote-pro/' ) );
	return array_values( array_filter( array_map( 'ap_site_cta_path', $paths ) ) );
}

/**
 * The site-header block's attributes with the buttons for this page left out.
 *
 * @param array  $attrs Block attributes.
 * @param string $here  Path of the page being viewed.
 * @return array
 */
function ap_site_header_cta_attrs( array $attrs, $here ) {
	if ( '' === $here ) {
		return $attrs;
	}
	$demo_url  = isset( $attrs['demo_url'] ) ? (string) $attrs['demo_url'] : '/get-a-demo/';
	$trial_url = isset( $attrs['trial_url'] ) ? (string) $attrs['trial_url'] : '/start-a-free-trial-email-only/';
	if ( ap_site_cta_path( $demo_url ) === $here || in_array( $here, ap_site_header_no_demo_paths(), true ) ) {
		$attrs['demo_label'] = '';
	}
	if ( ap_site_cta_path( $trial_url ) === $here ) {
		$attrs['trial_label'] = '';
	}
	return $attrs;
}

add_filter(
	'render_block_data',
	function ( $parsed_block ) {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_array( $parsed_block )
			|| 'assetpanda/site-header' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}
		$attrs                 = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : array();
		$parsed_block['attrs'] = ap_site_header_cta_attrs( $attrs, ap_site_cta_here() );
		return $parsed_block;
	}
);
