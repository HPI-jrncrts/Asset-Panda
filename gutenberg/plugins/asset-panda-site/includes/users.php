<?php
/**
 * User enumeration guard. Any host (like the sitemap exclusions).
 *
 * Live blocks /wp-json/wp/v2/users at the web server, but WordPress answers
 * the same endpoint at /?rest_route=/wp/v2/users, and on 2026-10-22 that URL
 * listed every account's login slug. The web-server rule also misses the
 * oEmbed endpoint, whose author_url carries the same slug. WordPress itself is
 * the right place to close this, whichever URL form reaches it:
 *
 * - the wp/v2/users routes answer 401 to anyone not logged in;
 * - ?author=N and author archives answer 404 to anyone not logged in (no
 *   canonical redirect to /author/<login>/);
 * - oEmbed responses carry no author_name / author_url.
 *
 * Logged-in users keep all three (the block editor's author picker and Yoast
 * read the users routes). Setting "user_enumeration", on by default; the
 * AP_SITE_DISABLE kill switch turns it off with everything else.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when the guard applies to this request. */
function ap_site_user_guard_on() {
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return false;
	}
	if ( ! ap_site_setting( 'user_enumeration' ) ) {
		return false;
	}
	return (bool) apply_filters( 'ap_site_feature_user_enumeration', true );
}

/** True when the request asks for an author archive, by query var or ?author=N. */
function ap_site_is_author_probe() {
	if ( function_exists( 'is_author' ) && is_author() ) {
		return true;
	}
	return isset( $_GET['author'] ) && '' !== trim( (string) $_GET['author'] ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- presence check only.
}

/* 1. REST: the users routes, at /wp-json/… and at ?rest_route=… alike. */
add_filter(
	'rest_pre_dispatch',
	static function ( $result, $server, $request ) {
		return ap_site_guard(
			static function () use ( $result, $request ) {
				if ( null !== $result || ! ap_site_user_guard_on() || is_user_logged_in() ) {
					return $result;
				}
				if ( $request instanceof WP_REST_Request && preg_match( '#^/wp/v2/users(?:/|$)#', (string) $request->get_route() ) ) {
					return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
				}
				return $result;
			},
			$result
		);
	},
	10,
	3
);

/* 2. ?author=N and author archives: a 404, and no canonical redirect that would reveal the login. */
add_filter(
	'redirect_canonical',
	static function ( $redirect_url ) {
		return ap_site_guard(
			static function () use ( $redirect_url ) {
				if ( ap_site_user_guard_on() && ! is_user_logged_in() && ap_site_is_author_probe() ) {
					return false;
				}
				return $redirect_url;
			},
			$redirect_url
		);
	},
	1
);

add_action(
	'template_redirect',
	static function () {
		ap_site_guard(
			static function () {
				if ( is_admin() || ! ap_site_user_guard_on() || is_user_logged_in() || ! ap_site_is_author_probe() ) {
					return;
				}
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				nocache_headers();
			}
		);
	},
	0
);

/* 3. oEmbed: no author name or author archive URL. */
add_filter(
	'oembed_response_data',
	static function ( $data ) {
		return ap_site_guard(
			static function () use ( $data ) {
				if ( ! is_array( $data ) || ! ap_site_user_guard_on() ) {
					return $data;
				}
				unset( $data['author_name'], $data['author_url'] );
				return $data;
			},
			$data
		);
	},
	20
);
