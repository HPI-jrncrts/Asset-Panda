<?php
/**
 * TEST ONLY — integration tests on the local site (real WordPress, real kit, real content plugin).
 *
 * php test.php <path/to/wp-load.php> <out dir>     (env AP_TEST_BASE = php -S base URL)
 *
 * Writes <out>/results.json and exits non-zero on any failure.
 *
 * @package APTemplateLibrary
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/image.php';
$out     = rtrim( $argv[2], '/\\' );
$session = json_decode( file_get_contents( $out . '/session.json' ), true );
$ids     = $session['ids'];
$users   = $session['users'];
$base    = getenv( 'AP_TEST_BASE' ) ? getenv( 'AP_TEST_BASE' ) : 'http://127.0.0.1:8898';

$results = array();
function t_ok( $name, $pass, $detail = '' ) {
	global $results;
	$results[] = array(
		'test'   => $name,
		'pass'   => (bool) $pass,
		'detail' => $pass ? '' : $detail,
	);
	echo ( $pass ? 'PASS ' : 'FAIL ' ) . $name . ( $pass ? '' : '  -- ' . substr( is_string( $detail ) ? $detail : wp_json_encode( $detail ), 0, 600 ) ) . "\n";
}
function t_blocks( $content ) {
	return array_values(
		array_filter(
			parse_blocks( $content ),
			static function ( $b ) {
				return ! ap_tl_is_whitespace_block( $b );
			}
		)
	);
}
function t_rest( $method, $route, $params, $user ) {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}
/** Builder data as the browser would get it (JSON round trip). */
function t_builder( $key ) {
	$data = ap_tl_builder_data( $key );
	return is_wp_error( $data ) ? $data : json_decode( wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ), true );
}
/** Boxes payload exactly as the builder sends it untouched. */
function t_payload( array $data ) {
	return array_map(
		static function ( $b ) {
			return 'kit' === $b['kind'] ? array(
				'ref'   => $b['ref'],
				'name'  => $b['name'],
				'attrs' => $b['attrs'],
			) : array(
				'ref'   => $b['ref'],
				'edits' => new stdClass(),
			);
		},
		$data['blocks']
	);
}
function t_http( $method, $url, $cookies, $headers = array(), $body = null ) {
	$args = array(
		'method'      => $method,
		'headers'     => array_merge( array( 'Host' => 'asset-panda-local.local' ), $headers ),
		'cookies'     => $cookies,
		'redirection' => 0,
		'timeout'     => 60,
		'body'        => $body,
	);
	$r = wp_remote_request( $url, $args );
	return is_wp_error( $r ) ? array( 0, $r->get_error_message(), array() ) : array( wp_remote_retrieve_response_code( $r ), wp_remote_retrieve_body( $r ), wp_remote_retrieve_headers( $r ) );
}

$admin = (int) $users['admin'];
$edit  = (int) $users['editor'];
$sub   = (int) $users['subscriber'];
wp_set_current_user( $admin );

/* ---------------------------------------------------------------- static */
$plugin_dir = WP_PLUGIN_DIR . '/ap-template-library';
$sources    = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( preg_match( '/\.(php|js)$/', $file->getFilename() ) ) {
		$sources .= file_get_contents( $file->getPathname() );
	}
}
t_ok( 'S1 no remote-request functions in the plugin', ! preg_match( '/wp_remote_|wp_safe_remote|curl_|fsockopen|download_url|\bfetch\(\s*[\'"]https?:/', $sources ) );
t_ok( 'S2 no file writes in the plugin', ! preg_match( '/file_put_contents|fopen\s*\(|fwrite\s*\(|WP_Filesystem|move_uploaded_file|wp_upload_bits|unlink\s*\(/', $sources ) );
t_ok( 'S3 no innerHTML writes in builder.js except the inert DOMParser', 0 === preg_match_all( '/\.innerHTML\s*=/', file_get_contents( $plugin_dir . '/assets/builder.js' ) ) );

