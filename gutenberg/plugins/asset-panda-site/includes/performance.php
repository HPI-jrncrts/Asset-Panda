<?php
/**
 * Front-end performance: WordPress's emoji detection script is not printed.
 *
 * Any host (like the user enumeration guard): the point is the pages' Lighthouse /
 * PageSpeed scores on staging as much as on www.
 *
 * What the core script does: on every page it inlines a 3 KB module that paints
 * test emoji on a canvas inside a Worker made from a blob: URL, and when the test
 * fails (headless browsers have no emoji font) it loads wp-emoji-release.min.js
 * (6 KB) and swaps emoji for Twemoji images. Creating that blob Worker is a
 * synchronous call into the browser process; on a busy machine — a PageSpeed
 * runner, a PC with other browsers open — the page's main thread stalls on it for
 * ~400 ms, which Lighthouse scores as a 1.4–1.7 s blocking task (the Asset
 * Intelligence Platform page fell from 87 to 64–66 on it; with the script gone,
 * five runs in a row stayed at 86–87 with no blocking time).
 *
 * Every browser the site supports renders emoji from the system font, so nothing
 * changes on screen. The admin screens keep their emoji handling.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the emoji scripts are stripped on this request.
 *
 * @return bool
 */
function ap_site_no_emoji_on() {
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return false;
	}
	if ( ! ap_site_setting( 'no_emoji_scripts' ) ) {
		return false;
	}
	return (bool) apply_filters( 'ap_site_feature_no_emoji_scripts', true );
}

/**
 * Unhooks core's front-end emoji output: the detection script in <head>, the emoji
 * styles, the s.w.org dns-prefetch (emoji_svg_url), and the feed/e-mail staticising
 * that depends on them.
 */
function ap_site_strip_emoji_scripts() {
	if ( is_admin() || ! ap_site_no_emoji_on() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}

add_action(
	'init',
	static function () {
		ap_site_guard( 'ap_site_strip_emoji_scripts' );
	},
	1
);
