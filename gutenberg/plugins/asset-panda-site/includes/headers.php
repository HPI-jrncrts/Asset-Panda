<?php
/**
 * Security headers and forced HTTPS. Production host only.
 *
 * Header values are copied byte for byte from live's PHP responses
 * (www.assetpanda.com, 2026-10-16). Live sends the same set on every
 * WordPress-rendered page, the login splash pages included, and on
 * WordPress's own redirects; not on REST responses or XML sitemaps.
 *
 * X-Frame-Options: live also sends SAMEORIGIN on /login-splash-*; browsers
 * ignore it there because CSP frame-ancestors takes precedence, which is why
 * the product login screen can frame those pages today. This plugin leaves
 * X-Frame-Options off on the paths in "xfo_omit" (default /login-splash*) so no
 * browser can block that frame; frame-ancestors still limits who may frame them.
 *
 * Permissions-Policy (1.3.8, reviewed 2026-11-16 with Joseph). Live used to send
 * a copy of the old site's header with `fullscreen=('self')`: the quoted 'self'
 * is not valid structured-header syntax, so Chrome logged "Error with
 * Permissions-Policy header" on every page and ignored the whole header (it
 * restricted nothing). Written correctly, that old list would also have turned
 * off autoplay, fullscreen and encrypted media for the YouTube / Vimeo players
 * and camera for the Ursa demo frame. The header now names only features no page
 * and no player uses, each switched off with `=()`; everything the site uses
 * (autoplay, fullscreen, encrypted-media, picture-in-picture, accelerometer,
 * gyroscope, clipboard-write for the players; camera for the Ursa demo; payment
 * for Cal.com; sync-xhr and the ad-measurement features for third-party tags)
 * is left out, so it keeps the browser default exactly as before. Only features
 * Chrome recognises are listed, so the header logs nothing. Checked on live pages
 * in Chrome: every player and the Ursa frame keep the same features; a tutorial
 * video plays.
 *
 * Not here: Strict-Transport-Security. Live sends it from a WP Engine Web Rule
 * (install assetpanda1, Header Rules #0, "When: All responses"), which also
 * covers static files and redirects that never reach PHP. Recreate that rule on
 * whichever install serves www, unquoted:
 *   Strict-Transport-Security: max-age=63072000; includeSubDomains; preload
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live's header values (Permissions-Policy corrected in 1.3.8, see above).
 *
 * @return array<string, string>
 */
function ap_site_live_header_values() {
	return array(
		'csp'         => "frame-ancestors 'self' https://login.assetpanda.com https://stage.assetpanda.com https://prelive.assetpanda.com https://admin.assetpanda.com;",
		'csp_ro'      => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://consent.cookiebot.com https://js.hsforms.net https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https: blob:; connect-src 'self' https://www.googletagmanager.com https://www.google-analytics.com https://consent.cookiebot.com https://js.hsforms.net; frame-src 'self' https://www.googletagmanager.com https://consent.cookiebot.com; object-src 'none'; base-uri 'self'; form-action 'self' https://js.hsforms.net; frame-ancestors 'self'; upgrade-insecure-requests; block-all-mixed-content",
		'permissions' => 'bluetooth=(), display-capture=(), geolocation=(), hid=(), idle-detection=(), local-fonts=(), magnetometer=(), microphone=(), midi=(), publickey-credentials-get=(), screen-wake-lock=(), serial=(), usb=(), window-management=(), xr-spatial-tracking=()',
		'referrer'    => 'strict-origin-when-cross-origin',
		'nosniff'     => 'nosniff',
		'xfo'         => 'SAMEORIGIN',
		'xss'         => '1; mode=block',
	);
}

/**
 * The headers for a path.
 *
 * @param string     $path Request path.
 * @param array|null $s    Settings.
 * @return array<string, string>
 */