/* -------------------------------------------------------------- registry */
$templates = ap_tl_templates();
foreach ( array( 'solution', 'product', 'industry-hub', 'platform-aip', 'platform-ursa', 'blog', 'case-study', 'compare', 'partners' ) as $key ) {
	t_ok( "R1 default template '$key' registered", isset( $templates[ $key ] ) );
}
$ctx = ap_tl_template_context( $templates['solution'] );
t_ok( 'R2 Solution page resolves /solutions/inventory-management/', 'ok' === $ctx['status'] && (int) $ctx['post']->ID === (int) $ids['solution'] && 'page' === $ctx['post_type'], array( $ctx['status'], $ctx['post'] ? $ctx['post']->ID : null ) );
$ctx = ap_tl_template_context( $templates['product'] );
t_ok( 'R3 Product page resolves by path', 'ok' === $ctx['status'] && (int) $ctx['post']->ID === (int) $ids['product'] );
$ctx = ap_tl_template_context( $templates['blog'] );
t_ok( 'R4 Blog post resolves the resource by slug, creates a blog resource', 'ok' === $ctx['status'] && (int) $ctx['post']->ID === (int) $ids['blog'] && 'resource' === $ctx['post_type'] && 'blog' === $ctx['resource_type'] );
$ctx = ap_tl_template_context( $templates['compare'] );
t_ok( 'R5 Compare resolves', 'ok' === $ctx['status'] && (int) $ctx['post']->ID === (int) $ids['compare'] );
$ctx = ap_tl_template_context( $templates['partners'] );
t_ok( 'R6 Partners page slot shows reference missing (no /partners/ locally)', 'missing' === $ctx['status'] && null === $ctx['post'] );
$ctx = ap_tl_template_context( $templates['case-study'] );
t_ok( 'R7 Case study with no case studies on the site is missing, not an error', 'missing' === $ctx['status'] );
// Fallback: an unknown slug falls back to the newest resource of the type.
$fallback = ap_tl_resolve_source(
	array(
		'post_type'     => 'resource',
		'resource_type' => 'compare',
		'slug'          => 'no-such-slug',
	)
);
t_ok( 'R8 unknown resource slug falls back to the newest of its type', $fallback && (int) $fallback->ID === (int) $ids['compare'] );
// Drafts are never references.
$draft_ref = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'Draft ref',
	)
);
t_ok( 'R9 an unpublished post is never a reference', null === ap_tl_resolve_source( array( 'id' => $draft_ref ) ) );
// The filter.
$filter = static function ( $t ) use ( $ids ) {
	$t['code-added'] = array(
		'label'  => 'Added in code',
		'group'  => 'landing',
		'source' => array( 'id' => $ids['product'] ),
	);
	unset( $t['legal'] );
	return $t;
};
add_filter( 'ap_tl_templates', $filter );
ap_tl_templates_reset();
$t = ap_tl_templates();
t_ok( 'R10 ap_tl_templates filter adds and removes templates', isset( $t['code-added'] ) && 'code' === $t['code-added']['origin'] && ! isset( $t['legal'] ) );
remove_filter( 'ap_tl_templates', $filter );
ap_tl_templates_reset();
// Hidden setting.
ap_tl_save_settings( array( 'hidden' => array( 'legal' ) ) );
$all = ap_tl_all_templates();
t_ok( 'R11 a hidden default leaves the library but stays in settings', ! isset( ap_tl_templates()['legal'] ) && ! empty( $all['legal']['hidden'] ) );
ap_tl_save_settings( array() );
t_ok( 'R12 settings reset shows it again', isset( ap_tl_templates()['legal'] ) );

