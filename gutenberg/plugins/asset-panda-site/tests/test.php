<?php
/**
 * TEST ONLY: integration test against the local WordPress (Local app, PHP 8.2).
 *
 * php test.php <wp-load.php> <results.json>
 * env: AP_TEST_BASE   http://127.0.0.1:<port> (php -S with tests/router.php)
 *      AP_TEST_LIVE   folder with live captures: b_.html (homepage) and h_*.txt (headers)
 *      AP_TEST_STATE  folder with pages.json from setup.php
 *      AP_TEST_TESTS  this folder (for the Yoast stand-in)
 *
 * Live expectations are read from the curl captures of www.assetpanda.com, not
 * from the plugin, so parity is checked against live itself.
 *
 * @package AssetPandaSite
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];

$base  = rtrim( (string) getenv( 'AP_TEST_BASE' ), '/' );
$live  = rtrim( (string) getenv( 'AP_TEST_LIVE' ), '/' );
$pages = json_decode( file_get_contents( getenv( 'AP_TEST_STATE' ) . '/pages.json' ), true );
$WWW   = 'www.assetpanda.com';
$DEV   = 'assetpanda1dev.wpenginepowered.com';

$GLOBALS['results'] = array();
function check( $name, $ok, $detail = '' ) {
	$GLOBALS['results'][] = array(
		'name'   => $name,
		'ok'     => (bool) $ok,
		'detail' => $detail,
	);
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $detail && ! $ok ? '  -- ' . ( is_string( $detail ) ? $detail : json_encode( $detail ) ) : '' ) . "\n";
}

/** HTTP request to php -S with a chosen Host header. */
function http( $method, $path, $host, array $headers = array(), $body = null ) {
	global $base;
	$ch   = curl_init( $base . $path );
	$hdrs = array( 'Host: ' . $host );
	foreach ( $headers as $k => $v ) {
		$hdrs[] = $k . ': ' . $v;
	}
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER     => $hdrs,
			CURLOPT_TIMEOUT        => 60,
		)
	);
	if ( 'HEAD' === $method ) {
		curl_setopt( $ch, CURLOPT_NOBODY, true );
	}
	if ( null !== $body ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
	$raw  = (string) curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$hlen = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );
	return array(
		'code'    => $code,
		'headers' => parse_headers( substr( $raw, 0, $hlen ) ),
		'body'    => substr( $raw, $hlen ),
	);
}

/** name (lower case) => last value. */
function parse_headers( $text ) {
	$out = array();
	foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
		if ( preg_match( '/^([A-Za-z0-9-]+):\s*(.*)$/', $line, $m ) ) {
			$out[ strtolower( $m[1] ) ] = trim( $m[2] );
		}
	}
	return $out;
}

function squash( $s ) {
	return trim( preg_replace( '/\s+/', ' ', (string) $s ) );
}

$https = array( 'X-Forwarded-Proto' => 'https' );
$SEC   = array( 'content-security-policy', 'content-security-policy-report-only', 'permissions-policy', 'referrer-policy', 'x-content-type-options', 'x-frame-options', 'x-xss-protection' );

/* ── Live references (curl captures of www.assetpanda.com) ─────────── */

$live_home = file_get_contents( $live . '/b_.html' );
preg_match( '#<script data-no-defer="1" data-no-optimize="1">\(function\(w,d,s,l,i\).*?</script>#s', $live_home, $m );
$live_gtm = squash( $m[0] );
preg_match( '#<noscript><iframe data-no-defer="1" data-no-optimize="1" src="([^"]+)"#', $live_home, $m );
$live_ns_src = $m[1];
preg_match( '#<script id="Cookiebot".*?</script>#s', $live_home, $m );
$live_cb = squash( preg_replace( array( '/"\s+/', '/>\s+</' ), array( '" ', '><' ), $m[0] ) );
// The Stape loader exactly as the 2026-10-15 AP Tag Loader would have rewritten live's snippet.
$live_stape = squash(
	str_replace(
		array( "'https://www.googletagmanager.com/gtm.js?id='+i+dl", "'dataLayer','GTM-WVPZ4X')" ),
		array( "'https://data.assetpanda.com/xlaymtlkg.js?'+i", "'dataLayer','a7a=Dg9RMzk0TDsxOzZANV8XUVVFXVIJF1wNFBgJAB0YDwgGRQ8bAA%3D%3D')" ),
		$live_gtm
	)
);
$live_hdr = array();
foreach ( array( 'home' => 'h_.txt', 'demo' => 'h_get-a-demo_.txt', 'splash' => 'h_login-splash-1_.txt', 'notfound' => 'h_404-not-found_.txt', 'robots' => 'robots.txt.h' ) as $k => $f ) {
	$live_hdr[ $k ] = is_readable( $live . '/' . $f ) ? parse_headers( file_get_contents( $live . '/' . $f ) ) : array();
}
check( 'live captures loaded', '' !== $live_gtm && '' !== $live_cb && ! empty( $live_hdr['home']['content-security-policy'] ) && ! empty( $live_hdr['robots']['content-security-policy'] ) );