function ap_site_security_headers( $path, $s = null ) {
	$s       = is_array( $s ) ? $s : ap_site_settings();
	$v       = ap_site_live_header_values();
	$headers = array( 'Content-Security-Policy' => $v['csp'] );
	if ( ! empty( $s['csp_report_only'] ) ) {
		$headers['Content-Security-Policy-Report-Only'] = $v['csp_ro'];
	}
	$headers['Permissions-Policy']     = $v['permissions'];
	$headers['Referrer-Policy']        = $v['referrer'];
	$headers['X-Content-Type-Options'] = $v['nosniff'];
	if ( ! ap_site_path_matches( $path, $s['xfo_omit'] ) ) {
		$headers['X-Frame-Options'] = $v['xfo'];
	}
	$headers['X-XSS-Protection'] = $v['xss'];

	$filtered = apply_filters( 'ap_site_security_headers', $headers, $path );
	return is_array( $filtered ) ? $filtered : $headers;
}

/*
 * WP::send_headers() applies this filter for every front-end request that
 * WordPress routes: pages, 404s, robots.txt, feeds, sitemaps, and the
 * redirects WordPress issues later in template_redirect.
 */
add_filter(
	'wp_headers',
	static function ( $headers ) {
		return ap_site_guard(
			static function () use ( $headers ) {
				$headers = is_array( $headers ) ? $headers : array();
				if ( is_admin() || ! ap_site_feature_on( 'headers' ) ) {
					return $headers;
				}
				foreach ( ap_site_security_headers( ap_site_request_path() ) as $name => $value ) {
					if ( is_string( $name ) && is_string( $value ) && '' !== $value && ! preg_match( '/[\r\n]/', $name . $value ) ) {
						$headers[ $name ] = $value;
					}
				}
				return $headers;
			},
			$headers
		);
	},
	99
);

/* ── Forced HTTPS ─────────────────────────────────────────────────────── */

/**
 * Whether the visitor's connection is HTTPS. Behind Cloudflare and WP Engine
 * the TLS connection may end before PHP, so the forwarded scheme counts too;
 * any one HTTPS signal is enough, which keeps the redirect from looping.
 *
 * @return bool
 */
function ap_site_request_is_https() {
	if ( function_exists( 'is_ssl' ) && is_ssl() ) {
		return true;
	}
	$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only checks below.
	if ( isset( $server['HTTP_X_FORWARDED_PROTO'] ) && false !== stripos( (string) $server['HTTP_X_FORWARDED_PROTO'], 'https' ) ) {
		return true;
	}
	if ( isset( $server['HTTP_CF_VISITOR'] ) && false !== stripos( (string) $server['HTTP_CF_VISITOR'], 'https' ) ) {
		return true;
	}
	if ( isset( $server['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( (string) $server['HTTP_X_FORWARDED_SSL'] ) ) {
		return true;
	}
	if ( isset( $server['HTTP_X_FORWARDED_PORT'] ) && '443' === (string) $server['HTTP_X_FORWARDED_PORT'] ) {
		return true;
	}
	if ( isset( $server['REQUEST_SCHEME'] ) && 'https' === strtolower( (string) $server['REQUEST_SCHEME'] ) ) {
		return true;
	}
	return false;
}

/**
 * The HTTPS URL for this request, or '' when it should not be redirected.
 *
 * @return string
 */
function ap_site_https_target() {
	if ( ! ap_site_feature_on( 'https_redirect' ) ) {
		return '';
	}
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
		return '';
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return ''; // A redirect would turn a POST into a GET; forms post to https anyway.
	}
	if ( ap_site_request_is_https() ) {
		return '';
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- re-checked below and by wp_redirect().
	if ( '' === $uri || '/' !== $uri[0] || preg_match( '/[\r\n]/', $uri ) ) {
		$uri = '/';
	}
	// The host is on the production list (ap_site_feature_on checked it), so this
	// can only ever point back to the same site.
	return 'https://' . ap_site_request_host() . $uri;
}

add_action(
	'init',
	static function () {
		$target = ap_site_guard( 'ap_site_https_target', '' );
		if ( is_string( $target ) && '' !== $target ) {
			wp_redirect( $target, 301, 'Asset Panda Site' ); // phpcs:ignore WordPress.Security.SafeRedirect -- same production host, see ap_site_https_target().
			exit;
		}
	},
	0
);