/* --------------------------------------------------------------- builder */
$sol = t_builder( 'solution' );
t_ok( 'B1 builder data for Solution page', ! is_wp_error( $sol ) && 7 === count( $sol['blocks'] ), is_wp_error( $sol ) ? $sol->get_error_message() : count( $sol['blocks'] ) );
$hero = $sol['blocks'][0];
t_ok( 'B2 box 1 = kit vertical-hero titled from block.json, AP prefix dropped', 'kit' === $hero['kind'] && 'assetpanda/vertical-hero' === $hero['name'] && 'Vertical Hero' === $sol['types']['assetpanda/vertical-hero']['title'], $sol['types']['assetpanda/vertical-hero']['title'] ?? null );
t_ok( 'B3 fields pre-filled from the reference', "Inventory software\nthat keeps count" === $hero['attrs']['title'] && 3 === count( $hero['attrs']['cards'] ) && false !== strpos( $hero['attrs']['dashboard'], 'dashboard.png' ) );
t_ok( 'B4 attributes the reference omits show their block.json default', '1440/550' === $hero['attrs']['ratio'] && 'scale' === $hero['attrs']['tablet'] );
t_ok( 'B5 hidden attributes (align, className…) are not fields', ! isset( $hero['attrs']['align'] ) && ! isset( $sol['types']['assetpanda/vertical-hero']['attributes']['lock'] ) );
t_ok( 'B6 help text comes from the kit component.json', false !== strpos( (string) ( $sol['types']['assetpanda/vertical-hero']['help']['title'] ?? '' ), 'Line breaks are kept' ) );
$shape = wp_list_pluck( (array) ( $sol['types']['assetpanda/vertical-hero']['shapes']['features'] ?? array() ), 'key' );
t_ok( 'B7 empty list gets its row shape from the contract ("Each: label; icon or icon_svg")', in_array( 'label', $shape, true ) && in_array( 'icon', $shape, true ) && in_array( 'icon_svg', $shape, true ), $shape );
t_ok( 'B8 enum and type info passed for selects', array( 2, 3, 4 ) === $sol['types']['assetpanda/stats']['attributes']['columns']['enum'] );
t_ok( 'B9 core heading and paragraph boxes get a text editor', 'core' === $sol['blocks'][2]['kind'] && 'text' === $sol['blocks'][2]['node']['editor'] && 'Heading (H2)' === $sol['blocks'][2]['title'] && 'text' === $sol['blocks'][3]['node']['editor'] );
t_ok( 'B10 object-typed defaults encode as {} (not [])', false !== strpos( wp_json_encode( ap_tl_builder_data( 'solution' ) ), '"m_flow":{}' ) );

$blog = t_builder( 'blog' );
$names = wp_list_pluck( $blog['blocks'], 'name' );
t_ok( 'B11 blog builder: 9 boxes in order', array( 'core/paragraph', 'core/heading', 'core/paragraph', 'core/list', 'core/image', 'core/group', 'core/embed', 'core/table', 'assetpanda/article-cta' ) === $names, $names );
t_ok( 'B12 list editor with its items', 'list' === $blog['blocks'][3]['node']['editor'] && array( 'Tag every asset', 'Scan on the move', 'Report in real time' ) === $blog['blocks'][3]['node']['fields']['items'] );
t_ok( 'B13 image editor with url and alt', 'image' === $blog['blocks'][4]['node']['editor'] && 'The asset dashboard' === $blog['blocks'][4]['node']['fields']['alt'] );
$button = $blog['blocks'][5]['node']['children'][0]['children'][0] ?? array();
t_ok( 'B14 group > buttons > button opens to an editable button at path 0/0', 'button' === ( $button['editor'] ?? '' ) && '0/0' === $button['path'] && 'Book a demo' === $button['fields']['text'], $button );
t_ok( 'B15 embed editor; table kept as is with a preview', 'embed' === $blog['blocks'][6]['node']['editor'] && null === $blog['blocks'][7]['node']['editor'] && false !== strpos( $blog['blocks'][7]['node']['preview'], 'Tag' ) );
t_ok( 'B16 resource page settings: resource type, industry, excerpt, featured URL', 'resource' === $blog['settings']['post_type'] && in_array( 'resource-types', wp_list_pluck( $blog['settings']['taxonomies'], 'name' ), true ) && 'A short guide to asset tracking.' === $blog['page']['excerpt'] && false !== strpos( $blog['page']['featured']['url'], 'card-a.png' ) );
t_ok( 'B17 page settings: parent pre-filled from the reference', (int) get_post( $ids['solution'] )->post_parent === (int) $sol['page']['parent'] && $sol['settings']['hierarchical'] );
$missing = ap_tl_builder_data( 'partners' );
t_ok( 'B18 builder for a missing reference returns a clear error', is_wp_error( $missing ) && 'ap_tl_reference_missing' === $missing->get_error_code() );

/* ------------------------------------------------------------ round trip */
foreach ( array( 'solution', 'blog', 'compare' ) as $key ) {
	$data = t_builder( $key );
	$ref  = get_post( $ids[ $key ] );
	$got  = ap_tl_build_content( $ref, t_payload( $data ) );
	t_ok( "T1 untouched $key template rebuilds the reference content byte for byte", ! is_wp_error( $got ) && trim( $got ) === trim( $ref->post_content ), is_wp_error( $got ) ? $got->get_error_message() : substr( $got, 0, 300 ) );
}

