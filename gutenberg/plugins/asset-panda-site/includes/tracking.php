<?php
/**
 * Google Tag Manager, Cookiebot and (optionally) Geotargetly, as live injects
 * them outside GTM. Production host only.
 *
 * What live does (curl of www.assetpanda.com, 2026-10-16, GTM container v240):
 * - GTM: inline snippet in <head> with data-no-defer/data-no-optimize, and the
 *   <noscript> iframe after <body>. Absent on /login-splash-1/ … /login-splash-5/;
 *   the container itself also excludes /login-splash*, /asset-panda-login*,
 *   /users/sign_in* and the login subdomain.
 * - HubSpot tracking + chat (portal 4155061) is a Custom HTML tag inside GTM
 *   ("hs-script-loader"), so it returns with GTM; nothing to add here.
 * - Cookiebot uc.js (data-blockingmode="auto") right before </body>, on every
 *   page including the login splash pages.
 * - Geotargetly (g792337341.co) inline before </body>, pre-consent. Only used by
 *   the old theme's Core/International nav swap; off by default here.
 *
 * Loader switch: setting "loader" = 'gtm' (googletagmanager.com, what live uses
 * today) or 'stape' (Stape Custom Loader on data.assetpanda.com, same container,
 * signed token in place of the container id). AP_SITE_GTM_LOADER in
 * wp-config.php overrides the setting.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True for an ordinary front-end page view (not admin, feeds, robots, embeds, previews). */
function ap_site_front_context() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( ( function_exists( 'is_feed' ) && is_feed() ) || ( function_exists( 'is_robots' ) && is_robots() ) ) {
		return false;
	}
	if ( ( function_exists( 'is_embed' ) && is_embed() ) || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
		return false;
	}
	if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
		return false;
	}
	return true;
}

/* ── Validation of stored identifiers (output is built from these only) ── */

/** @param string $id Container id. */
function ap_site_valid_gtm_id( $id ) {
	return (bool) preg_match( '/^GTM-[A-Z0-9]{4,12}$/', (string) $id );
}

/** @param string $id Cookiebot domain group id. */
function ap_site_valid_cookiebot_id( $id ) {
	return (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $id );
}

/** @param string $host Host name. */
function ap_site_valid_hostname( $host ) {
	return (bool) preg_match( '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', (string) $host );
}

/**
 * Whether the Stape Custom Loader settings are complete and well formed.
 *
 * @param array $s Settings.
 * @return bool
 */
function ap_site_valid_stape( array $s ) {
	return ap_site_valid_hostname( $s['stape_domain'] )
		&& preg_match( '/^[A-Za-z0-9_-]+\.js$/', (string) $s['stape_loader'] )
		&& preg_match( '/^[A-Za-z0-9_]+=[A-Za-z0-9%._~-]+$/', (string) $s['stape_query'] );
}

/**
 * The loader actually used: 'stape' only when its settings are valid, so a
 * typo falls back to the standard snippet instead of losing all tracking.
 *
 * @param array $s Settings.
 * @return string 'gtm' or 'stape'.
 */
function ap_site_effective_loader( array $s ) {
	return ( 'stape' === $s['loader'] && ap_site_valid_stape( $s ) ) ? 'stape' : 'gtm';
}

/* ── Snippets (also shown on the settings page as a preview) ─────────── */

/**
 * The <head> snippet: Google's standard GTM snippet with live's
 * data-no-defer / data-no-optimize attributes, or the Stape loader variant.
 *
 * @param array|null $s Settings; current settings when null.
 * @return string '' when the container id is invalid.
 */
