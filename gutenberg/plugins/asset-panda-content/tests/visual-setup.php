<?php
/**
 * TEST ONLY — loads real imported content into the local site for the visual check
 * (tests/visual.mjs), through the same REST routes the importer uses. Everything it
 * creates is removed by cleanup.php (snapshot restore).
 *
 * php visual-setup.php <path/to/wp-load.php> <batches dir> <targets.json>
 *
 * Loads: the 16 legacy pages (batch-05-legacy.json, page-article), 3 compare
 * resources with buttons (batch-02.json), the "Security Framework" white paper with
 * its themed HubSpot form (batch-02.json), the /partners/ form-panel meta
 * (batch-08-partners-form.json), and two synthetic pages: one article page with a
 * filled and an outline button, one plain (non-article) page with a core button that
 * must stay untouched.
 *
 * @package AssetPandaContent
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
$dir = rtrim( $argv[2], '/\\' );

$admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
wp_set_current_user( $admin[0]->ID );

function vs_rest( $method, $route, $body ) {
	// A real REST request starts with no global post; in one CLI process the previous
	// response leaves one behind, and core then checks `template` against that post's type.
	$GLOBALS['post'] = null;
	$r = new WP_REST_Request( $method, $route );
	$r->set_header( 'content-type', 'application/json' );
	$r->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $r );
	return array( $res->get_status(), rest_get_server()->response_to_data( $res, false ) );
}
function vs_read( $dir, $file ) {
	$path = $dir . '/' . $file;
	return is_readable( $path ) ? json_decode( file_get_contents( $path ), true ) : array();
}

$fixes = array();
foreach ( vs_read( $dir, 'batch-06-fix.json' ) as $e ) {
	$fixes[ $e['source_url'] ] = $e;
}
$targets = array();
$fail    = 0;

// 16 legacy pages, flat (parent 0): the path does not matter for the look.
foreach ( vs_read( $dir, 'batch-05-legacy.json' ) as $e ) {
	if ( 'page' !== $e['type'] ) {
		continue;
	}
	$e = isset( $fixes[ $e['source_url'] ] ) ? $fixes[ $e['source_url'] ] : $e;
	$p = $e['payload'];
	unset( $p['parent'] );
	list( $s, $res ) = vs_rest( 'POST', '/wp/v2/pages', $p );
	if ( 409 === $s && ! empty( $res['data']['existing_id'] ) ) { // Path taken by an earlier test page: update it, as the runner does.
		list( $s, $res ) = vs_rest( 'POST', '/wp/v2/pages/' . (int) $res['data']['existing_id'], $p );
		$s = 200 === $s ? 201 : $s;
	}
	if ( 201 !== $s ) {
		++$fail;
		fwrite( STDERR, "page {$p['slug']}: $s " . wp_json_encode( $res ) . "\n" );
		continue;
	}
	$targets[] = array(
		'name' => 'legacy-' . $p['slug'],
		'kind' => 'legacy',
		'path' => wp_parse_url( $res['link'], PHP_URL_PATH ),
		'id'   => (int) $res['id'],
	);
}

// Resources: 3 compare posts with buttons + the white paper.
$want = array(
	'asset-panda-vs-excel-full-comparison-guide'                  => 'compare',
	'best-asset-tracking-software-top-platforms-compared'          => 'compare',
	'build-vs-buy-homegrown-asset-tracking-systems-vs-asset-panda-guide' => 'compare',
	'a-security-framework-for-managing-public-sector-assets'       => 'whitepaper',
);
foreach ( array( 'batch-02.json', 'batch-01.json', 'batch-03.json' ) as $file ) {
	foreach ( vs_read( $dir, $file ) as $e ) {
		$slug = isset( $e['payload']['slug'] ) ? $e['payload']['slug'] : '';
		if ( 'resource' !== $e['type'] || ! isset( $want[ $slug ] ) ) {
			continue;
		}
		list( $s, $res ) = vs_rest( 'POST', '/wp/v2/resource', $e['payload'] );
		if ( 201 !== $s ) {
			++$fail;
			fwrite( STDERR, "resource $slug: $s " . wp_json_encode( $res ) . "\n" );
			continue;
		}
		$targets[] = array(
			'name' => $want[ $slug ] . '-' . $slug,
			'kind' => $want[ $slug ],
			'path' => wp_parse_url( $res['link'], PHP_URL_PATH ),
			'id'   => (int) $res['id'],
		);
		unset( $want[ $slug ] );
	}
}
foreach ( array_keys( $want ) as $missing ) {
	++$fail;
	fwrite( STDERR, "resource $missing: not found in batches\n" );
}

// /partners/ form panel, exactly as batch-08 writes it (meta-only).
foreach ( vs_read( $dir, 'batch-08-partners-form.json' ) as $e ) {
	$partners_page = get_page_by_path( 'partners' );
	if ( ! $partners_page ) {
		++$fail;
		fwrite( STDERR, "partners page missing\n" );
		continue;
	}
	list( $s, $res ) = vs_rest( 'POST', '/wp/v2/pages/' . $partners_page->ID, array( 'meta' => $e['meta'] ) );
	if ( 200 !== $s ) {
		++$fail;
		fwrite( STDERR, "partners meta: $s " . wp_json_encode( $res ) . "\n" );
	}
	foreach ( $targets as &$t ) {
		if ( (int) $t['id'] === (int) $partners_page->ID ) {
			$t['kind'] = 'partners';
		}
	}
	unset( $t );
}

// Synthetic: filled + outline buttons in an article page; a core button on a plain page.
$btn = function ( $label, $outline ) {
	$cls = $outline ? ' is-style-outline' : '';
	$att = $outline ? ' {"className":"is-style-outline"}' : '';
	return '<!-- wp:button' . $att . ' --><div class="wp-block-button' . $cls . '"><a class="wp-block-button__link wp-element-button" href="#x">' . $label . '</a></div><!-- /wp:button -->';
};
$buttons = '<!-- wp:buttons --><div class="wp-block-buttons">' . $btn( 'Filled button', false ) . $btn( 'Outline button', true ) . '</div><!-- /wp:buttons -->';
list( $s, $res ) = vs_rest(
	'POST',
	'/wp/v2/pages',
	array(
		'slug'     => 'ap-vis-buttons',
		'status'   => 'publish',
		'template' => 'page-article',
		'title'    => 'AP Visual Buttons',
		'content'  => '<!-- wp:paragraph --><p>Before the buttons.</p><!-- /wp:paragraph -->' . $buttons,
	)
);
$targets[] = array( 'name' => 'synthetic-article-buttons', 'kind' => 'synthetic', 'path' => wp_parse_url( $res['link'], PHP_URL_PATH ), 'id' => (int) $res['id'] );
list( $s, $res ) = vs_rest(
	'POST',
	'/wp/v2/pages',
	array(
		'slug'    => 'ap-vis-kit-buttons',
		'status'  => 'publish',
		'title'   => 'AP Visual Kit Buttons',
		'content' => '<!-- wp:paragraph --><p>Not an article page.</p><!-- /wp:paragraph -->' . $buttons,
	)
);
$targets[] = array( 'name' => 'plain-page-buttons', 'kind' => 'plain', 'path' => wp_parse_url( $res['link'], PHP_URL_PATH ), 'id' => (int) $res['id'] );

file_put_contents( $argv[3], wp_json_encode( $targets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo count( $targets ) . " visual targets, $fail failures\n";
exit( $fail ? 1 : 0 );