/* ── Host and path rules (unit level) ───────────────────────────────── */

$blocked = array( $DEV, 'assetpanda1.wpenginepowered.com', 'foo.wpengine.com', 'localhost', '127.0.0.1', '[::1]:8080', 'asset-panda-local.local', 'x.test', '', 'wpenginepowered.com' );
foreach ( $blocked as $h ) {
	check( 'blocked host: ' . ( '' === $h ? '(empty)' : $h ), ap_site_is_blocked_host( ap_site_normalize_host( $h ) ) );
}
check( 'www not blocked', ! ap_site_is_blocked_host( 'www.assetpanda.com' ) );
check( 'host normalise: WWW.AssetPanda.com:443', 'www.assetpanda.com' === ap_site_normalize_host( 'WWW.AssetPanda.com:443' ) );
check( 'path /login-splash-1/ matches /login-splash*', ap_site_path_matches( '/login-splash-1/', array( '/login-splash*' ) ) );
check( 'path /login-splash-5-p/ matches /login-splash*', ap_site_path_matches( '/LOGIN-SPLASH-5-P/', array( '/login-splash*' ) ) );
check( 'path /x/login-splash-9/ does not match /login-splash*', ! ap_site_path_matches( '/x/login-splash-9/', array( '/login-splash*' ) ) );
check( 'path /products/ matches /products, child does not', ap_site_path_matches( '/products/', array( '/products' ) ) && ! ap_site_path_matches( '/products/asset-panda-for-fire/', array( '/products' ) ) );
check( 'path /k12-thank-you-page/ matches *thank-you*', ap_site_path_matches( '/k12-thank-you-page/', array( '*thank-you*' ) ) );
check( 'value comparison tolerates Yoast clean-up (@, trailing slash) but not real changes', ap_site_same( 'assetpanda', '@assetpanda' ) && ap_site_same( array( 'https://x.example/a/' ), array( 'https://x.example/a' ) ) && ! ap_site_same( false, true ) && ap_site_same( '5', 5 ) && ! ap_site_same( 'sc-pipe', 'sc-dash' ) && ! ap_site_same( null, '' ) );

/* ── Tracking: only on the production host, never on staging/local ─── */

$p_page   = $pages['page']['path'];
$p_splash = $pages['splash']['path'];

