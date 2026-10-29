<?php
/**
 * Settings, request host and the production check every feature depends on.
 *
 * A feature runs only when ap_site_feature_on() says so: the request host is
 * on the production host list, the host is not a staging/local host, the
 * AP_SITE_DISABLE kill switch is off, and the feature's own setting is on.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default settings. Values are the ones live (www.assetpanda.com) used on
 * 2026-10-16, read with curl from the page source and response headers.
 *
 * @return array<string, mixed>
 */
function ap_site_defaults() {
	return array(
		// Hosts that count as production. Staging and local hosts are refused regardless.
		'production_hosts'         => array( 'www.assetpanda.com' ),

		// Google Tag Manager. Live loads the standard googletagmanager.com snippet.
		'tracking'                 => true,
		'gtm_id'                   => 'GTM-WVPZ4X',
		'loader'                   => 'gtm', // 'gtm' (standard) or 'stape' (first-party custom loader).
		'stape_domain'             => 'data.assetpanda.com',
		'stape_loader'             => 'xlaymtlkg.js',
		'stape_query'              => 'a7a=Dg9RMzk0TDsxOzZANV8XUVVFXVIJF1wNFBgJAB0YDwgGRQ8bAA%3D%3D',
		// Paths without the container: the container's own login exclusions (regex
		// /asset-panda-login(.*)|/login-splash(.*)|/users/sign_in(.*) in v240).
		'tracking_exclude'         => array( '/login-splash*', '/asset-panda-login*', '/users/sign_in*' ),

		// Cookiebot. Live loads it on every page, the login splash pages included.
		'cookiebot'                => true,
		'cookiebot_id'             => 'd8f06714-cf56-4332-a419-df2b99201fc0',
		'cookiebot_exclude'        => array(),

		// Geotargetly (pre-consent geolocation for the old theme's Core/International nav).
		// Off: the 2026 header does the same with /cdn-cgi/trace.
		'geotargetly'              => false,

		// Security headers, as live sends them from PHP.
		'headers'                  => true,
		'csp_report_only'          => false,
		// X-Frame-Options is left off here so nothing can block the product login
		// screen from framing these pages (CSP frame-ancestors still restricts it).
		'xfo_omit'                 => array( '/login-splash*' ),

		'https_redirect'           => true,

		// Visitors cannot list accounts: wp/v2/users (any URL form), ?author=N and
		// oEmbed author fields answer nothing to anyone not logged in. Any host.
		'user_enumeration'         => true,

		// WordPress's emoji detection script (any host). Its blob Worker stalls the main thread for ~400 ms in
		// Lighthouse and PageSpeed runs (TBT 1.4–1.7 s on the AIP page); every supported browser renders emoji itself.
		'no_emoji_scripts'         => true,

		// Homepage hero: frame the live Ursa demo instead of the posters.
		'ursa_demo'                => true,
		'ursa_demo_url'            => 'https://d11kkw4bm5u36o.cloudfront.net/',

		// Yoast sitemap exclusions. Switched on by the SEO apply action.
		'sitemap_exclude'          => false,
		'sitemap_exclude_patterns' => array(
			// Thank-you pages (form redirect targets and conversion triggers).
			'*thank-you*',
			'/thanks-*',
			// Framed by the product login screen.
			'/login-splash*',
			'/404-not-found',
			// Test and internal pages.
			'/test-*',
			'/chat-test-page',
			'/identify-hubspot-test',
			'/intercom-testing',
			'/filter-employee-traffic',
			'/asset-panda-2-0-b',
			'/asset-panda-2-0-light-theme-page',
			'/new-pricing-page-mockup-plugin',
			'/panda-pit-stop-post-signup',
			'/post-sm-survey-page',
			'/post-sm-swag-survey-page',
			'/pricing-2',
			'/pricing-v2',
			// Placeholders that live redirects or retired.
			'/about',
			'/asset-panda-for-fire',
			'/asset-panda-for-police',
			'/enterprise',
			'/resources',
			'/industries',
			'/who-we-help',
			'/see-pricing',
			'/log-in',
			'/products',
			// Published pages whose address redirects to a product page.
			'/solutions/education/higher-education',
			'/solutions/education/school-districts',
			'/solutions/government/public-works',
		),
	);
}

