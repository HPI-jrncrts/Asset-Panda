<?php
/**
 * Slow request recorder (1.3.7).
 *
 * Joseph, 2026-11-16: a logged-in visit to /solutions/transportation-logistics/warehouse/ "kept loading … took 2
 * mins". WP Engine support suspects a plugin conflict. Every measurement since answers in under 1.4 s, so a slow
 * request has to be caught while it happens. Any request (front end, REST, admin-ajax; not wp-admin screens or
 * WP-Cron) that spends longer than 3 seconds in PHP writes ONE line to the PHP error log (WP Engine → Error logs):
 *
 *   [asset-panda-site] slow 4.21s GET /some/page/ user=1 code=200 | boot 0.12 init 0.05 route 0.02 build 3.50
 *   print 0.31 flush 0.10 end 0.11 | http: www.youtube.com/oembed 3.20s timeout | mem 64M
 *
 * Phases (seconds): boot = PHP start → plugins loaded; init = → init done; route = → template_redirect;
 * build = → wp_head starts (a block theme renders the page's blocks here); print = → wp_footer finished;
 * flush = the page buffer handed to the server (output-buffer rewrites such as the media localizer run here);
 * end = the rest of shutdown. "http:" lists every outside call the request made (host and path, never the query)
 * with its time and result, so a plugin waiting on another server shows by name. A request that never gets slow
 * writes nothing.
 *
 * Threshold: filter ap_site_slow_request_seconds (default 3). Off: define( 'AP_SITE_SLOW_LOG', false ).
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'AP_SITE_SLOW_LOG' ) && ! AP_SITE_SLOW_LOG ) {
	return;
}

$GLOBALS['ap_site_slow'] = array(
	'start' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true ),
	'marks' => array(),
	'open'  => array(),
	'http'  => array(),
);

/**
 * Notes the time a phase ended.
 *
 * @param string $name Phase.
 */
function ap_site_slow_mark( $name ) {
	if ( ! isset( $GLOBALS['ap_site_slow']['marks'][ $name ] ) ) {
		$GLOBALS['ap_site_slow']['marks'][ $name ] = microtime( true );
	}
}

/**
 * Host and path of an address, without the query (keys and tokens stay out of the log).
 *
 * @param string $url Address.
 * @return string
 */
function ap_site_slow_short_url( $url ) {
	$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) $url ) : parse_url( (string) $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$host  = isset( $parts['host'] ) ? $parts['host'] : '?';
	$path  = isset( $parts['path'] ) ? $parts['path'] : '';
	return substr( $host . $path, 0, 90 );
}

foreach (
	array(
		array( 'plugins_loaded', 'boot', PHP_INT_MAX ),
		array( 'init', 'init', PHP_INT_MAX ),
		array( 'template_redirect', 'route', -100000 ),
		array( 'wp_head', 'build', -100000 ),
		array( 'wp_footer', 'print', PHP_INT_MAX ),
		array( 'shutdown', 'done', 0 ),
		array( 'shutdown', 'flush', 2 ), // WordPress flushes every output buffer at shutdown priority 1.
	) as $ap_site_slow_hook
) {
	$ap_site_slow_name = $ap_site_slow_hook[1];
	add_action(
		$ap_site_slow_hook[0],
		static function () use ( $ap_site_slow_name ) {
			ap_site_slow_mark( $ap_site_slow_name );
		},
		$ap_site_slow_hook[2]
	);
}
unset( $ap_site_slow_hook, $ap_site_slow_name );

/* Outside calls made through the WordPress HTTP API: start … finish. */
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false === $pre ) {
			$GLOBALS['ap_site_slow']['open'][] = array( (string) $url, microtime( true ) );
		}
		return $pre;
	},
	PHP_INT_MAX,
	3
);
add_action(
	'http_api_debug',
	static function ( $response, $context, $transport, $args, $url ) {
		$open  = &$GLOBALS['ap_site_slow']['open'];
		$began = null;
		for ( $i = count( $open ) - 1; $i >= 0; $i-- ) {
			if ( $open[ $i ][0] === (string) $url ) {
				$began = $open[ $i ][1];
				array_splice( $open, $i, 1 );
				break;
			}
		}
		if ( null === $began ) {
			return;
		}
		if ( is_wp_error( $response ) ) {
			$result = false !== stripos( $response->get_error_message(), 'timed out' ) ? 'timeout' : 'error';
		} else {
			$result = ! empty( $response['response']['code'] ) ? (string) $response['response']['code'] : 'sent'; // 'sent' = a call that does not wait for the answer.
		}
		$GLOBALS['ap_site_slow']['http'][] = sprintf( '%s %.2fs %s', ap_site_slow_short_url( $url ), microtime( true ) - $began, $result );
	},
	10,
	5
);

/**
 * The log line for this request, or '' when it was quick enough or is not one we record.
 *
 * @param float|null $now Time to measure to (tests pass their own).
 * @return string
 */
function ap_site_slow_line( $now = null ) {
	$s     = $GLOBALS['ap_site_slow'];
	$now   = null === $now ? microtime( true ) : (float) $now;
	$total = $now - $s['start'];
	$limit = (float) apply_filters( 'ap_site_slow_request_seconds', 3.0 );
	if ( $total < $limit ) {
		return '';
	}
	if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() ) {
		return '';
	}
	$parts = array();
	$prev  = $s['start'];
	foreach ( array( 'boot', 'init', 'route', 'build', 'print', 'done', 'flush' ) as $phase ) {
		if ( isset( $s['marks'][ $phase ] ) && $s['marks'][ $phase ] >= $prev ) {
			$parts[] = ( 'done' === $phase ? 'after' : $phase ) . ' ' . number_format( $s['marks'][ $phase ] - $prev, 2 );
			$prev    = $s['marks'][ $phase ];
		}
	}
	$parts[] = 'end ' . number_format( max( 0, $now - $prev ), 2 );
	$uri     = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path    = substr( (string) strtok( $uri, '?' ), 0, 150 );
	$method  = isset( $_SERVER['REQUEST_METHOD'] ) ? preg_replace( '/[^A-Z]/', '', (string) $_SERVER['REQUEST_METHOD'] ) : '?'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$kind    = ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? ' rest' : ( wp_doing_ajax() ? ' ajax' : '' );
	$code    = function_exists( 'http_response_code' ) ? (int) http_response_code() : 0;
	return sprintf(
		'slow %.2fs %s %s%s%s user=%d code=%d | %s | http: %s | mem %dM',
		$total,
		$method,
		'' !== $path ? $path : '/',
		false !== strpos( $uri, '?' ) ? '?…' : '',
		$kind,
		function_exists( 'is_user_logged_in' ) && is_user_logged_in() ? 1 : 0,
		$code,
		implode( ' ', $parts ),
		$s['http'] ? implode( '; ', array_slice( $s['http'], 0, 8 ) ) . ( count( $s['http'] ) > 8 ? '; +' . ( count( $s['http'] ) - 8 ) . ' more' : '' ) : 'none',
		(int) round( memory_get_peak_usage( true ) / 1048576 )
	);
}

add_action(
	'shutdown',
	static function () {
		try {
			$line = ap_site_slow_line();
			if ( '' !== $line ) {
				ap_site_log( $line );
			}
		} catch ( \Throwable $e ) {
			unset( $e ); // Never let the recorder disturb a request.
		}
	},
	PHP_INT_MAX
);