$r = http( 'GET', $p_page, $WWW, $https );
file_put_contents( getenv( 'AP_TEST_STATE' ) . '/sample-www-page.html', $r['body'] );
file_put_contents( getenv( 'AP_TEST_STATE' ) . '/sample-www-page.headers.json', json_encode( $r['headers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
check( 'www page: 200', 200 === $r['code'], $r['code'] );
check( 'www page: GTM head snippet identical to live (whitespace-normalised)', false !== strpos( squash( $r['body'] ), $live_gtm ), substr( $r['body'], 0, 300 ) );
check( 'www page: GTM snippet inside <head>', false !== ( $hp = strpos( $r['body'], 'googletagmanager.com/gtm.js' ) ) && $hp < strpos( $r['body'], '</head>' ) );
check( 'www page: noscript iframe src identical to live', false !== strpos( $r['body'], 'src="' . $live_ns_src . '"' ) );
$bo = strpos( $r['body'], '<body' );
$ns = strpos( $r['body'], 'googletagmanager.com/ns.html' );
check( 'www page: noscript right after <body> (wp_body_open)', false !== $bo && false !== $ns && $ns > $bo && ( $ns - strpos( $r['body'], '>', $bo ) ) < 400, $ns - $bo );
check( 'www page: Cookiebot tag identical to live (whitespace-normalised)', false !== strpos( squash( preg_replace( array( '/"\s+/', '/>\s+</' ), array( '" ', '><' ), $r['body'] ) ), $live_cb ), $live_cb );
check( 'www page: Cookiebot is the last script before </body>', preg_match( '#<script id="Cookiebot"[^>]*></script>\s*</body>#', $r['body'] ) );
check( 'www page: preconnect googletagmanager + cookiebot', false !== strpos( $r['body'], "rel='preconnect' href='https://www.googletagmanager.com'" ) && false !== strpos( $r['body'], "rel='preconnect' href='https://consent.cookiebot.com'" ) );
check( 'www page: preconnect + dns-prefetch for the two HubSpot form hosts, as live', false !== strpos( $r['body'], "rel='preconnect' href='https://js-na2.hsforms.net'" ) && false !== strpos( $r['body'], "rel='preconnect' href='https://forms-na2.hsforms.com'" ) && false !== strpos( $r['body'], "rel='dns-prefetch' href='//js-na2.hsforms.net'" ) && false !== strpos( $r['body'], "rel='dns-prefetch' href='//forms-na2.hsforms.com'" ) );
check( 'www page: exactly one GTM loader and one noscript', 1 === substr_count( $r['body'], 'googletagmanager.com/gtm.js' ) && 1 === substr_count( $r['body'], 'googletagmanager.com/ns.html' ) );
check( 'www page: no Geotargetly (off by default)', false === strpos( $r['body'], 'g792337341' ) );

foreach ( array( $DEV => 'staging host', 'localhost:8898' => 'localhost', 'asset-panda-local.local' => 'local site host', 'assetpanda1.wpenginepowered.com' => 'live install domain', 'assetpanda.com' => 'apex (not on the list)' ) as $h => $label ) {
	$r = http( 'GET', $p_page, $h, $https );
	check( $label . ': no GTM, Cookiebot or headers', 200 === $r['code'] && false === strpos( $r['body'], 'googletagmanager' ) && false === strpos( $r['body'], 'cookiebot' ) && ! isset( $r['headers']['content-security-policy'] ) && ! isset( $r['headers']['permissions-policy'] ), $r['code'] );
}

$r = http( 'GET', $p_splash, $WWW, $https );
check( 'www /login-splash-1/: 200, no GTM head or noscript', 200 === $r['code'] && false === strpos( $r['body'], 'googletagmanager.com/gtm.js' ) && false === strpos( $r['body'], 'googletagmanager.com/ns.html' ) && false === strpos( $r['body'], "href='https://www.googletagmanager.com'" ) );
check( 'www /login-splash-1/: Cookiebot kept (as live)', false !== strpos( $r['body'], 'consent.cookiebot.com/uc.js' ) );
$r = http( 'GET', $pages['splash_p']['path'], $WWW, $https );
check( 'www /login-splash-5-p/: no GTM', false === strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
$r = http( 'GET', $pages['nested']['path'], $WWW, $https );
check( 'www ' . $pages['nested']['path'] . ': GTM present (pattern is anchored)', false !== strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
$r = http( 'GET', $pages['thanks']['path'], $WWW, $https );
check( 'www thank-you page: GTM present (conversion triggers need it)', false !== strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
$r = http( 'GET', '/feed/', $WWW, $https );
check( 'www /feed/: no tracking markup', false === strpos( $r['body'], 'googletagmanager' ) );
$r = http( 'GET', $p_page, $WWW, $https + array( 'X-AP-Test-Disable' => '1' ) );
check( 'AP_SITE_DISABLE: nothing on www', false === strpos( $r['body'], 'googletagmanager' ) && ! isset( $r['headers']['content-security-policy'] ) );

/* ── Settings switches ──────────────────────────────────────────────── */

ap_site_update_settings( array( 'loader' => 'stape' ) );
$r = http( 'GET', $p_page, $WWW, $https );
check( 'stape loader: snippet identical to the AP Tag Loader rewrite of live', false !== strpos( squash( $r['body'] ), $live_stape ) );
check( 'stape loader: noscript from data.assetpanda.com, no googletagmanager loader', false !== strpos( $r['body'], 'src="https://data.assetpanda.com/ns.html?id=GTM-WVPZ4X"' ) && false === strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
check( 'stape loader: preconnect data.assetpanda.com', false !== strpos( $r['body'], "rel='preconnect' href='https://data.assetpanda.com'" ) );
ap_site_update_settings( array( 'stape_query' => 'not valid!' ) );
$r = http( 'GET', $p_page, $WWW, $https );
check( 'stape with an invalid token falls back to the standard loader', false !== strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
ap_site_update_settings( array( 'loader' => 'gtm', 'stape_query' => ap_site_defaults()['stape_query'] ) );

ap_site_update_settings( array( 'tracking' => false ) );
$r = http( 'GET', $p_page, $WWW, $https );
check( 'tracking switched off: no GTM, Cookiebot still on', false === strpos( $r['body'], 'googletagmanager.com/gtm.js' ) && false !== strpos( $r['body'], 'cookiebot' ) );
ap_site_update_settings( array( 'tracking' => true, 'geotargetly' => true ) );
$r = http( 'GET', $p_page, $WWW, $https );
check( 'geotargetly switched on: script present', false !== strpos( $r['body'], 'g792337341.co/geolocation' ) );
ap_site_update_settings( array( 'geotargetly' => false ) );

// Staging hosts added to the list (raw write, bypassing validation) are still refused.
$raw                     = ap_site_settings();
$raw['production_hosts'] = array( 'www.assetpanda.com', $DEV, 'localhost' );
update_option( AP_SITE_OPTION, $raw );
$r = http( 'GET', $p_page, $DEV, $https );
check( 'staging host forced onto the list: still nothing', false === strpos( $r['body'], 'googletagmanager' ) && ! isset( $r['headers']['content-security-policy'] ) );
$r = http( 'GET', $p_page, 'localhost', $https );
check( 'localhost forced onto the list: still nothing', false === strpos( $r['body'], 'googletagmanager' ) );
update_option( AP_SITE_OPTION, 'garbage' );
$r = http( 'GET', $p_page, $WWW, $https );
check( 'damaged settings option: no error, defaults apply', 200 === $r['code'] && false !== strpos( $r['body'], 'googletagmanager.com/gtm.js' ) );
delete_option( AP_SITE_OPTION );

/* ── Security headers per path vs live ──────────────────────────────── */

$compare = static function ( $label, $ours, $theirs, $skip = array() ) use ( $SEC ) {
	$diff = array();
	foreach ( $SEC as $h ) {
		if ( in_array( $h, $skip, true ) ) {
			continue;
		}
		$a = isset( $ours[ $h ] ) ? $ours[ $h ] : null;
		$b = isset( $theirs[ $h ] ) ? $theirs[ $h ] : null;
		if ( $a !== $b ) {
			$diff[ $h ] = array(
				'ours' => $a,
				'live' => $b,
			);
		}
	}
	check( $label, ! $diff, $diff );
};
$r = http( 'GET', '/', $WWW, $https );
$compare( 'headers / = live / (CSP-RO off by default)', $r['headers'], $live_hdr['home'], array( 'content-security-policy-report-only' ) );
check( 'headers /: no CSP report-only by default', ! isset( $r['headers']['content-security-policy-report-only'] ) );
check( 'headers /: no HSTS from PHP (WP Engine rule)', ! isset( $r['headers']['strict-transport-security'] ) );
$r = http( 'GET', $p_page, $WWW, $https );
$compare( 'headers ordinary page = live /get-a-demo/', $r['headers'], $live_hdr['demo'], array( 'content-security-policy-report-only' ) );
$r = http( 'GET', $p_splash, $WWW, $https );
$compare( 'headers /login-splash-1/ = live except X-Frame-Options', $r['headers'], $live_hdr['splash'], array( 'content-security-policy-report-only', 'x-frame-options' ) );
check( 'headers /login-splash-1/: no X-Frame-Options; frame-ancestors allows the 4 login hosts', ! isset( $r['headers']['x-frame-options'] ) && false !== strpos( $r['headers']['content-security-policy'], 'https://login.assetpanda.com' ) && false !== strpos( $r['headers']['content-security-policy'], 'https://admin.assetpanda.com' ) );
$r = http( 'GET', '/robots.txt', $WWW, $https );
$compare( 'headers /robots.txt = live robots.txt', $r['headers'], $live_hdr['robots'], array( 'content-security-policy-report-only' ) );
$r = http( 'GET', '/this-page-does-not-exist-ap-site/', $WWW, $https );
$compare( 'headers 404 = live (/404-not-found/)', $r['headers'], $live_hdr['notfound'], array( 'content-security-policy-report-only' ) );
check( '404 stays a real 404', 404 === $r['code'], $r['code'] );

ap_site_update_settings( array( 'csp_report_only' => true, 'xfo_omit' => array() ) );
$r = http( 'GET', $p_splash, $WWW, $https );
$compare( 'exact-live mode (CSP-RO on, xfo_omit empty): /login-splash-1/ = live, all headers', $r['headers'], $live_hdr['splash'] );
delete_option( AP_SITE_OPTION );
$r = http( 'GET', '/wp-json/', $WWW, $https );
check( 'REST responses: no CSP/XFO (as live)', ! isset( $r['headers']['content-security-policy'] ) && ! isset( $r['headers']['x-frame-options'] ) );

/* ── Forced HTTPS ───────────────────────────────────────────────────── */

$r = http( 'GET', $p_page . '?utm_source=x&gclid=y', $WWW );
check( 'http www: 301 to https, same path and query', 301 === $r['code'] && 'https://www.assetpanda.com' . $p_page . '?utm_source=x&gclid=y' === ( isset( $r['headers']['location'] ) ? $r['headers']['location'] : '' ), $r['code'] . ' ' . ( isset( $r['headers']['location'] ) ? $r['headers']['location'] : '' ) );
$r = http( 'GET', $p_page, $WWW, array( 'CF-Visitor' => '{"scheme":"https"}' ) );
check( 'Cloudflare CF-Visitor https: no redirect (no loop)', 200 === $r['code'], $r['code'] );
$r = http( 'HEAD', $p_page, $WWW );
check( 'http www HEAD: 301', 301 === $r['code'], $r['code'] );
$r = http( 'POST', $p_page, $WWW, array(), 'a=1' );
check( 'http www POST: not redirected', 301 !== $r['code'], $r['code'] );
$r = http( 'GET', $p_page, $DEV );
check( 'http staging: no redirect', 200 === $r['code'], $r['code'] );

/* ── Homepage Ursa demo ─────────────────────────────────────────────── */

$r = http( 'GET', '/', $WWW, $https );
check( 'www homepage: Ursa demo iframe from live URL, no poster', false !== strpos( $r['body'], '<iframe class="ap-ursa-frame" src="https://d11kkw4bm5u36o.cloudfront.net/"' ) && false === strpos( $r['body'], 'ap-ursa-poster' ), substr( $r['body'], strpos( $r['body'], 'ap-hero-visual' ) ?: 0, 400 ) );
$r = http( 'GET', '/', $DEV, $https );
check( 'staging homepage: poster, no iframe', false !== strpos( $r['body'], 'ap-ursa-poster' ) && false === strpos( $r['body'], 'd11kkw4bm5u36o' ) );
ap_site_update_settings( array( 'ursa_demo' => false ) );
$r = http( 'GET', '/', $WWW, $https );
check( 'www homepage with ursa_demo off: poster', false !== strpos( $r['body'], 'ap-ursa-poster' ) && false === strpos( $r['body'], 'd11kkw4bm5u36o' ) );
delete_option( AP_SITE_OPTION );

/* ── REST: permissions ──────────────────────────────────────────────── */

$rest = static function ( $method, $route, $params = array() ) {
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return array(
		'status' => $res->get_status(),
		'data'   => rest_get_server()->response_to_data( $res, false ),
	);
};
$admin_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin     = (int) $admin_ids[0];
$editor    = wp_insert_user(
	array(
		'user_login' => 'ap_site_test_editor',
		'user_pass'  => wp_generate_password( 24 ),
		'user_email' => 'ap-site-test-editor@example.invalid',
		'role'       => 'editor',
	)
);
foreach ( array( '/ap-site/v1/apply-seo-settings', '/ap-site/v1/apply-identity' ) as $route ) {
	wp_set_current_user( 0 );
	$a = $rest( 'POST', $route, array( 'dry_run' => true ) );
	wp_set_current_user( (int) $editor );
	$b = $rest( 'POST', $route, array( 'dry_run' => true ) );
	check( $route . ': logged out 401, editor 403', 401 === $a['status'] && 403 === $b['status'], array( $a['status'], $b['status'] ) );
}
wp_set_current_user( 0 );

/* ── User enumeration guard (1.1.0) ─────────────────────────────────── */

foreach ( array( '/wp-json/wp/v2/users', '/?rest_route=/wp/v2/users', '/wp-json/wp/v2/users/' . $admin, '/index.php?rest_route=/wp/v2/users/' . $admin ) as $path ) {
	$r = http( 'GET', $path, $WWW, $https );
	check( 'guard: visitor ' . $path . ' is 401', 401 === $r['code'], $r['code'] );
}
$r = http( 'GET', '/?author=' . $admin, $WWW, $https );
check( 'guard: visitor ?author=N is 404 with no redirect', 404 === $r['code'] && ! isset( $r['headers']['location'] ), $r['code'] . ' ' . ( isset( $r['headers']['location'] ) ? $r['headers']['location'] : '' ) );
$r = http( 'GET', '/?author=' . $admin, $DEV, $https );
check( 'guard: any host (staging ?author=N is 404 too)', 404 === $r['code'], $r['code'] );
$r = http( 'GET', '/wp-json/oembed/1.0/embed?url=' . rawurlencode( get_option( 'home' ) . $p_page ), $WWW, $https );
check( 'guard: oEmbed carries no author_name / author_url', 200 === $r['code'] && false === strpos( $r['body'], 'author_name' ) && false === strpos( $r['body'], 'author_url' ), $r['code'] . ' ' . substr( $r['body'], 0, 200 ) );
wp_set_current_user( $admin );
$a = $rest( 'GET', '/wp/v2/users', array( 'per_page' => 1 ) );
check( 'guard: logged-in users route still 200', 200 === $a['status'], $a['status'] );
wp_set_current_user( 0 );
ap_site_update_settings( array( 'user_enumeration' => false ) );
$r = http( 'GET', '/?rest_route=/wp/v2/users', $WWW, $https );
check( 'guard off: visitor users route answers 200 again', 200 === $r['code'], $r['code'] );
delete_option( AP_SITE_OPTION );
check( '/status: logged out 401', 401 === $rest( 'GET', '/ap-site/v1/status' )['status'] );
$r = http( 'POST', '/wp-json/ap-site/v1/apply-identity', $DEV, $https, '' );
check( 'HTTP POST apply-identity without auth: 401', 401 === $r['code'], $r['code'] );
$r = http( 'GET', '/wp-json/ap-site/v1/apply-identity', $DEV, $https );
check( 'HTTP GET apply-identity: not allowed (404/405)', in_array( $r['code'], array( 404, 405 ), true ), $r['code'] );
wp_set_current_user( $admin );

$st = $rest( 'GET', '/ap-site/v1/status' );
check( '/status as admin: 200, local host not production', 200 === $st['status'] && false === $st['data']['production'], $st['data'] );

/* ── Identity apply: dry run, apply, repeat ─────────────────────────── */

$watch  = array( 'blogname', 'blogdescription', 'timezone_string', 'gmt_offset', 'site_icon' );
$before = array();
foreach ( $watch as $o ) {
	$before[ $o ] = get_option( $o );
}
$att_count = static function () {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ap_site_asset' WHERE p.post_type = 'attachment'" );
};
$d = $rest( 'POST', '/ap-site/v1/apply-identity', array( 'dry_run' => true ) );
$same = true;
foreach ( $watch as $o ) {
	wp_cache_delete( $o, 'options' );
	$same = $same && get_option( $o ) == $before[ $o ]; // phpcs:ignore Universal.Operators.StrictComparisons
}
check( 'identity dry run: reports changes, writes nothing', 200 === $d['status'] && count( $d['data']['changed'] ) >= 4 && $same && 0 === $att_count(), $d['data'] );

$a1 = $rest( 'POST', '/ap-site/v1/apply-identity' );
check( 'identity apply #1: 200, changed blogname/blogdescription/timezone/gmt_offset/site_icon, none failed', 200 === $a1['status'] && empty( $a1['data']['failed'] ) && 5 === count( array_intersect( array_keys( $a1['data']['changed'] ), $watch ) ), $a1['data'] );
check( 'identity values', 'Asset Panda' === get_option( 'blogname' ) && 'Asset Management Software &amp; Asset Intelligence Platform' === get_option( 'blogdescription' ) && '' === get_option( 'timezone_string' ) && '-5' === (string) get_option( 'gmt_offset' ), array( get_option( 'blogname' ), get_option( 'blogdescription' ), get_option( 'timezone_string' ), get_option( 'gmt_offset' ) ) );
$icon = (int) get_option( 'site_icon' );
$meta = wp_get_attachment_metadata( $icon );
check( 'site icon: attachment, 512x512 PNG, site-icon sizes made', $icon && 'image/png' === get_post_mime_type( $icon ) && 512 === (int) $meta['width'] && isset( $meta['sizes']['site_icon-32'], $meta['sizes']['site_icon-180'], $meta['sizes']['site_icon-192'], $meta['sizes']['site_icon-270'] ), $meta );
check( 'site icon source: ' . ( isset( $a1['data']['site_icon']['source'] ) ? $a1['data']['site_icon']['source'] : '?' ), true );
// Cloudflare Polish serves non-browser agents a recompressed PNG (it zeroes the hidden colour of
// fully transparent pixels), so compare what is visible: RGBA, with transparent pixels as one value.
$pixels = static function ( $file ) {
	$im = @imagecreatefrompng( $file );
	if ( ! $im ) {
		return '';
	}
	$h = hash_init( 'sha256' );
	for ( $y = 0; $y < imagesy( $im ); $y++ ) {
		for ( $x = 0; $x < imagesx( $im ); $x++ ) {
			$c = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );
			hash_update( $h, 127 === $c['alpha'] ? 'T' : pack( 'C4', $c['red'], $c['green'], $c['blue'], $c['alpha'] ) );
		}
	}
	return hash_final( $h );
};
$px_ours = $pixels( get_attached_file( $icon ) );
check( 'site icon pixels identical to live favicon (sha256 of file: ' . substr( hash_file( 'sha256', get_attached_file( $icon ) ), 0, 12 ) . ')', '' !== $px_ours && $px_ours === $pixels( WP_PLUGIN_DIR . '/asset-panda-site/assets/site-icon-512.png' ) );
$a2 = $rest( 'POST', '/ap-site/v1/apply-identity' );
check( 'identity apply #2: nothing changed, nothing failed, no new attachment', 200 === $a2['status'] && empty( $a2['data']['changed'] ) && empty( $a2['data']['failed'] ) && 1 === $att_count(), $a2['data'] );
$a3 = $rest( 'POST', '/ap-site/v1/apply-identity', array( 'force_icon' => true ) );
check( 'identity apply with force_icon (icon already ours): unchanged', empty( $a3['data']['changed'] ) && 1 === $att_count(), $a3['data'] );
$r = http( 'GET', $p_page, $DEV, $https );
check( 'front end prints the site icon <link rel="icon"> and the new title', false !== strpos( $r['body'], 'rel="icon"' ) && false !== strpos( $r['body'], 'Asset Panda</title>' ), substr( $r['body'], 0, 200 ) );

/* ── SEO apply: without Yoast, then with the stand-in ───────────────── */

$n = $rest( 'POST', '/ap-site/v1/apply-seo-settings' );
check( 'SEO apply without Yoast: 409 ap_site_no_yoast, nothing written', 409 === $n['status'] && 'ap_site_no_yoast' === $n['data']['code'] && false === get_option( 'wpseo_titles' ), $n );

require_once getenv( 'AP_TEST_TESTS' ) . '/yoast-stub.php';
$d = $rest( 'POST', '/ap-site/v1/apply-seo-settings', array( 'dry_run' => true ) );
check( 'SEO dry run: reports changes, writes nothing', 200 === $d['status'] && count( $d['data']['changed'] ) > 5 && false === get_option( 'wpseo' ) && false === get_option( 'wpseo_titles' ) && false === get_option( 'wpseo_social' ) && ! ap_site_setting( 'sitemap_exclude' ), $d['data'] );
$s1 = $rest( 'POST', '/ap-site/v1/apply-seo-settings' );
$want_changed = array( 'googleverify', 'msverify', 'enable_llms_txt', 'facebook_site', 'twitter_site', 'other_social_urls', 'company_name', 'title-tax-category', 'disable-author', 'company_logo', 'company_logo_id', 'company_logo_meta', 'ap_site:sitemap_exclude' );
check( 'SEO apply #1: 200, expected keys changed, none failed', 200 === $s1['status'] && empty( $s1['data']['failed'] ) && ! array_diff( $want_changed, array_keys( $s1['data']['changed'] ) ), array( 'changed' => array_keys( $s1['data']['changed'] ), 'failed' => $s1['data']['failed'] ) );
WPSEO_Options::clear_cache();
$v = array(
	'googleverify'      => WPSEO_Options::get( 'googleverify' ),
	'msverify'          => WPSEO_Options::get( 'msverify' ),
	'enable_llms_txt'   => WPSEO_Options::get( 'enable_llms_txt' ),
	'facebook_site'     => WPSEO_Options::get( 'facebook_site' ),
	'twitter_site'      => WPSEO_Options::get( 'twitter_site' ),
	'other_social_urls' => WPSEO_Options::get( 'other_social_urls' ),
	'company_name'      => WPSEO_Options::get( 'company_name' ),
	'separator'         => WPSEO_Options::get( 'separator' ),
	'title-resource'    => WPSEO_Options::get( 'title-resource' ),
	'noindex-newsroom'  => WPSEO_Options::get( 'noindex-newsroom' ),
	'noindex-tax-rt'    => WPSEO_Options::get( 'noindex-tax-resource-types' ),
	'disable-author'    => WPSEO_Options::get( 'disable-author' ),
);
check(
	'Yoast values stored',
	'5R0O3t1WlUReAiV67NmZm-TZ5XPgkrvXas6D0RFgYDM' === $v['googleverify'] && '71FFA2A80364137CC63DA962747E39F9' === $v['msverify'] && true === $v['enable_llms_txt']
	&& 'https://business.facebook.com/AssetPanda/' === $v['facebook_site'] && 'assetpanda' === $v['twitter_site'] && 3 === count( $v['other_social_urls'] )
	&& 'Asset Panda' === $v['company_name'] && 'sc-dash' === $v['separator'] && '%%title%% %%page%% %%sep%% %%sitename%%' === $v['title-resource']
	&& false === $v['noindex-newsroom'] && false === $v['noindex-tax-rt'] && true === $v['disable-author'],
	$v
);
$logo_id = (int) WPSEO_Options::get( 'company_logo_id' );
$lmeta   = WPSEO_Options::get( 'company_logo_meta' );
$amd     = wp_get_attachment_metadata( $logo_id );
check( 'Organization logo: SVG attachment 109x41, Yoast company_logo/_id/_meta point at it', $logo_id && 'image/svg+xml' === get_post_mime_type( $logo_id ) && 109 === (int) $amd['width'] && 41 === (int) $amd['height'] && wp_get_attachment_url( $logo_id ) === WPSEO_Options::get( 'company_logo' ) && is_array( $lmeta ) && $lmeta['url'] === wp_get_attachment_url( $logo_id ) && 109 === $lmeta['width'], array( $logo_id, $amd, $lmeta ) );
check( 'logo file identical to live logo (sha256)', hash_file( 'sha256', get_attached_file( $logo_id ) ) === '5a1bb334512c79fe49d9749a68f6f854f6f2ccbe074a3729737d6fc977d1e4cd' );
check( 'logo source: ' . ( isset( $s1['data']['logo']['source'] ) ? $s1['data']['logo']['source'] : '?' ), true );
$s2 = $rest( 'POST', '/ap-site/v1/apply-seo-settings' );
check( 'SEO apply #2: nothing changed, nothing failed, no new attachment', 200 === $s2['status'] && empty( $s2['data']['changed'] ) && empty( $s2['data']['failed'] ) && 2 === $att_count(), array( 'changed' => $s2['data']['changed'], 'failed' => $s2['data']['failed'] ) );
$s3 = $rest( 'POST', '/ap-site/v1/apply-seo-settings', array( 'refresh_llms_txt' => true ) );
check( 'SEO apply with refresh_llms_txt: llms.txt regenerated, still on, nothing else changed', empty( $s3['data']['changed'] ) && true === WPSEO_Options::get( 'enable_llms_txt' ) && false !== strpos( implode( ' ', $s3['data']['notes'] ), 'regenerated' ), $s3['data']['notes'] );

$bad_key = static function ( $plan ) {
	$plan[] = array( 'key' => 'ap-test-unknown-key', 'value' => 'x' );
	return $plan;
};
add_filter( 'ap_site_seo_plan', $bad_key );
$s4 = $rest( 'POST', '/ap-site/v1/apply-seo-settings' );
remove_filter( 'ap_site_seo_plan', $bad_key );
check( 'SEO apply with a key Yoast does not know: reported failed, ok=false, nothing else changed', isset( $s4['data']['failed']['ap-test-unknown-key'] ) && false === $s4['data']['ok'] && empty( $s4['data']['changed'] ), $s4['data']['failed'] );

$ex = apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array( 999999 ) );
$in = static function ( $k ) use ( $ex, $pages ) {
	return in_array( (int) $pages[ $k ]['id'], $ex, true );
};
check( 'sitemap exclusions: splash, splash-p, thanks-, *thank-you* excluded', $in( 'splash' ) && $in( 'splash_p' ) && $in( 'thanks' ) && $in( 'ty_vertical' ) && in_array( 999999, $ex, true ), $ex );
check( 'sitemap exclusions: home, ordinary page, parent and nested page kept', ! $in( 'home' ) && ! $in( 'page' ) && ! $in( 'parent' ) && ! $in( 'nested' ), $ex );

/* ── Settings page and its validation ───────────────────────────────── */

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$clean = ap_site_sanitize_settings(
	array(
		'_form'            => '1',
		'production_hosts' => "www.assetpanda.com\nassetpanda1dev.wpenginepowered.com\nlocalhost\nnot a host",
		'gtm_id'           => 'nope',
		'loader'           => 'stape',
		'stape_query'      => 'a7a=abc"<script>',
		'tracking'         => '1',
		'tracking_exclude' => "/login-splash*\njavascript:alert(1)",
		'ursa_demo_url'    => 'http://insecure.example/',
	)
);
check(
	'settings validation: staging/local hosts and junk refused, bad IDs keep the old value, unticked boxes off',
	array( 'www.assetpanda.com' ) === $clean['production_hosts'] && 'GTM-WVPZ4X' === $clean['gtm_id'] && 'stape' === $clean['loader']
	&& ap_site_defaults()['stape_query'] === $clean['stape_query'] && true === $clean['tracking'] && false === $clean['cookiebot'] && false === $clean['headers']
	&& array( '/login-splash*' ) === $clean['tracking_exclude'] && ap_site_defaults()['ursa_demo_url'] === $clean['ursa_demo_url'],
	$clean
);
ob_start();
ap_site_render_admin_page();
$page = ob_get_clean();
check( 'settings page renders: host, production no, features, snippet preview, apply buttons', false !== strpos( $page, 'Request host' ) && false !== strpos( $page, 'never production' ) && false !== strpos( $page, 'GTM-WVPZ4X' ) && false !== strpos( $page, 'Apply SEO settings' ) && false !== strpos( $page, 'Apply site identity' ) && false !== strpos( $page, 'frame-ancestors' ), strlen( $page ) );
check( 'no client-facing attribution words in the settings page', ! preg_match( '/\b(claude|anthropic|AI)\b/', wp_strip_all_tags( $page ) ) );

/* ── Deactivate, uninstall ──────────────────────────────────────────── */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'asset-panda-site/asset-panda-site.php' );
$r = http( 'GET', '/', $WWW );
check( 'deactivated: www 200 over http, no GTM, no headers, no redirect, poster back', 200 === $r['code'] && false === strpos( $r['body'], 'googletagmanager' ) && ! isset( $r['headers']['content-security-policy'] ) && false !== strpos( $r['body'], 'ap-ursa-poster' ), $r['code'] );
$r = http( 'GET', '/wp-json/ap-site/v1/status', $WWW );
check( 'deactivated: REST routes gone (404)', 404 === $r['code'], $r['code'] );
check( 'deactivated: options kept until uninstall', false !== get_option( AP_SITE_LAST_APPLY ) );
uninstall_plugin( 'asset-panda-site/asset-panda-site.php' );
wp_cache_delete( 'alloptions', 'options' );
wp_cache_delete( AP_SITE_OPTION, 'options' );
wp_cache_delete( AP_SITE_LAST_APPLY, 'options' );
check( 'uninstall: plugin options removed; identity and Yoast settings stay', false === get_option( AP_SITE_OPTION ) && false === get_option( AP_SITE_LAST_APPLY ) && 'Asset Panda' === get_option( 'blogname' ) && false !== get_option( 'wpseo_titles' ) );
check( 'uninstall: plugin files still present (only uninstall.php ran)', is_readable( WP_PLUGIN_DIR . '/asset-panda-site/asset-panda-site.php' ) );

$fails = count( array_filter( $GLOBALS['results'], static function ( $r ) { return ! $r['ok']; } ) );
file_put_contents( $argv[2], json_encode( $GLOBALS['results'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo "\n" . ( count( $GLOBALS['results'] ) - $fails ) . ' passed, ' . $fails . " failed\n";
exit( $fails ? 1 : 0 );
