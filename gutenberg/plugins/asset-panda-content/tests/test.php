<?php
/**
 * TEST ONLY — integration test of asset-panda-content on a local WordPress.
 * Needs setup.php first and a `php -S` server on AP_TEST_BASE (tests/run.sh).
 *
 * php test.php <path/to/wp-load.php> <results.json>
 *
 * @package AssetPandaContent
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/plugin.php';
global $wpdb, $wp_rewrite;

$BASE = getenv( 'AP_TEST_BASE' ) ? getenv( 'AP_TEST_BASE' ) : 'http://127.0.0.1:8899';
$HOST = wp_parse_url( home_url(), PHP_URL_HOST );
$HOME = untrailingslashit( home_url() );

$results = array();
function t_ok( $name, $cond, $detail = '' ) {
	global $results;
	$results[] = array( 'test' => $name, 'pass' => (bool) $cond, 'detail' => $cond ? '' : ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) );
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . ( $cond ? '' : '  -- ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) . "\n";
}
function t_rest( $method, $route, $body = null, $query = array() ) {
	$r = new WP_REST_Request( $method, $route );
	if ( $query ) {
		$r->set_query_params( $query );
	}
	if ( null !== $body ) {
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( $body ) );
	}
	$res  = rest_do_request( $r );
	$data = rest_get_server()->response_to_data( $res, false );
	return array( $res->get_status(), $data, $res->get_headers() );
}
function t_http( $path, $headers = array() ) {
	global $BASE, $HOST;
	$ch = curl_init( $BASE . $path );
	$h  = array( 'Host: ' . $HOST, 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36' );
	foreach ( $headers as $k => $v ) {
		$h[] = $k . ': ' . $v;
	}
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HTTPHEADER     => $h,
			CURLOPT_TIMEOUT        => 60,
		)
	);
	$raw    = curl_exec( $ch );
	$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$hsize  = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );
	$head = substr( (string) $raw, 0, $hsize );
	$body = substr( (string) $raw, $hsize );
	$loc  = preg_match( '/^Location:\s*(\S+)/mi', $head, $m ) ? trim( $m[1] ) : '';
	$dump = getenv( 'AP_TEST_DUMP' );
	if ( $dump && is_dir( $dump ) ) {
		file_put_contents( $dump . '/' . trim( preg_replace( '/[^a-z0-9]+/i', '-', $path ), '-' ) . ( $headers ? '-yoast' : '' ) . '.html', $body );
	}
	return array( $status, $body, $loc );
}
function t_route( $path ) {
	$_SERVER['REQUEST_URI'] = $path;
	$_SERVER['PATH_INFO']   = '';
	unset( $_SERVER['QUERY_STRING'] );
	$wp                    = new WP();
	$wp->public_query_vars = $GLOBALS['wp']->public_query_vars;
	$wp->parse_request();
	return $wp->query_vars;
}
function t_has( $html, $needle ) {
	return false !== strpos( $html, $needle );
}
function t_title_of( $html ) {
	return preg_match( '#<title>(.*?)</title>#s', $html, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' ) : null;
}
function t_meta_desc( $html ) {
	return preg_match( '#<meta name="description" content="([^"]*)"#', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : null;
}
function t_canonicals( $html ) {
	preg_match_all( '#<link rel=["\']canonical["\'] href=["\']([^"\']+)["\']#', $html, $m );
	return $m[1];
}
function t_robots( $html ) {
	return preg_match( "#<meta name=['\"]robots['\"] content=['\"]([^'\"]*)['\"]#", $html, $m ) ? $m[1] : null;
}
function t_body_class( $html ) {
	return preg_match( '#<body[^>]*class="([^"]*)"#', $html, $m ) ? $m[1] : '';
}

$admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
if ( ! $admin ) {
	fwrite( STDERR, "no administrator\n" );
	exit( 2 );
}
wp_set_current_user( $admin[0]->ID );

/* ── A. Activation ──────────────────────────────────────────────────── */
t_ok( 'A1 plugin active', is_plugin_active( 'asset-panda-content/asset-panda-content.php' ) || in_array( 'asset-panda-content/asset-panda-content.php', (array) get_option( 'active_plugins' ), true ) );
t_ok( 'A2 no activation error', ! get_option( 'ap_content_last_error' ), (string) get_option( 'ap_content_last_error' ) );
t_ok( 'A3 post types', post_type_exists( 'resource' ) && post_type_exists( 'newsroom' ) && taxonomy_exists( 'resource-types' ) );
$terms = get_terms(
	array(
		'taxonomy'   => 'resource-types',
		'hide_empty' => false,
	)
);
$slugs = wp_list_pluck( $terms, 'name', 'slug' );
ksort( $slugs );
t_ok(
	'A4 six resource types with live names',
	array(
		'blog'            => 'Blog',
		'case-studies'    => 'Case Study',
		'compare'         => 'Compare Asset Panda',
		'success-stories' => 'Success Story',
		'webinars'        => 'Webinar',
		'white-papers'    => 'White Paper',
	) === $slugs,
	$slugs
);
list( $s, $types ) = t_rest( 'GET', '/wp/v2/types', null, array( 'context' => 'edit' ) );
t_ok( 'A5 REST types expose resource + newsroom', 200 === $s && isset( $types['resource'], $types['newsroom'] ) && 'resource' === $types['resource']['rest_base'] && 'newsroom' === $types['newsroom']['rest_base'], array( $s, array_keys( (array) $types ) ) );
list( $s, $tx ) = t_rest( 'GET', '/wp/v2/taxonomies/resource-types' );
t_ok( 'A6 REST taxonomy resource-types', 200 === $s && 'resource-types' === $tx['rest_base'], array( $s, $tx ) );
$rules = (array) get_option( 'rewrite_rules' );
t_ok( 'A7 rewrite rules present', isset( $rules['^resource-center/([^/]+)/page/?([0-9]{1,})/?$'], $rules['resource-center/?$'], $rules['pressroom/?$'] ), array_slice( array_keys( $rules ), 0, 12 ) );
list( $s, $contract ) = t_rest( 'GET', '/ap-content/v1/contract' );
t_ok( 'A8 contract endpoint', 200 === $s && '1.1' === $contract['contract_version'] && AP_CONTENT_VERSION === $contract['plugin_version'] && in_array( '_ap_jsonld', $contract['own_meta'], true ) && in_array( '_ap_gate_button_label', $contract['own_meta'], true ) && $contract['design']['snippet6_blocks'] && $contract['design']['snippet8_design_pass'] && $contract['design']['page_article'], $contract );

