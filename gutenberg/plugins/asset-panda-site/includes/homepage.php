<?php
/**
 * Homepage hero: frame the live Ursa demo on the production host.
 *
 * The kit's home-hero component (ui/components/home-hero/render.php) frames
 * the demo when its `demo_url` prop is set and shows the posters when it is
 * empty. The demo's host only allows assetpanda.com and *.assetpanda.com to
 * frame it (CSP frame-ancestors), so the staging page leaves `demo_url` empty
 * and shows posters. Here, on a production host only, an empty `demo_url` on
 * the assetpanda/home-hero block is filled with live's demo address before
 * the block renders. The kit is unchanged; an editor-set `demo_url` wins.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The demo address, or '' when the setting is not a valid https URL.
 *
 * @param array|null $s Settings.
 * @return string
 */
function ap_site_ursa_url( $s = null ) {
	$s   = is_array( $s ) ? $s : ap_site_settings();
	$url = esc_url_raw( trim( (string) $s['ursa_demo_url'] ), array( 'https' ) );
	return 0 === strpos( $url, 'https://' ) ? $url : '';
}

/** Block names whose `demo_url` attribute is filled. */
function ap_site_ursa_blocks() {
	return (array) apply_filters( 'ap_site_ursa_blocks', array( 'assetpanda/home-hero' ) );
}

/**
 * Fills an empty demo_url on the home-hero block (production host only).
 *
 * @param array $block Parsed block.
 * @return array
 */
function ap_site_ursa_block_data( $block ) {
	return ap_site_guard(
		static function () use ( $block ) {
			if ( ! is_array( $block ) || empty( $block['blockName'] ) || ! in_array( $block['blockName'], ap_site_ursa_blocks(), true ) ) {
				return $block;
			}
			if ( ! ap_site_feature_on( 'ursa_demo' ) ) {
				return $block;
			}
			$url = ap_site_ursa_url();
			if ( '' === $url ) {
				return $block;
			}
			$attrs = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) ? $block['attrs'] : array();
			if ( isset( $attrs['demo_url'] ) && '' !== trim( (string) $attrs['demo_url'] ) ) {
				return $block;
			}
			$attrs['demo_url'] = $url;
			$block['attrs']    = $attrs;
			return $block;
		},
		$block
	);
}
add_filter( 'render_block_data', 'ap_site_ursa_block_data', 10, 1 );