function ap_site_gtm_head_html( $s = null ) {
	$s = is_array( $s ) ? $s : ap_site_settings();
	if ( ! ap_site_valid_gtm_id( $s['gtm_id'] ) ) {
		return '';
	}
	if ( 'stape' === ap_site_effective_loader( $s ) ) {
		$src = "'https://" . $s['stape_domain'] . '/' . $s['stape_loader'] . "?'+i";
		$arg = $s['stape_query'];
	} else {
		$src = "'https://www.googletagmanager.com/gtm.js?id='+i+dl";
		$arg = $s['gtm_id'];
	}
	return "<!-- Google Tag Manager -->\n"
		. "<script data-no-defer=\"1\" data-no-optimize=\"1\">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
		. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
		. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
		. $src . ";f.parentNode.insertBefore(j,f);\n"
		. "})(window,document,'script','dataLayer','" . $arg . "');</script>\n"
		. "<!-- End Google Tag Manager -->\n";
}

/**
 * The <noscript> iframe for just after <body>.
 *
 * @param array|null $s Settings.
 * @return string
 */
function ap_site_gtm_noscript_html( $s = null ) {
	$s = is_array( $s ) ? $s : ap_site_settings();
	if ( ! ap_site_valid_gtm_id( $s['gtm_id'] ) ) {
		return '';
	}
	$base = 'stape' === ap_site_effective_loader( $s )
		? 'https://' . $s['stape_domain'] . '/ns.html'
		: 'https://www.googletagmanager.com/ns.html';
	return "<!-- Google Tag Manager (noscript) -->\n"
		. '<noscript><iframe data-no-defer="1" data-no-optimize="1" src="' . esc_url( $base . '?id=' . $s['gtm_id'] ) . "\"\n"
		. 'height="0" width="0" class="googletagman" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
		. "<!-- End Google Tag Manager (noscript) -->\n";
}

/**
 * Cookiebot, exactly as live loads it.
 *
 * @param array|null $s Settings.
 * @return string
 */
function ap_site_cookiebot_html( $s = null ) {
	$s = is_array( $s ) ? $s : ap_site_settings();
	if ( ! ap_site_valid_cookiebot_id( $s['cookiebot_id'] ) ) {
		return '';
	}
	return '<script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="' . esc_attr( $s['cookiebot_id'] ) . '" data-blockingmode="auto" type="text/javascript"></script>' . "\n";
}

/**
 * Geotargetly, as live's footer had it (sets sessionStorage.geoloc and the
 * body classes geoloc-core / geoloc-international).
 *
 * @return string
 */
function ap_site_geotargetly_html() {
	return <<<'HTML'
<script>
(function() {
  function applyGeoNav(value) {
    document.body.classList.remove('geoloc-core', 'geoloc-international');
    if (value === 'International') {
      document.body.classList.add('geoloc-international');
    } else {
      document.body.classList.add('geoloc-core');
    }
  }
  applyGeoNav(sessionStorage.getItem('geoloc'));
  if (sessionStorage.getItem('geoloc') == null) {
      (function(g,e,o,t,a,r,ge,tl,y){
      t=g.getElementsByTagName(o)[0];y=g.createElement(e);y.async=true;
      y.src='https://g792337341.co/geolocation?privacy&id=-OKlTAG9y8UWI1nmwATy&refurl='+g.referrer+'&winurl='+encodeURIComponent(window.location)+'&navigatorlang='+(navigator.language || navigator.userLanguage || '');
      t.parentNode.insertBefore(y,t);
      })(document,'script','head');
    window.geotargetly_loaded = function() {
      const coregeos = ["US","CA","GB","NZ","AU"];
      var country_code = geotargetly_country_code();
      var geoValue = coregeos.includes(country_code) ? 'Core' : 'International';
      sessionStorage.setItem('geoloc', geoValue);
      applyGeoNav(geoValue);
    };
  }
})();
</script>

HTML;
}

/* ── When each piece is printed ──────────────────────────────────────── */

/** GTM for this request? */
function ap_site_gtm_active() {
	if ( ! ap_site_feature_on( 'tracking' ) || ! ap_site_front_context() ) {
		return false;
	}
	if ( ap_site_path_matches( ap_site_request_path(), ap_site_setting( 'tracking_exclude' ) ) ) {
		return false;
	}
	return ap_site_valid_gtm_id( ap_site_setting( 'gtm_id' ) );
}