/* ── B. Create through REST, as the contract says ───────────────────── */
$quote   = '<!-- wp:quote {"className":"is-style-ap-cta-quote"} -->' . "\n" . '<blockquote class="wp-block-quote is-style-ap-cta-quote"><!-- wp:paragraph -->' . "\n" . '<p>It\'s simple, it\'s easy, it works.</p>' . "\n" . '<!-- /wp:paragraph --><cite>Pat Doe, CIO, Example Corp</cite></blockquote>' . "\n" . '<!-- /wp:quote -->';
$content = '<!-- wp:paragraph --><p>The challenge paragraph.</p><!-- /wp:paragraph -->' . "\n"
	. '<!-- wp:heading --><h2 class="wp-block-heading">The Solution</h2><!-- /wp:heading -->' . "\n" . $quote . "\n"
	. '<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Feature</th><th>Asset Panda</th></tr></thead><tbody><tr><td>Mobile</td><td>Yes</td></tr></tbody></table></figure><!-- /wp:table -->';
$src     = 'https://www.assetpanda.com/resource-center/success-stories/ap-test-ss-alpha/';
$img     = 'https://www.assetpanda.com/wp-content/uploads/2017/03/asset-tracking-for-system-thumbnail.jpg';
$payload = array(
	'slug'            => 'ap-test-ss-alpha',
	'status'          => 'publish',
	'date'            => '2025-10-21T09:15:00',
	'date_gmt'        => '2025-10-21T14:15:00',
	'ap_modified_gmt' => '2025-11-02T18:40:12',
	'title'           => 'AP Test Success Story Alpha',
	'content'         => $content,
	'excerpt'         => 'Alpha excerpt for the card.',
	'comment_status'  => 'closed',
	'ping_status'     => 'closed',
	'ap_terms'        => array(
		'resource-types' => array( 'success-stories' ),
		'category'       => array(
			array(
				'slug' => 'ap-test-industry',
				'name' => 'AP Test Industry',
			),
		),
	),
	'meta'            => array(
		'_ap_source_url'                     => $src,
		'_ap_source_id'                      => 990001,
		'_ap_import_hash'                    => 'hash-alpha-1',
		'_ap_featured_image_url'             => $img,
		'_ap_featured_image_alt'             => 'Alpha image',
		'_yoast_wpseo_title'                 => 'Alpha SEO Title | AP Test',
		'_yoast_wpseo_metadesc'              => 'Alpha meta description from live.',
		'_yoast_wpseo_canonical'             => '',
		'_yoast_wpseo_meta-robots-noindex'   => '0',
		'_yoast_wpseo_meta-robots-nofollow'  => '0',
		'_yoast_wpseo_opengraph-image'       => $img,
	),
);
list( $s, $a ) = t_rest( 'POST', '/wp/v2/resource', $payload );
t_ok( 'B1 create resource 201', 201 === $s, array( $s, $a ) );
$A_ID = isset( $a['id'] ) ? (int) $a['id'] : 0;
t_ok( 'B2 permalink = live pattern', $HOME . '/resource-center/success-stories/ap-test-ss-alpha/' === ( isset( $a['link'] ) ? $a['link'] : '' ), isset( $a['link'] ) ? $a['link'] : $a );
list( $s, $ga ) = t_rest( 'GET', '/wp/v2/resource/' . $A_ID, null, array( 'context' => 'edit' ) );
$meta_ok = 200 === $s;
$diff    = array();
foreach ( $payload['meta'] as $k => $v ) {
	$got = isset( $ga['meta'][ $k ] ) ? $ga['meta'][ $k ] : '(missing)';
	if ( (string) $got !== (string) $v ) {
		$meta_ok = false;
		$diff[ $k ] = array( $v, $got );
	}
}
t_ok( 'B3 meta round-trips', $meta_ok, $diff );
t_ok( 'B4 modified_gmt = ap_modified_gmt', '2025-11-02T18:40:12' === $ga['modified_gmt'] && '2025-11-02T18:40:12' === $ga['ap_modified_gmt'], array( $ga['modified_gmt'], $ga['ap_modified_gmt'] ) );
t_ok( 'B5 date / date_gmt kept', '2025-10-21T09:15:00' === $ga['date'] && '2025-10-21T14:15:00' === $ga['date_gmt'], array( $ga['date'], $ga['date_gmt'] ) );
t_ok( 'B6 ap_terms read back', array( 'success-stories' ) === $ga['ap_terms']['resource-types'] && array( 'ap-test-industry' ) === $ga['ap_terms']['category'], $ga['ap_terms'] );
$ss = get_term_by( 'slug', 'success-stories', 'resource-types' );
t_ok( 'B7 Yoast primary term set', (string) $ss->term_id === (string) get_post_meta( $A_ID, '_yoast_wpseo_primary_resource-types', true ) );
t_ok( 'B8 content stored as sent', $content === get_post_field( 'post_content', $A_ID ) );

$mk = function ( $slug, $types, $extra = array() ) use ( $content ) {
	return array_merge(
		array(
			'slug'     => $slug,
			'status'   => 'publish',
			'title'    => 'AP Test ' . $slug,
			'content'  => '<!-- wp:paragraph --><p>Body of ' . $slug . '.</p><!-- /wp:paragraph -->',
			'excerpt'  => 'Excerpt ' . $slug,
			'ap_terms' => array( 'resource-types' => $types ),
			'meta'     => array( '_ap_source_url' => 'https://www.assetpanda.com/resource-center/' . $types[0] . '/' . $slug . '/' ),
		),
		$extra
	);
};
list( $s, $b ) = t_rest( 'POST', '/wp/v2/resource', $mk( 'ap-test-ss-beta', array( 'success-stories' ) ) );
$B_ID          = (int) $b['id'];
list( $s, $c ) = t_rest( 'POST', '/wp/v2/resource', $mk( 'ap-test-blog-gamma', array( 'blog' ) ) );
$C_ID          = (int) $c['id'];
list( $s, $d ) = t_rest( 'POST', '/wp/v2/resource', $mk( 'ap-test-compare-delta', array( 'compare', 'blog' ) ) );
$D_ID          = (int) $d['id'];
t_ok( 'B9 two types: URL uses the FIRST', $HOME . '/resource-center/compare/ap-test-compare-delta/' === $d['link'], $d['link'] );
$wp_payload     = $mk(
	'ap-test-wp-epsilon',
	array( 'white-papers' ),
	array(
		'meta' => array(
			'_ap_source_url'     => 'https://www.assetpanda.com/resource-center/white-papers/ap-test-wp-epsilon/',
			'_ap_gate_form_id'   => 'e4942541-8043-417b-a41c-a78b29a4884d',
			'_ap_gate_portal_id' => '4155061',
			'_ap_gate_region'    => 'na2',
			'_ap_gate_heading'   => 'Get The White Paper',
		),
	)
);
list( $s, $e ) = t_rest( 'POST', '/wp/v2/resource', $wp_payload );
$E_ID          = (int) $e['id'];
t_ok( 'B10 white paper created', 201 === $s && $HOME . '/resource-center/white-papers/ap-test-wp-epsilon/' === $e['link'], array( $s, $e['link'] ?? $e ) );