/* ------------------------------------------------------------- kit edits */
$payload                       = t_payload( $sol );
$payload[0]['attrs']['title']  = 'Edited <script>alert(1)</script>title';
$payload[0]['attrs']['cta_url'] = 'javascript:alert(1)';
$payload[0]['attrs']['dashboard'] = 'https://cdn.example.test/new-dash.png';
$payload[0]['attrs']['cards'][0]['icon_svg'] = '<svg viewBox="0 0 10 10" onload="alert(1)"><script>alert(2)</script><path d="M0 0h10"/></svg>';
$payload[0]['attrs']['evil']   = 'x';
$payload[0]['attrs']['className'] = 'injected';
$payload[0]['attrs']['tablet'] = 'neon';
$payload[4]['attrs']['items'][] = array(
	'value'       => '4x',
	'label'       => 'Faster audits',
	'description' => 'New row',
);
$payload[4]['attrs']['columns'] = '4';
$built = ap_tl_build_content( get_post( $ids['solution'] ), $payload );
$bl    = t_blocks( $built );
$h     = $bl[0]['attrs'];
t_ok( 'K1 edited title stored, script tag stripped', 0 === strpos( $h['title'], 'Edited' ) && false === stripos( $h['title'], '<script' ), $h['title'] );
t_ok( 'K2 javascript: link removed', '' === $h['cta_url'], $h['cta_url'] );
t_ok( 'K3 image URL kept', 'https://cdn.example.test/new-dash.png' === $h['dashboard'] );
t_ok( 'K4 SVG keeps drawing elements, loses script and handlers', false !== strpos( $h['cards'][0]['icon_svg'], '<path' ) && false === stripos( $h['cards'][0]['icon_svg'], 'onload' ) && false === stripos( $h['cards'][0]['icon_svg'], 'script' ), $h['cards'][0]['icon_svg'] );
t_ok( 'K5 unknown and hidden attributes are not taken from the request', ! isset( $h['evil'] ) && ! isset( $h['className'] ) );
t_ok( 'K6 enum: invalid value falls back to the default', ! isset( $h['tablet'] ) || 'scale' === $h['tablet'], $h['tablet'] ?? null );
t_ok( 'K7 repeater item added; numeric enum coerced from "4" to 4', 4 === count( $bl[4]['attrs']['items'] ) && '4x' === $bl[4]['attrs']['items'][3]['value'] && 4 === $bl[4]['attrs']['columns'], $bl[4]['attrs'] );
t_ok( 'K8 attributes the reference omitted and the user left at default stay out', ! array_key_exists( 'ratio', $h ) && ! array_key_exists( 'm_top', $h ) );
t_ok( 'K9 untouched kit blocks unchanged', serialize_block( $bl[1] ) === serialize_block( t_blocks( get_post( $ids['solution'] )->post_content )[1] ) );
$type_payload                      = t_payload( $sol );
$type_payload[0]['attrs']['badges'] = 'not a list';
$type_payload[0]['attrs']['m_top']  = 'abc';
$tb = t_blocks( ap_tl_build_content( get_post( $ids['solution'] ), $type_payload ) );
t_ok( 'K10 wrong types fall back to the reference value', 2 === count( $tb[0]['attrs']['badges'] ) && ! array_key_exists( 'm_top', $tb[0]['attrs'] ), $tb[0]['attrs']['m_top'] ?? 'absent' );
// Order / duplicate / remove.
$p2    = t_payload( $sol );
$order = ap_tl_build_content( get_post( $ids['solution'] ), array( $p2[6], $p2[0], $p2[0] ) );
t_ok( 'K11 reorder, duplicate and remove sections', array( 'assetpanda/demo-cta', 'assetpanda/vertical-hero', 'assetpanda/vertical-hero' ) === wp_list_pluck( t_blocks( $order ), 'blockName' ) );
$bad = ap_tl_build_content( get_post( $ids['solution'] ), array( array( 'ref' => 0, 'name' => 'assetpanda/faq', 'attrs' => array() ) ) );
t_ok( 'K12 a box whose block no longer matches the reference is refused (409)', is_wp_error( $bad ) && 409 === $bad->get_error_data()['status'] );
$bad = ap_tl_build_content( get_post( $ids['solution'] ), array( array( 'ref' => 99 ) ) );
t_ok( 'K13 an unknown section index is refused', is_wp_error( $bad ) );
$bad = ap_tl_build_content( get_post( $ids['solution'] ), array() );
t_ok( 'K14 no sections is refused (400)', is_wp_error( $bad ) && 400 === $bad->get_error_data()['status'] );
// Known strings pass through unchanged.
$known = ap_tl_known_strings(
	parse_blocks(
		serialize_block(
			array(
				'blockName'    => 'assetpanda/hero',
				'attrs'        => array( 'icon_svg' => '<svg style="color:red"></svg>' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		)
	)
);
$k15a  = ap_tl_sanitize_value( '<svg style="color:red"></svg>', 'icon_svg', $known );
$k15b  = ap_tl_sanitize_value( '<svg style="color:red"></svg>', 'icon_svg', array() );
t_ok( 'K15 a string already stored in the reference is kept exactly; the same string typed new is filtered', '<svg style="color:red"></svg>' === $k15a && '<svg></svg>' === $k15b, array( $k15a, $k15b ) );
t_ok( 'K16 relative paths kept, bare words in image keys not turned into URLs', '/get-a-demo/' === ap_tl_sanitize_string( '/get-a-demo/', 'cta_url' ) && 'check' === ap_tl_sanitize_string( 'check', 'icon' ) );
t_ok( 'K16b scheme URLs in link/image fields: javascript:/data: dropped, mailto: kept', '' === ap_tl_sanitize_string( 'javascript:alert(1)', 'url' ) && '' === ap_tl_sanitize_string( 'data:image/png;base64,AAAA', 'image' ) && 'mailto:a@example.test' === ap_tl_sanitize_string( 'mailto:a@example.test', 'link_url' ) );
t_ok( 'K17 keys outside [A-Za-z0-9_-] dropped from nested objects', array( 'ok' => 'v' ) === ap_tl_sanitize_value( array( 'ok' => 'v', 'b<ad' => 'x' ), 'items', array() ) );

/* ------------------------------------------------------------ core edits */
$bp = t_payload( $blog );
$bp[0]['edits'] = array( '' => array( 'text' => '<em>New</em> intro<script>alert(1)</script> with <a href="javascript:alert(1)">link</a>' ) );
$bp[1]['edits'] = array( '' => array( 'text' => 'Edited heading' ) );
$bp[3]['edits'] = array( '' => array( 'items' => array( 'Tag every asset', 'Scan on the move', 'Report in real time', 'Audit <strong>twice</strong> a year' ) ) );
$bp[5]['edits'] = array(
	'0/0'      => array(
		'text' => 'Talk to sales',
		'url'  => '/contact/',
	),
	'../1'     => array( 'text' => 'x' ),
	'0/0/0/9'  => array( 'text' => 'x' ),
);
$bp[6]['edits'] = array( '' => array( 'url' => 'https://www.youtube.com/watch?v=abc123' ) );
$bp[7]['edits'] = array( '' => array( 'html' => '<p>not a table editor</p>' ) );
// A real attachment for the image edit.
$upload = wp_upload_dir();
$att_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'ap-tl-test attachment',
		'post_status'    => 'inherit',
	),
	$upload['basedir'] . '/ap-tl-fixtures/card-b.png'
);
$bp[4]['edits'] = array(
	'' => array(
		'url' => content_url( 'uploads/ap-tl-fixtures/card-b.png' ),
		'alt' => 'New <b>alt</b>',
		'id'  => $att_id,
	),
);
$cb = t_blocks( ap_tl_build_content( get_post( $ids['blog'] ), $bp ) );
t_ok( 'C1 paragraph text edited; script and javascript: removed; inline markup kept', false !== strpos( $cb[0]['innerHTML'], '<em>New</em> intro' ) && false === stripos( $cb[0]['innerHTML'], 'script' ) && false === stripos( $cb[0]['innerHTML'], 'javascript:' ) && 0 === strpos( $cb[0]['innerHTML'], '<p>' ), $cb[0]['innerHTML'] );
t_ok( 'C2 heading text edited, wrapper kept', '<h2 class="wp-block-heading">Edited heading</h2>' === $cb[1]['innerHTML'] );
t_ok( 'C3 list rebuilt with 4 list-item blocks', 4 === count( $cb[3]['innerBlocks'] ) && '<li>Audit <strong>twice</strong> a year</li>' === $cb[3]['innerBlocks'][3]['innerHTML'] && false !== strpos( serialize_block( $cb[3] ), '<ul class="wp-block-list"><!-- wp:list-item -->' ), serialize_block( $cb[3] ) );
t_ok( 'C4 image: new src, alt text sanitised, wp-image-<id> class and id attribute in step', false !== strpos( $cb[4]['innerHTML'], 'card-b.png' ) && false !== strpos( $cb[4]['innerHTML'], 'alt="New alt"' ) && false !== strpos( $cb[4]['innerHTML'], 'wp-image-' . $att_id ) && $att_id === (int) $cb[4]['attrs']['id'], array( $cb[4]['innerHTML'], $cb[4]['attrs'] ) );
$btn = $cb[5]['innerBlocks'][0]['innerBlocks'][0];
t_ok( 'C5 nested button text and link edited; bad paths ignored', false !== strpos( $btn['innerHTML'], '>Talk to sales</a>' ) && false !== strpos( $btn['innerHTML'], 'href="/contact/"' ) && 1 === count( $cb[5]['innerBlocks'] ), $btn['innerHTML'] );
t_ok( 'C6 embed URL edited in attributes and markup', 'https://www.youtube.com/watch?v=abc123' === $cb[6]['attrs']['url'] && false !== strpos( $cb[6]['innerHTML'], 'abc123' ) && false === strpos( $cb[6]['innerHTML'], 'dQw4w9WgXcQ' ) );
t_ok( 'C7 a block without an editor ignores edits (table unchanged)', false !== strpos( $cb[7]['innerHTML'], '<table>' ) );
// HTML for users without unfiltered_html.
$deny = static function ( $caps, $cap ) {
	return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
};
add_filter( 'map_meta_cap', $deny, 10, 2 );
$freeform = ap_tl_apply_core_edit(
	array(
		'blockName'    => null,
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '<p>old</p>',
		'innerContent' => array( '<p>old</p>' ),
	),
	array( 'html' => '<p>new</p><script>alert(1)</script><iframe src="https://x.test"></iframe>' )
);
remove_filter( 'map_meta_cap', $deny, 10 );
t_ok( 'C8 classic/HTML edits are kses-filtered for users without unfiltered_html', '<p>new</p>alert(1)' === $freeform['innerHTML'], $freeform['innerHTML'] );

