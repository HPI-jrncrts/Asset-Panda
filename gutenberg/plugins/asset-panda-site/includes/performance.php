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

/*
 * WordPress's speculative loading is off (1.3.9, 2026-11-17).
 *
 * Since 6.8 core prints <script type="speculationrules"> on every page for logged-out visitors: "prefetch any
 * link on this site when the visitor starts to press it". Chrome has acted on it for years; Safari started to
 * (26.2+). Rex's iPad (Safari 27.3, the only visitor on that version in 72 hours of edge logs) fetched a page
 * for every menu link his finger touched (245 pages in 12 minutes on 2026-11-17, 130 in 6 on 2026-11-15) and then
 * showed pages that were empty, or that began part-way through with their stylesheet as text. The edge log has
 * every one of those 1,963 responses as a complete 200 in milliseconds: the pages left the server whole and were
 * lost in the browser, on the path that hands a prefetched page to the tab. Reproduced the trigger in WebKit 26.5:
 * with the rules a press on a link sends a "Sec-Purpose: prefetch" request for it, without them nothing is sent.
 *
 * With no rules a page is fetched when it is opened, as on the old site. The cost is the head start prefetch
 * gave Chrome on a click (the pages answer from the edge cache in 10 to 20 ms, so little is lost).
 *
 * Any host. Back on: define( 'AP_SITE_SPECULATIVE_LOADING', true ) in wp-config.php, or the filter
 * ap_site_feature_no_speculative_loading.
 */

/**
 * Whether core's speculation rules are kept off the pages on this request.
 *
 * @return bool
 */
function ap_site_no_speculative_loading_on() {
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return false;
	}
	if ( defined( 'AP_SITE_SPECULATIVE_LOADING' ) && AP_SITE_SPECULATIVE_LOADING ) {
		return false;
	}
	return (bool) apply_filters( 'ap_site_feature_no_speculative_loading', true );
}

// Core: a null configuration switches speculative loading off (wp_get_speculation_rules_configuration()).
add_filter(
	'wp_speculation_rules_configuration',
	static function ( $config ) {
		return ap_site_guard(
			static function () use ( $config ) {
				return ap_site_no_speculative_loading_on() ? null : $config;
			},
			$config
		);
	},
	PHP_INT_MAX
);