$news = array(
	'slug'            => 'ap-test-press-one',
	'status'          => 'publish',
	'date'            => '2016-08-10T08:00:00',
	'date_gmt'        => '2016-08-10T13:00:00',
	'ap_modified_gmt' => '2020-01-01T00:00:00',
	'title'           => 'AP Test Press One',
	'content'         => '<!-- wp:paragraph --><p>Press body.</p><!-- /wp:paragraph -->',
	'excerpt'         => 'Press excerpt',
	'meta'            => array(
		'_ap_source_url'        => 'https://www.assetpanda.com/pressroom/ap-test-press-one/',
		'_yoast_wpseo_title'    => 'Press One SEO Title',
		'_yoast_wpseo_metadesc' => 'Press one description.',
	),
);
list( $s, $n ) = t_rest( 'POST', '/wp/v2/newsroom', $news );
$N_ID          = (int) $n['id'];
t_ok( 'B11 create newsroom 201 + /pressroom/<slug>/', 201 === $s && $HOME . '/pressroom/ap-test-press-one/' === $n['link'], array( $s, $n['link'] ?? $n ) );
list( $s, $n2 ) = t_rest( 'POST', '/wp/v2/newsroom', array_merge( $news, array( 'slug' => 'ap-test-press-two', 'title' => 'AP Test Press Two', 'meta' => array( '_ap_source_url' => 'https://www.assetpanda.com/pressroom/ap-test-press-two/' ) ) ) );
$N2_ID          = (int) $n2['id'];
list( $s, $gn ) = t_rest( 'GET', '/wp/v2/newsroom/' . $N_ID, null, array( 'context' => 'edit' ) );
t_ok( 'B12 newsroom meta + modified round-trip', 'Press One SEO Title' === $gn['meta']['_yoast_wpseo_title'] && '2020-01-01T00:00:00' === $gn['modified_gmt'], array( $gn['meta']['_yoast_wpseo_title'] ?? null, $gn['modified_gmt'] ?? null ) );

$legacy_payload = array(
	'slug'            => 'ap-test-legacy',
	'status'          => 'publish',
	'parent'          => 0,
	'template'        => 'page-article',
	'title'           => 'AP Test Legacy Page',
	'content'         => '<!-- wp:paragraph --><p>Legacy body.</p><!-- /wp:paragraph -->' . $quote,
	'ap_modified_gmt' => '2024-01-10T12:00:00',
	'meta'            => array(
		'_ap_source_url'        => 'https://www.assetpanda.com/ap-test-legacy/',
		'_ap_source_id'         => 990100,
		'_yoast_wpseo_title'    => 'Legacy SEO Title',
		'_yoast_wpseo_metadesc' => 'Legacy description.',
	),
);
list( $s, $p ) = t_rest( 'POST', '/wp/v2/pages', $legacy_payload );
$P_ID          = (int) ( $p['id'] ?? 0 );
t_ok( 'B13 legacy page with template page-article', 201 === $s && 'page-article' === ( $p['template'] ?? '' ) && 'page-article' === get_page_template_slug( $P_ID ), array( $s, $p['template'] ?? $p ) );
t_ok( 'B14 legacy page URL', $HOME . '/ap-test-legacy/' === ( $p['link'] ?? '' ), $p['link'] ?? '' );
list( $s, $p2 ) = t_rest( 'POST', '/wp/v2/pages', array_merge( $legacy_payload, array( 'slug' => 'child', 'parent' => $P_ID, 'title' => 'AP Test Child', 'meta' => array( '_ap_source_url' => 'https://www.assetpanda.com/ap-test-legacy/child/' ) ) ) );
$P2_ID          = (int) ( $p2['id'] ?? 0 );
t_ok( 'B15 child legacy page URL', $HOME . '/ap-test-legacy/child/' === ( $p2['link'] ?? '' ), $p2['link'] ?? $p2 );

/* Duplicates and validation */
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='resource'" );
list( $s, $dup ) = t_rest( 'POST', '/wp/v2/resource', array_merge( $payload, array( 'meta' => array( '_ap_source_url' => 'https://www.assetpanda.com/resource-center/success-stories/other/' ) ) ) );
t_ok( 'B16 same slug → 409 with existing_id', 409 === $s && 'ap_content_duplicate' === $dup['code'] && $A_ID === (int) $dup['data']['existing_id'], array( $s, $dup ) );
list( $s, $dup2 ) = t_rest( 'POST', '/wp/v2/resource', array_merge( $payload, array( 'slug' => 'ap-test-ss-alpha-x' ) ) );
t_ok( 'B17 same _ap_source_url → 409', 409 === $s && $A_ID === (int) $dup2['data']['existing_id'], array( $s, $dup2 ) );
list( $s, $bad ) = t_rest( 'POST', '/wp/v2/resource', $mk( 'ap-test-bad-term', array( 'no-such-type' ) ) );
t_ok( 'B18 unknown type → 400 ap_content_bad_term', 400 === $s && 'ap_content_bad_term' === $bad['code'], array( $s, $bad ) );
list( $s, $bad2 ) = t_rest( 'POST', '/wp/v2/resource', $mk( 'ap-test-bad-date', array( 'blog' ), array( 'ap_modified_gmt' => '02/11/2025' ) ) );
t_ok( 'B19 bad date → 400 ap_content_bad_date', 400 === $s && 'ap_content_bad_date' === $bad2['code'], array( $s, $bad2 ) );
$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='resource'" );
t_ok( 'B20 refused creates wrote nothing', $before === $after, array( $before, $after ) );
list( $s, $dupp ) = t_rest( 'POST', '/wp/v2/pages', array_merge( $legacy_payload, array( 'meta' => array( '_ap_source_url' => 'https://www.assetpanda.com/ap-test-legacy-2/' ) ) ) );
t_ok( 'B21 page with same path → 409', 409 === $s && $P_ID === (int) $dupp['data']['existing_id'], array( $s, $dupp ) );

/* Update in place (the upsert path) */
list( $s, $upd ) = t_rest( 'POST', '/wp/v2/resource/' . $A_ID, array( 'title' => 'AP Test Success Story Alpha', 'excerpt' => 'Alpha excerpt for the card.', 'ap_modified_gmt' => '2025-11-03T10:00:00', 'meta' => array( '_ap_source_url' => $src, '_ap_import_hash' => 'hash-alpha-2' ) ) );
t_ok( 'B22 update in place keeps id + URL, new modified', 200 === $s && $A_ID === (int) $upd['id'] && '2025-11-03T10:00:00' === $upd['modified_gmt'] && $HOME . '/resource-center/success-stories/ap-test-ss-alpha/' === $upd['link'], array( $s, $upd['modified_gmt'] ?? $upd ) );