/* --------------------------------------------------------- REST: drafts */
list( $st, $body ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'solution', 'page' => array( 'title' => 'X' ), 'blocks' => t_payload( $sol ) ), $sub );
t_ok( 'D1 subscriber cannot create drafts (403)', 403 === $st, array( $st, $body ) );
list( $st, $body ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'solution', 'page' => array( 'title' => 'X' ), 'blocks' => t_payload( $sol ) ), 0 );
t_ok( 'D2 logged-out request refused (401)', 401 === $st, array( $st, $body ) );

$ep                         = t_payload( $sol );
$ep[0]['attrs']['title']    = 'Editor built this page';
list( $st, $body ) = t_rest(
	'POST',
	'/ap-template-library/v1/draft',
	array(
		'template' => 'solution',
		'page'     => array(
			'title'  => 'Editor test page',
			'slug'   => 'editor-test-page',
			'parent' => get_page_by_path( 'solutions' )->ID,
		),
		'blocks'   => $ep,
	),
	$edit
);
$draft = is_array( $body ) && ! empty( $body['id'] ) ? get_post( $body['id'] ) : null;
t_ok( 'D3 editor creates a page draft (201)', 201 === $st && $draft && 'draft' === $draft->post_status && 'page' === $draft->post_type, array( $st, $body ) );
if ( $draft ) {
	t_ok( 'D4 draft: title, slug, parent, author', 'Editor test page' === $draft->post_title && 'editor-test-page' === $draft->post_name && (int) get_page_by_path( 'solutions' )->ID === (int) $draft->post_parent && $edit === (int) $draft->post_author );
	t_ok( 'D5 draft copies the reference template (_wp_page_template) and records its template', 'page-no-title' === get_post_meta( $draft->ID, '_wp_page_template', true ) && 'solution' === get_post_meta( $draft->ID, '_ap_tl_template', true ) );
	t_ok( 'D6 draft content carries the edit; renders through the kit', 'Editor built this page' === t_blocks( $draft->post_content )[0]['attrs']['title'] && false !== strpos( do_blocks( $draft->post_content ), 'Editor built this page' ) );
	t_ok( 'D7 response has edit and preview links', false !== strpos( $body['edit_link'], 'post.php?post=' . $draft->ID ) && false !== strpos( $body['preview_link'], 'preview=true' ) );
}
// Resource draft with terms + featured URL copy.
$bp2 = t_payload( $blog );
$edu = get_term_by( 'slug', 'education', 'resource-industry' );
list( $st, $body ) = t_rest(
	'POST',
	'/ap-template-library/v1/draft',
	array(
		'template' => 'blog',
		'page'     => array(
			'title'    => 'Blog from template',
			'excerpt'  => "Two\nlines",
			'featured' => $blog['page']['featured'],
			'terms'    => array(
				'resource-types'    => array(),
				'resource-industry' => $edu ? array( $edu->term_id, 999999 ) : array(),
			),
		),
		'blocks'   => $bp2,
	),
	$edit
);
$rdraft = is_array( $body ) && ! empty( $body['id'] ) ? get_post( $body['id'] ) : null;
t_ok( 'D8 editor creates a blog resource draft', 201 === $st && $rdraft && 'resource' === $rdraft->post_type && 'draft' === $rdraft->post_status, array( $st, $body ) );
if ( $rdraft ) {
	$types = wp_get_object_terms( $rdraft->ID, 'resource-types', array( 'fields' => 'slugs' ) );
	$inds  = wp_get_object_terms( $rdraft->ID, 'resource-industry', array( 'fields' => 'slugs' ) );
	t_ok( 'D9 resource type defaults to the template\'s (blog); valid industry kept, unknown id dropped', array( 'blog' ) === $types && array( 'education' ) === $inds, array( $types, $inds ) );
	t_ok( 'D10 excerpt and the reference\'s imported featured image URL copied', "Two\nlines" === $rdraft->post_excerpt && false !== strpos( (string) get_post_meta( $rdraft->ID, '_ap_featured_image_url', true ), 'card-a.png' ) );
}
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'solution', 'page' => array( 'title' => '  ' ), 'blocks' => t_payload( $sol ) ), $admin );
t_ok( 'D11 empty title refused (400)', 400 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'nope', 'page' => array( 'title' => 'x' ), 'blocks' => t_payload( $sol ) ), $admin );
t_ok( 'D12 unknown template (404)', 404 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'partners', 'page' => array( 'title' => 'x' ), 'blocks' => array( array( 'ref' => 0 ) ) ), $admin );
t_ok( 'D13 template with a missing reference (409)', 409 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'solution', 'page' => array( 'title' => 'x' ), 'blocks' => 'nope' ), $admin );
t_ok( 'D14 malformed blocks refused by the route schema (400)', 400 === $st );
global $wpdb;
t_ok( 'D15 nothing created by the plugin is published', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_status <> 'draft'", '_ap_tl_template' ) ) );

/* -------------------------------------------------------------- progress */
$state = wp_json_encode(
	array(
		'v'     => 1,
		'hash'  => $sol['hash'],
		'page'  => array( 'title' => 'P' ),
		'boxes' => t_payload( $sol ),
	)
);
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'solution', 'state' => $state ), $edit );
$saved = ap_tl_get_progress( $edit );
t_ok( 'P1 progress saved in user meta (JSON with backslashes intact)', 200 === $st && isset( $saved['solution']['state'] ) && json_decode( $saved['solution']['state'], true )['boxes'][0]['attrs']['title'] === "Inventory software\nthat keeps count" );
wp_set_current_user( $edit );
t_ok( 'P2 builder data carries the saved state for this user', null !== ap_tl_builder_data( 'solution' )['progress'] );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'solution', 'state' => 'not json' ), $edit );
t_ok( 'P3 invalid progress refused (400)', 400 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'solution', 'state' => str_repeat( 'x', AP_TL_MAX_PROGRESS + 1 ) ), $edit );
t_ok( 'P4 oversized progress refused (413)', 413 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'nope', 'state' => $state ), $edit );
t_ok( 'P5 progress for an unknown template refused (404)', 404 === $st );
list( $st ) = t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'solution', 'state' => $state ), $sub );
t_ok( 'P6 subscriber cannot save progress (403)', 403 === $st );
list( $st ) = t_rest( 'DELETE', '/ap-template-library/v1/progress', array( 'template' => 'solution' ), $edit );
t_ok( 'P7 progress discarded', 200 === $st && ! isset( ap_tl_get_progress( $edit )['solution'] ) );
t_rest( 'POST', '/ap-template-library/v1/progress', array( 'template' => 'blog', 'state' => $state ), $edit );
t_rest( 'POST', '/ap-template-library/v1/draft', array( 'template' => 'blog', 'page' => array( 'title' => 'Clears progress' ), 'blocks' => t_payload( $blog ) ), $edit );
t_ok( 'P8 sending to draft clears that template\'s saved progress', ! isset( ap_tl_get_progress( $edit )['blog'] ) );