/** Cookiebot for this request? */
function ap_site_cookiebot_active() {
	if ( ! ap_site_feature_on( 'cookiebot' ) || ! ap_site_front_context() ) {
		return false;
	}
	if ( ap_site_path_matches( ap_site_request_path(), ap_site_setting( 'cookiebot_exclude' ) ) ) {
		return false;
	}
	return ap_site_valid_cookiebot_id( ap_site_setting( 'cookiebot_id' ) );
}

/** Geotargetly for this request? Follows the GTM path exclusions. */
function ap_site_geotargetly_active() {
	if ( ! ap_site_feature_on( 'geotargetly' ) || ! ap_site_front_context() ) {
		return false;
	}
	return ! ap_site_path_matches( ap_site_request_path(), ap_site_setting( 'tracking_exclude' ) );
}

add_action(
	'wp_head',
	static function () {
		ap_site_guard(
			static function () {
				if ( ap_site_gtm_active() ) {
					echo ap_site_gtm_head_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from validated settings only.
				}
			}
		);
	},
	3 // Right after the resource hints (priority 2), like live's preconnect-then-snippet order.
);

/** Prints the <noscript> iframe once. */
function ap_site_print_gtm_noscript() {
	ap_site_guard(
		static function () {
			if ( ! empty( $GLOBALS['ap_site_noscript_done'] ) || ! ap_site_gtm_active() ) {
				return;
			}
			$GLOBALS['ap_site_noscript_done'] = true;
			echo ap_site_gtm_noscript_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from validated settings only.
		}
	);
}
add_action( 'wp_body_open', 'ap_site_print_gtm_noscript', 1 );
// Templates that never call wp_body_open() still get it, at the top of the footer.
add_action( 'wp_footer', 'ap_site_print_gtm_noscript', 1 );

add_action(
	'wp_footer',
	static function () {
		ap_site_guard(
			static function () {
				if ( ap_site_cookiebot_active() ) {
					echo ap_site_cookiebot_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from validated settings only.
				}
			}
		);
	},
	1000 // Last, as on live: page scripts above it are not caught by auto-blocking.
);

add_action(
	'wp_footer',
	static function () {
		ap_site_guard(
			static function () {
				if ( ap_site_geotargetly_active() ) {
					echo ap_site_geotargetly_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup.
				}
			}
		);
	},
	1001
);

/*
 * Preconnects, as live sends them: googletagmanager.com (the loader, and the
 * Google tags even with the Stape loader), the Stape domain when it serves
 * the loader, and consent.cookiebot.com; plus the two HubSpot form hosts,
 * which live preconnects and dns-prefetches on every page.
 */
add_filter(
	'wp_resource_hints',
	static function ( $urls, $relation ) {
		return ap_site_guard(
			static function () use ( $urls, $relation ) {
				if ( ! is_array( $urls ) ) {
					return $urls;
				}
				if ( 'dns-prefetch' === $relation && ap_site_gtm_active() ) {
					$urls[] = '//js-na2.hsforms.net';
					$urls[] = '//forms-na2.hsforms.com';
					return $urls;
				}
				if ( 'preconnect' !== $relation ) {
					return $urls;
				}
				if ( ap_site_gtm_active() ) {
					$urls[] = 'https://js-na2.hsforms.net';
					$urls[] = 'https://forms-na2.hsforms.com';
				}
				if ( ap_site_gtm_active() ) {
					$s = ap_site_settings();
					if ( 'stape' === ap_site_effective_loader( $s ) ) {
						$urls[] = 'https://' . $s['stape_domain'];
					}
					$urls[] = 'https://www.googletagmanager.com';
				}
				if ( ap_site_cookiebot_active() ) {
					$urls[] = 'https://consent.cookiebot.com';
				}
				return $urls;
			},
			$urls
		);
	},
	10,
	2
);