/* Lookup / items */
list( $s, $l1 ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'source_url' => $src ) );
t_ok( 'B23 lookup by source_url', 200 === $s && $l1['found'] && $A_ID === $l1['id'] && 'publish' === $l1['status'], $l1 );
list( $s, $l1b ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'source_url' => 'http://www.assetpanda.com/resource-center/success-stories/ap-test-ss-alpha' ) );
t_ok( 'B24 lookup tolerates http + no slash', 200 === $s && $l1b['found'] && $A_ID === $l1b['id'], $l1b );
list( $s, $l2 ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'type' => 'newsroom', 'slug' => 'ap-test-press-one' ) );
t_ok( 'B25 lookup by type+slug', $l2['found'] && $N_ID === $l2['id'], $l2 );
list( $s, $l3 ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'type' => 'page', 'path' => 'ap-test-legacy/child' ) );
t_ok( 'B26 lookup page by path', $l3['found'] && $P2_ID === $l3['id'] && 'page-article' === $l3['template'], $l3 );
list( $s, $l4 ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'type' => 'resource', 'slug' => 'nope-nope' ) );
t_ok( 'B27 lookup miss = found:false', 200 === $s && false === $l4['found'], $l4 );
list( $s, $items, $hdr ) = t_rest( 'GET', '/ap-content/v1/items', null, array( 'type' => 'resource', 'per_page' => 500 ) );
$ids = wp_list_pluck( (array) $items, 'id' );
t_ok( 'B28 items lists all test resources', 200 === $s && ! array_diff( array( $A_ID, $B_ID, $C_ID, $D_ID, $E_ID ), $ids ) && 'hash-alpha-2' === $items[ array_search( $A_ID, $ids, true ) ]['import_hash'], array( $s, $ids ) );

/* Archive SEO */
list( $s, $as ) = t_rest( 'POST', '/ap-content/v1/archive-seo', array( 'target' => 'resource-types:success-stories', 'title' => 'Success Stories - AP Test', 'description' => 'SS listing description.' ) );
t_ok( 'B29 archive-seo term', 200 === $s && 'Success Stories - AP Test' === $as['title'], array( $s, $as ) );
list( $s, $as2 ) = t_rest( 'POST', '/ap-content/v1/archive-seo', array( 'target' => 'newsroom', 'title' => 'Pressroom - AP Test', 'description' => 'Press listing description.', 'heading' => 'Press Room' ) );
t_ok( 'B30 archive-seo newsroom', 200 === $s && 'Pressroom - AP Test' === $as2['title'] && 'Press Room' === $as2['heading'], array( $s, $as2 ) );
list( $s, $as3 ) = t_rest( 'POST', '/ap-content/v1/archive-seo', array( 'target' => 'resource-types:nope' ) );
t_ok( 'B31 archive-seo bad target → 400', 400 === $s, array( $s, $as3 ) );