/** Setting keys that are on/off switches. */
function ap_site_bool_keys() {
	return array( 'tracking', 'cookiebot', 'geotargetly', 'headers', 'csp_report_only', 'https_redirect', 'ursa_demo', 'sitemap_exclude', 'user_enumeration', 'no_emoji_scripts' );
}

/** Setting keys that hold path pattern lists. */
function ap_site_pattern_keys() {
	return array( 'tracking_exclude', 'cookiebot_exclude', 'xfo_omit', 'sitemap_exclude_patterns' );
}

/**
 * Current settings merged over the defaults. A damaged option falls back to
 * the defaults instead of failing.
 *
 * @return array<string, mixed>
 */
function ap_site_settings() {
	$defaults = ap_site_defaults();
	$stored   = get_option( AP_SITE_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$settings = $defaults;
	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $stored ) ) {
			continue;
		}
		$value = $stored[ $key ];
		if ( is_bool( $default ) ) {
			$settings[ $key ] = (bool) $value;
		} elseif ( is_array( $default ) ) {
			$settings[ $key ] = is_array( $value ) ? ap_site_string_list( $value ) : $default;
		} else {
			$settings[ $key ] = is_scalar( $value ) ? (string) $value : $default;
		}
	}
	// Emergency loader switch from wp-config.php.
	if ( defined( 'AP_SITE_GTM_LOADER' ) && in_array( AP_SITE_GTM_LOADER, array( 'gtm', 'stape' ), true ) ) {
		$settings['loader'] = AP_SITE_GTM_LOADER;
	}
	return $settings;
}

/**
 * Trimmed, non-empty strings from a list; anything else is dropped.
 *
 * @param array $values Raw list.
 * @return string[]
 */
function ap_site_string_list( array $values ) {
	$out = array();
	foreach ( $values as $value ) {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			$out[] = trim( (string) $value );
		}
	}
	return $out;
}

/**
 * One setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function ap_site_setting( $key ) {
	$settings = ap_site_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
}

/**
 * Saves settings changes through the same validation the settings page uses.
 *
 * @param array<string, mixed> $changes Keys to change.
 * @return bool
 */
function ap_site_update_settings( array $changes ) {
	return update_option( AP_SITE_OPTION, array_merge( ap_site_settings(), $changes ), true );
}

/* ── Host ─────────────────────────────────────────────────────────────── */

/**
 * The host this request was made to, lower case, without port.
 *
 * @return string
 */
function ap_site_request_host() {
	$raw = '';
	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		$raw = (string) $_SERVER['HTTP_HOST']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
	} elseif ( isset( $_SERVER['SERVER_NAME'] ) ) {
		$raw = (string) $_SERVER['SERVER_NAME']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
	}
	return ap_site_normalize_host( $raw );
}

/**
 * Lower case, no port, no trailing dot; '' when it is not a plausible host.
 *
 * @param string $host Raw host.
 * @return string
 */
function ap_site_normalize_host( $host ) {
	$host = strtolower( trim( (string) $host ) );
	if ( preg_match( '/^\[([0-9a-f:.]+)\](?::\d+)?$/', $host, $m ) ) {
		return $m[1];
	}
	if ( substr_count( $host, ':' ) < 2 ) {
		// name:port. More than one colon is a bare IPv6 address: no port to strip.
		$host = preg_replace( '/:\d*$/', '', $host );
	}
	$host = rtrim( (string) $host, '.' );
	if ( '' === $host || ! preg_match( '/^[a-z0-9.:-]+$/', $host ) ) {
		return '';
	}
	return $host;
}

/**
 * Hosts that are never production: staging, WP Engine install domains, local
 * development names and bare IP addresses. This list wins over the settings.
 *
 * @param string $host Normalised host.
 * @return bool
 */
function ap_site_is_blocked_host( $host ) {
	$host = ap_site_normalize_host( $host );
	if ( '' === $host ) {
		return true;
	}
	if ( in_array( $host, array( 'localhost', 'wpenginepowered.com', 'wpengine.com' ), true ) ) {
		return true;
	}
	if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return true;
	}
	if ( false !== strpos( $host, 'assetpanda1dev' ) ) {
		return true;
	}
	$suffixes = array( '.wpenginepowered.com', '.wpengine.com', '.wpenginedev.com', '.localhost', '.local', '.test', '.lndo.site', '.ddev.site' );
	foreach ( $suffixes as $suffix ) {
		if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
			return true;
		}
	}
	return false;
}

