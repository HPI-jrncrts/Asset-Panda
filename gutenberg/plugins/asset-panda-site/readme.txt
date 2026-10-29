=== Asset Panda Site ===
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Site-wide setup for www.assetpanda.com that is not page content.

== What it does ==

On a production host only (default list: www.assetpanda.com), each with its own switch
under Settings → Asset Panda Site:

* Google Tag Manager GTM-WVPZ4X: the <head> snippet (with live's data-no-defer /
  data-no-optimize attributes) and the <noscript> iframe after <body>. Not printed on
  /login-splash*, /asset-panda-login*, /users/sign_in* (the container's own exclusions).
  HubSpot tracking and chat come from a tag inside the container, as on live.
* Cookiebot d8f06714-cf56-4332-a419-df2b99201fc0 (data-blockingmode="auto") as the last
  script before </body>, on every page, as on live.
* Geotargetly: available, off (the 2026 header does not need it).
* Security headers copied from live: CSP frame-ancestors (self, login/stage/prelive/admin
  .assetpanda.com), Permissions-Policy, Referrer-Policy, X-Frame-Options SAMEORIGIN
  (not on /login-splash*), X-Content-Type-Options, X-XSS-Protection. Optional: live's
  CSP report-only header.
* 301 from http:// to https:// for GET/HEAD page requests.
* Homepage hero: frames the live Ursa demo instead of the posters.
* Preconnect hints for googletagmanager.com and consent.cookiebot.com.

Never on assetpanda1dev, *.wpenginepowered.com, *.wpengine.com, localhost, *.local, *.test
or an IP address, even if added to the list. Nothing is printed until the domain moves.

On any host, with its own switch:

* WordPress's emoji detection script, emoji styles and the s.w.org dns-prefetch are not
  printed on the front end (1.2.0). The script's blob Worker stalls the page's main thread
  for ~400 ms in PageSpeed / Lighthouse runs (a 1.4–1.7 s blocking task on the Asset
  Intelligence Platform page). Browsers render emoji from the system font; admin screens
  keep core's handling.

On request (Apply buttons or REST), on any host:

* Yoast SEO as live: Organization "Asset Panda" with live's SVG logo (imported into the
  media library), social profiles, twitter:site @assetpanda, article:publisher, title
  separator and formats, Google and Bing verification, llms.txt on, resource / newsroom /
  resource-types indexable, author archives off, and thank-you, login splash, test and
  placeholder pages kept out of the sitemap.
* Site identity: "Asset Panda", "Asset Management Software & Asset Intelligence Platform",
  timezone UTC-5 (as live), site icon from live's favicon.

== REST ==

All routes need manage_options (Application Password or cookie + X-WP-Nonce).

POST /wp-json/ap-site/v1/apply-seo-settings   {"dry_run":false,"refresh_llms_txt":false}
POST /wp-json/ap-site/v1/apply-identity       {"dry_run":false,"force_icon":false}
GET  /wp-json/ap-site/v1/status

Each apply returns changed / unchanged / failed keys. A repeat run changes nothing.

== Switching the GTM loader ==

Settings → Asset Panda Site → Google Tag Manager → "Stape custom loader", Save, purge the
WP Engine cache. Check the page source: the loader is
https://data.assetpanda.com/xlaymtlkg.js?… and the noscript iframe is
https://data.assetpanda.com/ns.html?id=GTM-WVPZ4X. Then check that the Cookiebot banner
renders and GA4 realtime records. To go back, choose "Standard loader" and purge again.
For an emergency switch without wp-admin: define( 'AP_SITE_GTM_LOADER', 'gtm' ); in
wp-config.php. If the Stape values are invalid the standard loader is used.

== HSTS ==

Not sent by this plugin. Live's comes from a WP Engine Web Rule (install assetpanda1 →
Advanced → Web rules → Header Rules #0, When: All responses), which also covers static
files. If www is served by a different install after go-live, recreate it there, unquoted:
Strict-Transport-Security: max-age=63072000; includeSubDomains; preload

== Kill switches (wp-config.php) ==

define( 'AP_SITE_DISABLE', true );                 // no tracking, headers, redirect or demo frame
define( 'AP_SITE_PRODUCTION_HOSTS', 'www.assetpanda.com' ); // overrides the host list
define( 'AP_SITE_GTM_LOADER', 'gtm' );             // or 'stape'

== Deactivate / uninstall ==

Deactivating removes every hook immediately. Uninstalling deletes ap_site_settings and
ap_site_last_apply only; Yoast settings, site identity and media stay as applied.