/* Yoast keys on core post + page (kit pages): meta-only writes */
$kit_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AP Test Kit Page',
		'post_name'    => 'ap-test-kit-page',
		'post_content' => '<!-- wp:assetpanda/hero {"title":"x"} /-->',
	)
);
$kit_before = get_post_field( 'post_content', $kit_id );
list( $s, $km ) = t_rest( 'POST', '/wp/v2/pages/' . $kit_id, array( 'meta' => array( '_yoast_wpseo_title' => 'Kit SEO Title', '_yoast_wpseo_metadesc' => 'Kit description.', '_yoast_wpseo_canonical' => 'https://www.assetpanda.com/products/x/', '_yoast_wpseo_meta-robots-noindex' => '1', '_yoast_wpseo_meta-robots-nofollow' => '1', '_yoast_wpseo_opengraph-image' => $img ) ) );
t_ok( 'B32 kit page meta-only write', 200 === $s && 'Kit SEO Title' === $km['meta']['_yoast_wpseo_title'] && '1' === $km['meta']['_yoast_wpseo_meta-robots-noindex'] && $kit_before === get_post_field( 'post_content', $kit_id ), array( $s, $km['meta'] ?? $km ) );
list( $s, $lk ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'type' => 'page', 'path' => 'ap-test-kit-page' ) );
t_ok( 'B33 lookup flags kit pages', true === $lk['kit'], $lk );
$core_id = wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'AP Test Core Post',
		'post_name'    => 'ap-test-core-post',
		'post_content' => '<!-- wp:paragraph --><p>Core.</p><!-- /wp:paragraph -->',
	)
);
list( $s, $cp ) = t_rest( 'POST', '/wp/v2/posts/' . $core_id, array( 'meta' => array( '_yoast_wpseo_title' => 'Core SEO Title' ) ) );
t_ok( 'B34 core post Yoast meta writable', 200 === $s && 'Core SEO Title' === $cp['meta']['_yoast_wpseo_title'], array( $s, $cp['meta'] ?? $cp ) );
$schema_keys = array_keys( (array) get_registered_meta_keys( 'post', 'page' ) );
t_ok( 'B35 Yoast keys registered on page (title, metadesc, canonical, noindex, nofollow, og-image)', ! array_diff( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow', '_yoast_wpseo_opengraph-image' ), $schema_keys ) );

/* Yoast keeps sanitizing its own keys: emulate Yoast's register_meta( 'post', key, sanitize ) */
register_meta(
	'post',
	'_yoast_wpseo_title',
	array(
		'sanitize_callback' => function ( $v ) {
			return $v . '|yoast-sanitized';
		},
	)
);
t_ok( 'B36 Yoast sanitize still runs for resource/page/post', 'x|yoast-sanitized' === sanitize_meta( '_yoast_wpseo_title', 'x', 'post', 'resource' ) && 'x|yoast-sanitized' === sanitize_meta( '_yoast_wpseo_title', 'x', 'post', 'page' ) && 'x|yoast-sanitized' === sanitize_meta( '_yoast_wpseo_title', 'x', 'post', 'post' ) );
remove_all_filters( 'sanitize_post_meta__yoast_wpseo_title' );
t_ok( 'B37 no default injected for absent Yoast keys', '' === get_post_meta( $B_ID, '_yoast_wpseo_metadesc', true ) && array() === get_post_meta( $B_ID, '_yoast_wpseo_metadesc' ) );

/* ── 1.1: form panel on pages ────────────────────────────────────────── */
$PARTNER_FORM = '024ab0e4-66a2-49d1-a857-6357604454fb';
$partners     = array(
	'slug'     => 'ap-test-partners',
	'status'   => 'publish',
	'template' => 'page-article',
	'title'    => 'AP Test Partners',
	'content'  => '<!-- wp:paragraph --><p>Intro.</p><!-- /wp:paragraph -->' . "\n"
		. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://info.assetpanda.com/partner-deal-registration">Deal Registration</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . "\n"
		. '<!-- wp:paragraph --><p>More text.</p><!-- /wp:paragraph -->',
	'meta'     => array(
		'_ap_source_url'        => 'https://www.assetpanda.com/ap-test-partners/',
		'_ap_gate_form_id'      => $PARTNER_FORM,
		'_ap_gate_portal_id'    => '4155061',
		'_ap_gate_region'       => 'na2',
		'_ap_gate_heading'      => 'Partner Registration',
		'_ap_gate_text'         => 'Apply to join the reseller program.',
		'_ap_gate_button_label' => 'Partner Registration',
	),
);
list( $s, $pp ) = t_rest( 'POST', '/wp/v2/pages', $partners );
$PP_ID          = (int) ( $pp['id'] ?? 0 );
$gate_ok        = 201 === $s;
foreach ( $partners['meta'] as $k => $v ) {
	$gate_ok = $gate_ok && isset( $pp['meta'][ $k ] ) && (string) $v === (string) $pp['meta'][ $k ];
}
t_ok( 'F1 gate meta (+ button label) writable on a page', $gate_ok, array( $s, $pp['meta'] ?? $pp ) );
// A kit page (no page-article template) with a form and no Buttons block.
list( $s, $kf ) = t_rest( 'POST', '/wp/v2/pages/' . $kit_id, array( 'meta' => array( '_ap_gate_form_id' => $PARTNER_FORM, '_ap_gate_button_label' => 'Apply Now' ) ) );
t_ok( 'F2 gate meta on an existing kit page (meta-only)', 200 === $s && $PARTNER_FORM === $kf['meta']['_ap_gate_form_id'] && $kit_before === get_post_field( 'post_content', $kit_id ), array( $s, $kf['meta'] ?? $kf ) );
t_ok( 'F3 button label registered on resource too', in_array( '_ap_gate_button_label', array_keys( (array) get_registered_meta_keys( 'post', 'resource' ) ), true ) );

/* ── 1.1: JSON-LD meta ───────────────────────────────────────────────── */
$ld_objects = array(
	array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array(
			array(
				'@type'          => 'Question',
				'name'           => 'Does it escape </script> and <!-- in strings?',
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => 'Yes: </script><script>alert(1)</script> stays text. Unicode: café ✓',
				),
			),
		),
	),
	array(
		'@context' => 'https://schema.org',
		'@graph'   => array(
			array(
				'@type' => 'WebPage',
				'@id'   => 'https://www.assetpanda.com/ap-test-legacy/#webpage',
				'about' => new stdClass(),
			),
		),
	),
);
$ld_string  = wp_json_encode( $ld_objects, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
list( $s, $lp ) = t_rest( 'POST', '/wp/v2/pages/' . $P_ID, array( 'meta' => array( '_ap_jsonld' => $ld_string ) ) );
t_ok( 'J1 _ap_jsonld writable on a page and round-trips byte for byte', 200 === $s && $ld_string === $lp['meta']['_ap_jsonld'], array( $s, $lp['meta']['_ap_jsonld'] ?? $lp ) );
$J_RES = $ld_string;
foreach ( array( array( 'resource', $C_ID ), array( 'newsroom', $N_ID ), array( 'posts', $core_id ) ) as $pair ) {
	list( $s, $lr ) = t_rest( 'POST', '/wp/v2/' . $pair[0] . '/' . $pair[1], array( 'meta' => array( '_ap_jsonld' => '[{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[]}]' ) ) );
	t_ok( 'J2 _ap_jsonld writable on ' . $pair[0], 200 === $s && '[{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[]}]' === $lr['meta']['_ap_jsonld'], array( $s, $lr['meta']['_ap_jsonld'] ?? $lr ) );
}
list( $s, $bad3 ) = t_rest( 'POST', '/wp/v2/pages/' . $P_ID, array( 'meta' => array( '_ap_jsonld' => '[{"@type":"FAQPage",}]' ) ) );
t_ok( 'J3 invalid JSON → 400 ap_content_bad_jsonld, stored value kept', 400 === $s && 'ap_content_bad_jsonld' === $bad3['code'] && $ld_string === get_post_meta( $P_ID, '_ap_jsonld', true ), array( $s, $bad3 ) );
list( $s, $bad4 ) = t_rest( 'POST', '/wp/v2/pages/' . $P_ID, array( 'meta' => array( '_ap_jsonld' => '["just a string"]' ) ) );
t_ok( 'J4 array of non-objects → 400', 400 === $s && 'ap_content_bad_jsonld' === $bad4['code'], array( $s, $bad4 ) );
list( $s, $bad5 ) = t_rest( 'POST', '/wp/v2/posts/' . $core_id, array( 'meta' => array( '_ap_jsonld' => '{oops' ) ) );
t_ok( 'J5 invalid JSON refused on core posts too', 400 === $s && 'ap_content_bad_jsonld' === $bad5['code'], array( $s, $bad5 ) );
// A broken value stored behind REST's back must never print.
update_post_meta( $P2_ID, '_ap_jsonld', '[{"@type":"Thing"' );

/* ── 1.1.2: an attachment that owns a slug is never taken for the page ─ */
$att = wp_insert_attachment(
	array(
		'post_title'     => 'ap-test-attach',
		'post_name'      => 'ap-test-attach',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image/png',
	),
	false
);
list( $s, $la ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'type' => 'page', 'path' => 'ap-test-attach' ) );
t_ok( 'K1 page lookup ignores an attachment with that slug', 200 === $s && false === $la['found'], $la );
list( $s, $pa ) = t_rest(
	'POST',
	'/wp/v2/pages',
	array(
		'slug'     => 'ap-test-attach',
		'status'   => 'publish',
		'template' => 'page-article',
		'title'    => 'AP Test Attach Page',
		'content'  => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->',
		'meta'     => array( '_ap_source_url' => 'https://www.assetpanda.com/ap-test-attach/' ),
	)
);
t_ok( 'K2 page create is not refused (409) because of the attachment', 201 === $s, array( $s, $pa['code'] ?? '' ) );
t_ok( 'K3 (WordPress) the page gets -2 while the attachment keeps the slug', isset( $pa['slug'] ) && 'ap-test-attach-2' === $pa['slug'], $pa['slug'] ?? $pa );

/* Permissions */
wp_set_current_user( 0 );
list( $s ) = t_rest( 'POST', '/wp/v2/pages/' . $kit_id, array( 'meta' => array( '_yoast_wpseo_title' => 'hacked' ) ) );
t_ok( 'B38 anonymous meta write refused', in_array( $s, array( 401, 403 ), true ) && 'Kit SEO Title' === get_post_meta( $kit_id, '_yoast_wpseo_title', true ), $s );
list( $s ) = t_rest( 'GET', '/ap-content/v1/lookup', null, array( 'source_url' => $src ) );
t_ok( 'B39 anonymous lookup refused', 401 === $s, $s );
list( $s ) = t_rest( 'POST', '/ap-content/v1/archive-seo', array( 'target' => 'newsroom', 'title' => 'x' ) );
t_ok( 'B40 anonymous archive-seo refused', 401 === $s, $s );
list( $s, $pub ) = t_rest( 'GET', '/wp/v2/resource/' . $A_ID );
t_ok( 'B41 public REST read of a published resource', 200 === $s && $A_ID === $pub['id'], $s );
wp_set_current_user( $admin[0]->ID );