/**
 * The production host list: AP_SITE_PRODUCTION_HOSTS in wp-config.php when
 * defined, otherwise the setting. Blocked hosts are dropped.
 *
 * @return string[]
 */
function ap_site_production_hosts() {
	if ( defined( 'AP_SITE_PRODUCTION_HOSTS' ) ) {
		$raw = AP_SITE_PRODUCTION_HOSTS;
		$raw = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
	} else {
		$raw = (array) ap_site_setting( 'production_hosts' );
	}
	$raw   = (array) apply_filters( 'ap_site_production_hosts', $raw );
	$hosts = array();
	foreach ( $raw as $host ) {
		$host = ap_site_normalize_host( (string) $host );
		if ( '' !== $host && ! ap_site_is_blocked_host( $host ) ) {
			$hosts[] = $host;
		}
	}
	return array_values( array_unique( $hosts ) );
}

/**
 * Where this request stands.
 *
 * @return array{host: string, production: bool, reason: string}
 */
function ap_site_environment() {
	$host = ap_site_request_host();
	if ( defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE ) {
		return array(
			'host'       => $host,
			'production' => false,
			'reason'     => 'AP_SITE_DISABLE is set in wp-config.php',
		);
	}
	if ( ap_site_is_blocked_host( $host ) ) {
		return array(
			'host'       => $host,
			'production' => false,
			'reason'     => '' === $host ? 'no request host (CLI, cron)' : 'staging or local host: never production',
		);
	}
	if ( ! in_array( $host, ap_site_production_hosts(), true ) ) {
		return array(
			'host'       => $host,
			'production' => false,
			'reason'     => 'not on the production host list',
		);
	}
	return array(
		'host'       => $host,
		'production' => true,
		'reason'     => 'on the production host list',
	);
}

/** True when this request is to a production host. */
function ap_site_is_production() {
	$env = ap_site_environment();
	return $env['production'];
}

/**
 * True when a feature may run for this request: production host and the
 * feature's setting on.
 *
 * @param string $key Setting key of the feature.
 * @return bool
 */
function ap_site_feature_on( $key ) {
	if ( ! ap_site_is_production() ) {
		return false;
	}
	if ( ! ap_site_setting( $key ) ) {
		return false;
	}
	return (bool) apply_filters( 'ap_site_feature_' . $key, true );
}

/* ── Paths ────────────────────────────────────────────────────────────── */

/**
 * The request path relative to the site root, e.g. /login-splash-1/.
 *
 * @return string
 */
function ap_site_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only matched against patterns.
	$path = explode( '?', $uri, 2 );
	$path = explode( '#', $path[0], 2 );
	$path = rawurldecode( $path[0] );
	$home = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) : '';
	$home = rtrim( $home, '/' );
	if ( '' !== $home && 0 === stripos( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	if ( '' === $path || '/' !== $path[0] ) {
		$path = '/' . $path;
	}
	return $path;
}

/**
 * Lower case, leading slash, no trailing slash (except the root).
 *
 * @param string $path Path or pattern.
 * @return string
 */
function ap_site_normalize_path( $path ) {
	$path = strtolower( trim( (string) $path ) );
	if ( '' === $path ) {
		return '';
	}
	if ( '/' !== $path[0] && '*' !== $path[0] ) {
		$path = '/' . $path;
	}
	if ( strlen( $path ) > 1 ) {
		$path = rtrim( $path, '/' );
	}
	return '' === $path ? '/' : $path;
}

/**
 * Whether a path matches any pattern. A pattern is a path; `*` matches any
 * run of characters; matching ignores case and the trailing slash.
 * `/login-splash*` matches /login-splash-1/ and /login-splash-5-p/;
 * `/products` matches /products/ only.
 *
 * @param string   $path     Request path.
 * @param string[] $patterns Patterns.
 * @return bool
 */
function ap_site_path_matches( $path, $patterns ) {
	$path = ap_site_normalize_path( $path );
	foreach ( (array) $patterns as $pattern ) {
		$pattern = ap_site_normalize_path( $pattern );
		if ( '' === $pattern ) {
			continue;
		}
		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
		if ( preg_match( $regex, $path ) ) {
			return true;
		}
	}
	return false;
}