/* ---------------------------------------------------- HTTP (php -S): auth */
$jar = static function ( $who ) use ( $session ) {
	$c = array();
	foreach ( $session['cookies'][ $who ] as $cookie ) {
		$c[] = new WP_Http_Cookie(
			array(
				'name'  => $cookie['name'],
				'value' => $cookie['value'],
			)
		);
	}
	return $c;
};
$json = wp_json_encode(
	array(
		'template' => 'solution',
		'page'     => array( 'title' => 'via http' ),
		'blocks'   => t_payload( $sol ),
	)
);
list( $st, $b ) = t_http( 'POST', $base . '/wp-json/ap-template-library/v1/draft', $jar( 'admin' ), array( 'Content-Type' => 'application/json' ), $json );
t_ok( 'H1 admin cookie without the REST nonce is not authenticated (401)', 401 === $st, array( $st, substr( (string) $b, 0, 200 ) ) );
list( $st, $b ) = t_http( 'POST', $base . '/wp-json/ap-template-library/v1/draft', $jar( 'admin' ), array( 'Content-Type' => 'application/json', 'X-WP-Nonce' => 'bad' ), $json );
t_ok( 'H2 wrong nonce refused (403 rest_cookie_invalid_nonce)', 403 === $st && false !== strpos( (string) $b, 'rest_cookie_invalid_nonce' ), array( $st, substr( (string) $b, 0, 200 ) ) );
list( $st, $b, $hd ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library', array() );
t_ok( 'H3 logged out: library redirects to login', 302 === $st && false !== strpos( (string) ( $hd['location'] ?? '' ), 'wp-login.php' ), array( $st, $hd['location'] ?? '' ) );
list( $st, $b ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library', $jar( 'subscriber' ) );
t_ok( 'H4 subscriber: library refused (403)', 403 === $st, array( $st, substr( wp_strip_all_tags( (string) $b ), 0, 200 ) ) );
list( $st, $b ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library-settings', $jar( 'editor' ) );
t_ok( 'H5 editor: settings refused (manage_options)', 403 === $st, array( $st ) );
list( $st, $b ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library', $jar( 'editor' ) );
t_ok( 'H6 editor: library opens', 200 === $st && false !== strpos( (string) $b, 'ap-tl-card' ), array( $st ) );
list( $st, $b ) = t_http( 'POST', $base . '/wp-admin/admin-post.php', $jar( 'admin' ), array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'action=ap_tl_toggle&key=legal' );
t_ok( 'H7 settings action without a nonce refused', 403 === $st && ! in_array( 'legal', ap_tl_settings()['hidden'], true ), array( $st ) );
list( $st, $b ) = t_http( 'POST', $base . '/wp-admin/admin-post.php', $jar( 'editor' ), array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'action=ap_tl_toggle&key=legal&_wpnonce=x' );
t_ok( 'H8 settings action as editor refused', 403 === $st, array( $st ) );
list( $st, $b ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library&template=partners', $jar( 'admin' ) );
t_ok( 'H9 builder for a missing reference shows "Reference missing" instead of failing', 200 === $st && false !== strpos( (string) $b, 'Reference missing' ), array( $st ) );
list( $st, $b ) = t_http( 'GET', $base . '/wp-admin/admin.php?page=ap-template-library&template=solution', $jar( 'admin' ) );
t_ok( 'H10 builder page prints its data safely (no raw </script> from content)', 200 === $st && false !== strpos( (string) $b, 'window.AP_TL_BUILDER = {' ) && false !== strpos( (string) $b, 'ap-tl-builder' ), array( $st ) );

file_put_contents( $out . '/results.json', wp_json_encode( $results, JSON_PRETTY_PRINT ) );
$failed = count(
	array_filter(
		$results,
		static function ( $r ) {
			return ! $r['pass'];
		}
	)
);
echo "\n" . ( count( $results ) - $failed ) . '/' . count( $results ) . " passed\n";
exit( $failed ? 1 : 0 );