/* ── C. Routing (WP::parse_request) ─────────────────────────────────── */
$r = t_route( '/resource-center/' );
t_ok( 'C1 /resource-center/ → resource archive', 'resource' === ( $r['post_type'] ?? '' ), $r );
$r = t_route( '/resource-center/page/2/' );
t_ok( 'C2 /resource-center/page/2/', 'resource' === ( $r['post_type'] ?? '' ) && '2' === (string) ( $r['paged'] ?? '' ), $r );
$r = t_route( '/resource-center/success-stories/' );
t_ok( 'C3 type listing', 'success-stories' === ( $r['resource-types'] ?? '' ) && empty( $r['resource'] ), $r );
$r = t_route( '/resource-center/success-stories/page/2/' );
t_ok( 'C4 type listing page 2', 'success-stories' === ( $r['resource-types'] ?? '' ) && '2' === (string) ( $r['paged'] ?? '' ) && empty( $r['resource'] ), $r );
$r = t_route( '/resource-center/success-stories/ap-test-ss-alpha/' );
t_ok( 'C5 single resource', 'ap-test-ss-alpha' === ( $r['resource'] ?? '' ) && 'success-stories' === ( $r['ap_rtype'] ?? '' ) && ! isset( $r['resource-types'] ), $r );
$r = t_route( '/resource-center/blog/ap-test-core-post/' );
t_ok( 'C6 core post at /resource-center/blog/<slug>/ still resolves', 'ap-test-core-post' === ( $r['name'] ?? '' ) && empty( $r['resource'] ), $r );
$r = t_route( '/pressroom/' );
t_ok( 'C7 /pressroom/', 'newsroom' === ( $r['post_type'] ?? '' ), $r );
$r = t_route( '/pressroom/page/2/' );
t_ok( 'C8 /pressroom/page/2/', 'newsroom' === ( $r['post_type'] ?? '' ) && '2' === (string) ( $r['paged'] ?? '' ), $r );
$r = t_route( '/pressroom/ap-test-press-one/' );
t_ok( 'C9 press single', 'ap-test-press-one' === ( $r['newsroom'] ?? '' ), $r );

/* ── D. Front end over HTTP (php -S) ────────────────────────────────── */
list( $st, $h ) = t_http( '/resource-center/success-stories/ap-test-ss-alpha/' );
$bc = t_body_class( $h );
t_ok( 'D1 single resource 200 + single-resource', 200 === $st && t_has( $bc, 'single-resource' ), array( $st, $bc ) );
t_ok( 'D2 <title> from _yoast_wpseo_title (Yoast inactive)', 'Alpha SEO Title | AP Test' === t_title_of( $h ), t_title_of( $h ) );
t_ok( 'D3 meta description', 'Alpha meta description from live.' === t_meta_desc( $h ), t_meta_desc( $h ) );
t_ok( 'D4 exactly one canonical = permalink', array( $HOME . '/resource-center/success-stories/ap-test-ss-alpha/' ) === t_canonicals( $h ), t_canonicals( $h ) );
t_ok( 'D5 og:image', t_has( $h, '<meta property="og:image" content="' . $img . '"' ) );
t_ok( 'D6 robots index', null === t_robots( $h ) || false === strpos( (string) t_robots( $h ), 'noindex' ), t_robots( $h ) );
t_ok( 'D7 crumbs: Resources → Success Stories → title', t_has( $h, 'href="' . $HOME . '/resource-center/">Resources</a>' ) && t_has( $h, 'href="' . $HOME . '/resource-center/success-stories/">Success Stories</a>' ) && t_has( $h, '<span class="ap26-crumbs__current">AP Test Success Story Alpha</span>' ) );
t_ok( 'D8 eyebrow = Resource Type + one H1', t_has( $h, '<p class="ap26-eyebrow">Success Story</p><h1 class="ap26-post-header__title">AP Test Success Story Alpha</h1>' ) && 1 === substr_count( $h, '<h1' ) );
t_ok( 'D9 external featured image in hero', t_has( $h, '<figure class="ap26-post-header__media"><img src="' . $img . '"' ) );
t_ok( 'D10 sticky bar with type', t_has( $h, '<span class="ap26-sticky__eyebrow">Success Story</span>' ) );
t_ok( 'D11 related = same type only (beta in, gamma/delta out)', t_has( $h, 'ap26-related' ) && t_has( $h, 'ap-test-ss-beta/' ) && ! t_has( $h, 'ap-test-blog-gamma' ) && ! t_has( $h, 'ap-test-compare-delta' ) );
t_ok( 'D12 ap26 stylesheet + cta-quote style loaded', t_has( $h, 'id="ap26-blog-inline-css"' ) && t_has( $h, 'cta-quote.css' ) && t_has( $h, 'ap-content.css' ) );
t_ok( 'D13 CTA-box quote markup', t_has( $h, 'wp-block-quote is-style-ap-cta-quote' ) && t_has( $h, '<cite>Pat Doe, CIO, Example Corp</cite>' ) );
t_ok( 'D14 snippet 8 design pass ran (table styled)', t_has( $h, 'ap26x-table' ) && t_has( $h, 'id="ap-content-ap26x"' ) );
t_ok( 'D15 share bar (snippet block untouched)', t_has( $h, 'ap26-share-footer' ) );
t_ok( 'D16 card label = industry category', t_has( $h, 'AP Test Industry' ) || t_has( $h, 'ap26-card__tag' ) );

list( $st, $h, $loc ) = t_http( '/resource-center/blog/ap-test-ss-alpha/?utm_source=x' );
t_ok( 'D17 wrong type → 301 to canonical (query kept)', 301 === $st && $HOME . '/resource-center/success-stories/ap-test-ss-alpha/?utm_source=x' === $loc, array( $st, $loc ) );
list( $st, $h ) = t_http( '/resource-center/compare/ap-test-compare-delta/' );
t_ok( 'D18 two-type resource at its first type', 200 === $st && t_has( t_body_class( $h ), 'single-resource' ) );

list( $st, $h ) = t_http( '/resource-center/success-stories/' );
t_ok( 'D19 type listing 200 (archive design)', 200 === $st && t_has( t_body_class( $h ), 'tax-resource-types' ) && t_has( $h, '<h1 class="ap26-cat-hero__title">Success Stories</h1>' ), array( $st, t_body_class( $h ) ) );
t_ok( 'D20 listing cards = both success stories only', t_has( $h, 'ap-test-ss-alpha/' ) && t_has( $h, 'ap-test-ss-beta/' ) && ! t_has( $h, 'ap-test-blog-gamma/' ) );
t_ok( 'D21 listing SEO from archive-seo', 'Success Stories - AP Test' === t_title_of( $h ) && 'SS listing description.' === t_meta_desc( $h ) && array( $HOME . '/resource-center/success-stories/' ) === t_canonicals( $h ), array( t_title_of( $h ), t_meta_desc( $h ), t_canonicals( $h ) ) );
t_ok( 'D22 listing crumbs', t_has( $h, 'href="' . $HOME . '/resource-center/">Resources</a>' ) && t_has( $h, '<span class="ap26-crumbs__current">Success Stories</span>' ) );

