<?php
/**
 * REST endpoints (namespace ap-site/v1), administrators only (manage_options).
 *
 *   POST /wp-json/ap-site/v1/apply-seo-settings   { "dry_run": bool, "refresh_llms_txt": bool }
 *   POST /wp-json/ap-site/v1/apply-identity       { "dry_run": bool, "force_icon": bool }
 *   GET  /wp-json/ap-site/v1/status
 *
 * Authenticate with an Application Password (Basic auth) or a logged-in
 * cookie plus the X-WP-Nonce header. Both apply actions are idempotent and
 * report changed / unchanged / failed keys; dry_run writes nothing.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Permission check for every route. */
function ap_site_rest_can() {
	return current_user_can( 'manage_options' );
}

/**
 * Runs a REST callback; an unexpected error becomes a JSON 500, never a white screen.
 *
 * @param callable $callback Work.
 * @return WP_REST_Response|WP_Error
 */
function ap_site_rest_run( $callback ) {
	try {
		$result = call_user_func( $callback );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	} catch ( \Throwable $e ) {
		ap_site_log( 'REST: ' . $e->getMessage() . ' in ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		return new WP_Error( 'ap_site_error', 'The action failed: ' . $e->getMessage(), array( 'status' => 500 ) );
	}
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'ap-site/v1',
			'/apply-seo-settings',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'ap_site_rest_can',
				'args'                => array(
					'dry_run'          => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'refresh_llms_txt' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					return ap_site_rest_run(
						static function () use ( $request ) {
							return ap_site_apply_seo( (bool) $request['dry_run'], (bool) $request['refresh_llms_txt'] );
						}
					);
				},
			)
		);
		register_rest_route(
			'ap-site/v1',
			'/apply-identity',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'ap_site_rest_can',
				'args'                => array(
					'dry_run'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'force_icon' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					return ap_site_rest_run(
						static function () use ( $request ) {
							return ap_site_apply_identity( (bool) $request['dry_run'], (bool) $request['force_icon'] );
						}
					);
				},
			)
		);
		register_rest_route(
			'ap-site/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'ap_site_rest_can',
				'callback'            => static function () {
					return ap_site_rest_run( 'ap_site_status' );
				},
			)
		);
	}
);

/**
 * Where this request stands and what each feature does here. Used by the
 * settings page and GET /status.
 *
 * @return array
 */
function ap_site_status() {
	$env  = ap_site_environment();
	$s    = ap_site_settings();
	$prod = $env['production'];
	$on   = static function ( $key ) use ( $s, $prod ) {
		return array(
			'setting' => (bool) $s[ $key ],
			'active'  => $prod && (bool) $s[ $key ],
		);
	};
	$last = get_option( AP_SITE_LAST_APPLY, array() );
	$last = is_array( $last ) ? $last : array();
	foreach ( $last as $k => $row ) {
		if ( is_array( $row ) ) {
			unset( $last[ $k ]['report'] );
		}
	}

	return array(
		'version'          => AP_SITE_VERSION,
		'host'             => $env['host'],
		'production'       => $prod,
		'reason'           => $env['reason'],
		'production_hosts' => ap_site_production_hosts(),
		'hosts_from'       => defined( 'AP_SITE_PRODUCTION_HOSTS' ) ? 'AP_SITE_PRODUCTION_HOSTS (wp-config.php)' : 'settings',
		'features'         => array(
			'tracking'        => $on( 'tracking' ) + array(
				'loader'  => ap_site_effective_loader( $s ),
				'gtm_id'  => $s['gtm_id'],
				'exclude' => $s['tracking_exclude'],
			),
			'cookiebot'       => $on( 'cookiebot' ) + array(
				'cbid'    => $s['cookiebot_id'],
				'exclude' => $s['cookiebot_exclude'],
			),
			'geotargetly'     => $on( 'geotargetly' ),
			'headers'         => $on( 'headers' ) + array( 'xfo_omit' => $s['xfo_omit'] ),
			'csp_report_only' => array(
				'setting' => (bool) $s['csp_report_only'],
				'active'  => $prod && $s['headers'] && $s['csp_report_only'],
			),
			'https_redirect'  => $on( 'https_redirect' ),
			'ursa_demo'       => $on( 'ursa_demo' ) + array( 'url' => ap_site_ursa_url( $s ) ),
			'sitemap_exclude' => array(
				'setting' => (bool) $s['sitemap_exclude'],
				'active'  => (bool) $s['sitemap_exclude'] && ap_site_yoast_ready(),
				'note'    => 'any host; switched on by apply-seo-settings',
			),
			'user_enumeration' => array(
				'setting' => (bool) $s['user_enumeration'],
				'active'  => ap_site_user_guard_on(),
				'note'    => 'any host; wp/v2/users, ?author=N and oEmbed author fields refused to visitors',
			),
			'no_emoji_scripts' => array(
				'setting' => (bool) $s['no_emoji_scripts'],
				'active'  => ap_site_no_emoji_on(),
				'note'    => 'any host; core emoji detection script, styles and s.w.org prefetch not printed on the front end',
			),
		),
		'yoast'            => ap_site_yoast_ready() ? ap_site_yoast_version() : null,
		'kill_switch'      => defined( 'AP_SITE_DISABLE' ) && AP_SITE_DISABLE,
		'last_apply'       => $last,
	);
}