list( $st, $h ) = t_http( '/resource-center/' );
t_ok( 'D23 /resource-center/ = all resources, Blog List (home) design', 200 === $st && t_has( t_body_class( $h ), 'post-type-archive-resource' ) && t_has( $h, 'ap26-blog-hero' ) && t_has( $h, '<a class="ap26-tab is-active" href="' . $HOME . '/resource-center/">All</a>' ), array( $st, t_body_class( $h ) ) );
t_ok( 'D24 all-resources grid has every type', t_has( $h, 'ap-test-ss-alpha/' ) && t_has( $h, 'ap-test-blog-gamma/' ) && t_has( $h, 'ap-test-wp-epsilon/' ) );

list( $st, $h ) = t_http( '/pressroom/' );
t_ok( 'D25 /pressroom/ listing', 200 === $st && t_has( t_body_class( $h ), 'post-type-archive-newsroom' ) && t_has( $h, '<h1 class="ap26-cat-hero__title">Press Room</h1>' ) && t_has( $h, 'ap-test-press-one/' ), array( $st, t_body_class( $h ) ) );
t_ok( 'D26 /pressroom/ SEO', 'Pressroom - AP Test' === t_title_of( $h ) && 'Press listing description.' === t_meta_desc( $h ), array( t_title_of( $h ), t_meta_desc( $h ) ) );
list( $st, $h ) = t_http( '/pressroom/ap-test-press-one/' );
t_ok( 'D27 press single', 200 === $st && t_has( t_body_class( $h ), 'single-newsroom' ) && t_has( $h, '<p class="ap26-eyebrow">Press Release</p>' ) && t_has( $h, 'href="' . $HOME . '/pressroom/">Press Room</a>' ), array( $st, t_body_class( $h ) ) );
t_ok( 'D28 press SEO + related press', 'Press One SEO Title' === t_title_of( $h ) && 'Press one description.' === t_meta_desc( $h ) && t_has( $h, 'ap-test-press-two/' ) );

list( $st, $h ) = t_http( '/resource-center/white-papers/ap-test-wp-epsilon/' );
t_ok( 'D29 white paper gate panel + HubSpot form', 200 === $st && t_has( $h, 'ap26-cta-mini ap-content-gate' ) && t_has( $h, 'Get The White Paper' ) && t_has( $h, 'js.hsforms.net/forms/embed/v2.js' ) && t_has( $h, 'e4942541-8043-417b-a41c-a78b29a4884d' ) );

list( $st, $h ) = t_http( '/ap-test-legacy/' );
t_ok( 'D30 legacy page renders page-article', 200 === $st && t_has( t_body_class( $h ), 'page-template-page-article' ) && t_has( $h, 'ap-content-page-hero' ) && t_has( $h, '<h1 class="ap26-cat-hero__title">AP Test Legacy Page</h1>' ) && t_has( $h, 'ap26-article' ), array( $st, t_body_class( $h ) ) );
t_ok( 'D31 legacy page SEO + quote style', 'Legacy SEO Title' === t_title_of( $h ) && 'Legacy description.' === t_meta_desc( $h ) && t_has( $h, 'cta-quote.css' ), array( t_title_of( $h ), t_meta_desc( $h ) ) );
list( $st, $h ) = t_http( '/ap-test-legacy/child/' );
t_ok( 'D32 child legacy page crumbs', 200 === $st && t_has( $h, 'href="' . $HOME . '/ap-test-legacy/">AP Test Legacy Page</a>' ) );

list( $st, $h ) = t_http( '/ap-test-kit-page/' );
t_ok( 'D33 kit page: noindex,nofollow + custom canonical suppressed by noindex', 200 === $st && t_has( (string) t_robots( $h ), 'noindex' ) && t_has( (string) t_robots( $h ), 'nofollow' ) && 'Kit SEO Title' === t_title_of( $h ), array( t_robots( $h ), t_title_of( $h ) ) );

list( $st, $h ) = t_http( '/resource-center/blog/ap-test-core-post/' );
t_ok( 'D34 core post at its old URL still 200', 200 === $st && t_has( t_body_class( $h ), 'single-post' ), array( $st, t_body_class( $h ) ) );

list( $st, $h, $loc ) = t_http( '/resource-category/success-stories/' );
t_ok( 'D35 /resource-category/<t>/ → 301 listing', 301 === $st && $HOME . '/resource-center/success-stories/' === $loc, array( $st, $loc ) );
list( $st, $h, $loc ) = t_http( '/resource-types/blog/' );
t_ok( 'D36 /resource-types/<t>/ → 301 listing', 301 === $st && $HOME . '/resource-center/blog/' === $loc, array( $st, $loc ) );
list( $st, $h, $loc ) = t_http( '/resource/' );
t_ok( 'D37 /resource/ → 301 /resource-center/', 301 === $st && $HOME . '/resource-center/' === $loc, array( $st, $loc ) );
list( $st ) = t_http( '/resource-center/no-such-type/' );
t_ok( 'D38 unknown type → 404', 404 === $st, $st );
list( $st ) = t_http( '/resource-center/blog/no-such-post-xyz/' );
t_ok( 'D39 unknown resource → 404', 404 === $st, $st );
list( $st, $h ) = t_http( '/resource-center/compare/' );
t_ok( 'D40 compare listing 200 while no /compare/ page', 200 === $st, $st );
$cmp = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Compare', 'post_name' => 'compare' ) );
list( $st, $h, $loc ) = t_http( '/resource-center/compare/' );
t_ok( 'D41 /resource-center/compare/ → 301 /compare/ once that page exists (as live)', 301 === $st && $HOME . '/compare/' === $loc, array( $st, $loc ) );

/* Yoast stand-down */
list( $st, $h ) = t_http( '/resource-center/success-stories/ap-test-ss-alpha/', array( 'X-AP-Test-Yoast' => '1' ) );
t_ok( 'D42 WPSEO_VERSION defined → fallback stands down', 200 === $st && ! t_has( $h, 'Asset Panda Content SEO' ) && null === t_meta_desc( $h ) && 'Alpha SEO Title | AP Test' !== t_title_of( $h ) && 1 === count( t_canonicals( $h ) ) && ! t_has( $h, 'property="og:image"' ), array( t_title_of( $h ), t_meta_desc( $h ), t_canonicals( $h ) ) );
list( $st, $h ) = t_http( '/resource-center/success-stories/', array( 'X-AP-Test-Yoast' => '1' ) );
t_ok( 'D43 stand-down on listings too', 200 === $st && null === t_meta_desc( $h ) && 'Success Stories - AP Test' !== t_title_of( $h ), array( t_title_of( $h ) ) );

/* ── 1.1 front end: form panel on pages, JSON-LD ─────────────────────── */
list( $st, $h ) = t_http( '/ap-test-partners/' );
t_ok( 'F4 page form panel after the body', 200 === $st && t_has( $h, '<aside class="ap26-cta-mini ap-content-gate" id="ap-gate">' ) && t_has( $h, '<h2 class="ap26-cta-mini__heading ap-content-gate__heading">Partner Registration</h2>' ) && t_has( $h, 'Apply to join the reseller program.' ) && t_has( $h, '"formId":"' . $PARTNER_FORM . '"' ) && t_has( $h, 'js.hsforms.net/forms/embed/v2.js' ), $st );
$pos_body  = strpos( $h, 'More text.' );
$pos_panel = strpos( $h, 'ap-content-gate" id="ap-gate"' );
t_ok( 'F5 panel comes after the article body', false !== $pos_body && false !== $pos_panel && $pos_panel > $pos_body );
t_ok( 'F6 jump button is the first button of the body\'s first Buttons block', (bool) preg_match( '#<div class="wp-block-buttons[^"]*"[^>]*><div class="wp-block-button ap-content-gate-jump-wrap"><a class="wp-block-button__link wp-element-button ap-content-gate-jump" href="\#ap-gate">Partner Registration</a></div><div class="wp-block-button"><a [^>]*>Deal Registration</a>#', $h ) );
t_ok( 'F7 one panel, one jump button, ap-content.css loaded', 1 === substr_count( $h, 'ap-content-gate" id=' ) && 1 === substr_count( $h, 'ap-content-gate-jump"' ) && t_has( $h, 'ap-content.css' ) );
list( $st, $h ) = t_http( '/ap-test-kit-page/' );
t_ok( 'F8 kit page: panel + jump row at the top + own sheet (no blog sheet)', 200 === $st && t_has( $h, 'id="ap-gate"' ) && t_has( $h, '<div class="wp-block-buttons ap-content-gate-jump-row"><div class="wp-block-button ap-content-gate-jump-wrap"><a class="wp-block-button__link wp-element-button ap-content-gate-jump" href="#ap-gate">Apply Now</a>' ) && t_has( $h, 'ap-content.css' ) && ! t_has( $h, 'id="ap26-blog-inline-css"' ), $st );
list( $st, $h ) = t_http( '/resource-center/white-papers/ap-test-wp-epsilon/' );
t_ok( 'F9 white paper panel unchanged (id + default heading)', 200 === $st && t_has( $h, '<aside class="ap26-cta-mini ap-content-gate" id="get-the-white-paper">' ) && t_has( $h, '>Get The White Paper</h2>' ) && ! t_has( $h, 'ap-content-gate-jump' ) );

list( $st, $h ) = t_http( '/ap-test-legacy/' );
preg_match_all( '#<script type="application/ld\+json" class="ap-content-jsonld">(.*?)</script>#s', $h, $mm );
$head_end = strpos( $h, '</head>' );
$in_head  = $mm[0] && false !== $head_end && strpos( $h, $mm[0][0] ) < $head_end;
$decoded  = array_map( function ( $j ) { return json_decode( $j, true ); }, $mm[1] );
t_ok( 'J6 two JSON-LD scripts, in <head>', 2 === count( $mm[1] ) && $in_head, count( $mm[1] ) );
t_ok( 'J7 each parses and equals what was stored', isset( $decoded[0], $decoded[1] ) && json_decode( $ld_string, true ) === $decoded, $mm[1] );
t_ok( 'J8 </script> and <!-- escaped (no raw tag inside), unicode kept', ! preg_match( '#</script#i', implode( '', $mm[1] ) ) && t_has( $mm[1][0] ?? '', chr( 92 ) . 'u003C/script' . chr( 92 ) . 'u003E' ) && t_has( $mm[1][0] ?? '', chr( 92 ) . 'u003C!--' ) && t_has( $mm[1][0] ?? '', 'café ✓' ) );
t_ok( 'J9 empty object stays an object', t_has( $mm[1][1] ?? '', '"about":{}' ), $mm[1][1] ?? '' );
t_ok( 'J10 separate from any other graph (not merged)', 1 === substr_count( $h, '"@type":"FAQPage"' ) );
list( $st, $h ) = t_http( '/ap-test-legacy/', array( 'X-AP-Test-Yoast' => '1' ) );
t_ok( 'J11 still printed when Yoast is active', 2 === substr_count( $h, 'class="ap-content-jsonld"' ) );
list( $st, $h ) = t_http( '/ap-test-legacy/child/' );
t_ok( 'J12 broken stored JSON is never printed', 200 === $st && ! t_has( $h, 'ap-content-jsonld' ) );
list( $st, $h ) = t_http( '/resource-center/blog/ap-test-blog-gamma/' );
t_ok( 'J13 printed on a resource', 1 === substr_count( $h, 'class="ap-content-jsonld"' ) );
list( $st, $h ) = t_http( '/pressroom/ap-test-press-one/' );
t_ok( 'J14 printed on a press release', 1 === substr_count( $h, 'class="ap-content-jsonld"' ) );
list( $st, $h ) = t_http( '/resource-center/blog/ap-test-core-post/' );
t_ok( 'J15 printed on a core post', 1 === substr_count( $h, 'class="ap-content-jsonld"' ) );

/* Activation is idempotent; deactivate/reactivate keeps content and URLs */
deactivate_plugins( 'asset-panda-content/asset-panda-content.php' );
t_ok( 'E1 deactivation drops stored rules', false === get_option( 'rewrite_rules' ) );
list( $st ) = t_http( '/' );
t_ok( 'E2 site still 200 with the plugin off', 200 === $st, $st );
$r2 = activate_plugin( 'asset-panda-content/asset-panda-content.php' );
t_ok( 'E3 reactivation clean', ! is_wp_error( $r2 ) && ! get_option( 'ap_content_last_error' ) );
list( $st ) = t_http( '/resource-center/success-stories/ap-test-ss-alpha/' );
t_ok( 'E4 URL back after reactivation', 200 === $st, $st );
$n_terms = count( get_terms( array( 'taxonomy' => 'resource-types', 'hide_empty' => false ) ) );
t_ok( 'E5 no duplicate terms after reactivation', 6 === $n_terms, $n_terms );

$fail = count( array_filter( $results, function ( $r ) { return ! $r['pass']; } ) );
file_put_contents( $argv[2], wp_json_encode( array( 'total' => count( $results ), 'failed' => $fail, 'results' => $results ), JSON_PRETTY_PRINT ) );
echo "\n" . ( count( $results ) - $fail ) . '/' . count( $results ) . " passed\n";
exit( $fail ? 1 : 0 );
