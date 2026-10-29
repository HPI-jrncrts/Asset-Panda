<?php
/**
 * TEST ONLY — activates the plugins and creates the reference fixtures on the local site.
 *
 * php setup.php <path/to/wp-load.php> <out dir> activate   (activates the three plugins)
 * php setup.php <path/to/wp-load.php> <out dir> fixtures   (fresh process: plugins loaded normally)
 *
 * Creates (all removed again by cleanup.php):
 * - reference pages /solutions/inventory-management/ and /products/asset-panda-for-local-government/
 *   built from assetpanda/* kit blocks (attribute shapes from the kit's block.json), plus core blocks;
 * - resources what-is-asset-tracking (blog: core blocks) and asset-panda-vs-ezo (compare: core + kit);
 * - three users (administrator, editor, subscriber) with random passwords that are never printed;
 *   the browser is signed in with auth cookies generated here (written to <out>/session.json).
 *
 * @package APTemplateLibrary
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = 'asset-panda-local.local';
$_SERVER['REQUEST_URI'] = '/';
require $argv[1];
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$out  = rtrim( $argv[2], '/\\' );
$mode = isset( $argv[3] ) ? $argv[3] : '';

if ( 'activate' === $mode ) {
	foreach ( array( 'asset-panda-ui-kit/asset-panda-ui-kit.php', 'asset-panda-content/asset-panda-content.php', 'ap-template-library/ap-template-library.php' ) as $plugin ) {
		$r = activate_plugin( $plugin );
		if ( is_wp_error( $r ) ) {
			fwrite( STDERR, "activate $plugin failed: " . $r->get_error_message() . "\n" );
			exit( 2 );
		}
		echo "activated $plugin\n";
	}
	exit( 0 );
}
if ( 'fixtures' !== $mode ) {
	fwrite( STDERR, "mode must be activate or fixtures\n" );
	exit( 1 );
}
foreach ( array( 'ap_tl_templates', 'ap_render_component', 'ap_content_ensure_terms' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		fwrite( STDERR, "$fn missing: plugin not loaded\n" );
		exit( 2 );
	}
}
if ( ! post_type_exists( 'resource' ) || ! WP_Block_Type_Registry::get_instance()->is_registered( 'assetpanda/vertical-hero' ) ) {
	fwrite( STDERR, "resource post type or kit blocks missing\n" );
	exit( 2 );
}
ap_content_ensure_terms();

$img = static function ( $name ) {
	return content_url( 'uploads/ap-tl-fixtures/' . $name );
};
$kit = static function ( $name, array $attrs ) {
	return array(
		'blockName'    => $name,
		'attrs'        => $attrs,
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
};
$leaf = static function ( $name, $html, array $attrs = array() ) {
	return array(
		'blockName'    => $name,
		'attrs'        => $attrs,
		'innerBlocks'  => array(),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	);
};
$wrap = static function ( $name, $open, array $inner, $close, array $attrs = array() ) {
	return array(
		'blockName'    => $name,
		'attrs'        => $attrs,
		'innerBlocks'  => $inner,
		'innerHTML'    => $open . $close,
		'innerContent' => array_merge( array( $open ), array_fill( 0, count( $inner ), null ), array( $close ) ),
	);
};
$content = static function ( array $blocks ) {
	return implode( "\n\n", array_map( 'serialize_block', $blocks ) );
};
$card = static function ( $title, $tag, $icon, $x, $y ) {
	return array(
		'label'        => 'Item',
		'title'        => $title,
		'status_label' => 'Status',
		'tag'          => $tag,
		'icon'         => $icon,
		'avatar'       => '',
		'x'            => $x,
		'y'            => $y,
		'mx'           => 10,
		'my'           => 70,
	);
};

$solution = $content(
	array(
		$kit(
			'assetpanda/vertical-hero',
			array(
				'eyebrow'        => 'INVENTORY MANAGEMENT SOFTWARE',
				'title'          => "Inventory software\nthat keeps count",
				'title_accent'   => 'keeps count',
				'lede'           => 'Track stock, consumables and equipment across every site from one place.',
				'cta_label'      => 'Free Demo',
				'cta_url'        => '/get-a-demo/',
				'badges'         => array(
					array(
						'image' => $img( 'badge-a.png' ),
						'alt'   => 'GovRAMP authorized',
					),
					array(
						'image' => $img( 'badge-b.png' ),
						'alt'   => 'SOC 2 Type 2 certified',
					),
				),
				'dashboard'      => $img( 'dashboard.png' ),
				'person'         => $img( 'person.png' ),
				'cards'          => array(
					$card( 'Pallet Jack', 'Due Inspection', $img( 'icon-a.png' ), 52, 28 ),
					$card( 'Barcode Scanner', 'Ready', $img( 'icon-b.png' ), 55, 55 ),
					$card( 'Laptop Cart', 'In Service', $img( 'icon-c.png' ), 74, 47 ),
				),
				'features_style' => 'band',
			)
		),
		$kit(
			'assetpanda/trust-strip',
			array(
				'label' => 'Trusted by inventory teams nationwide',
				'items' => array(
					array(
						'image' => $img( 'logo-a.png' ),
						'alt'   => 'Logo A',
					),
					array(
						'image' => $img( 'logo-b.png' ),
						'alt'   => 'Logo B',
					),
					array(
						'image' => $img( 'logo-c.png' ),
						'alt'   => 'Logo C',
					),
				),
			)
		),
		$leaf( 'core/heading', '<h2 class="wp-block-heading">Why teams switch from spreadsheets</h2>' ),
		$leaf( 'core/paragraph', '<p><strong>Spreadsheets</strong> break the moment two people edit them. Asset Panda keeps one live record per item.</p>' ),
		$kit(
			'assetpanda/stats',
			array(
				'eyebrow' => 'By the numbers',
				'items'   => array(
					array(
						'value'       => '98%',
						'label'       => 'Audit accuracy',
						'description' => 'Counts match the books after the first cycle.',
					),
					array(
						'value'       => '6 hrs',
						'label'       => 'Saved per week',
						'description' => 'No more chasing spreadsheet versions.',
					),
					array(
						'value'       => '30 days',
						'label'       => 'To go live',
						'description' => 'Import, tag and start scanning.',
					),
				),
			)
		),
		$kit(
			'assetpanda/faq',
			array(
				'title' => 'Frequently asked questions',
				'items' => array(
					array(
						'q' => 'Can I import my spreadsheet?',
						'a' => 'Yes. Map your columns once and import.',
					),
					array(
						'q' => 'Does it work offline?',
						'a' => "The mobile app scans offline\nand syncs when you are back online.",
					),
				),
			)
		),
		$kit(
			'assetpanda/demo-cta',
			array(
				'title'     => 'See inventory tracking in action',
				'body'      => 'A 20-minute walkthrough with your own items.',
				'cta_label' => 'Book a demo',
				'cta_url'   => '/get-a-demo/',
			)
		),
	)
);

$product = $content(
	array(
		$kit(
			'assetpanda/vertical-hero',
			array(
				'eyebrow'  => 'LOCAL GOVERNMENT ASSET MANAGEMENT SOFTWARE',
				'title'    => 'Every county asset, accounted for',
				'lede'     => 'From parks equipment to IT, one record per asset.',
				'features' => array(
					array(
						'label' => 'GASB 34 reporting',
						'icon'  => $img( 'icon-a.png' ),
					),
					array(
						'label' => 'Audit trails',
						'icon'  => $img( 'icon-b.png' ),
					),
				),
			)
		),
		$kit(
			'assetpanda/link-cards',
			array(
				'title' => 'Built for every department',
				'items' => array(
					array(
						'title'      => 'Parks',
						'body'       => 'Mowers, trucks and tools.',
						'link_label' => 'Learn more',
						'url'        => '/solutions/government/',
						'image'      => $img( 'card-a.png' ),
					),
					array(
						'title'      => 'IT',
						'body'       => 'Laptops and phones.',
						'link_label' => 'Learn more',
						'url'        => '/solutions/it-asset-tracking/',
						'image'      => $img( 'card-b.png' ),
					),
				),
			)
		),
		$kit(
			'assetpanda/faq',
			array(
				'title' => 'Questions from county teams',
				'items' => array(
					array(
						'q' => 'Is it GovRAMP authorized?',
						'a' => 'Yes.',
					),
				),
			)
		),
	)
);

$blog = $content(
	array(
		$leaf( 'core/paragraph', '<p>Asset tracking is how an organisation knows <em>what</em> it owns and <a href="/solutions/asset-management/">where it is</a>.</p>' ),
		$leaf( 'core/heading', '<h2 class="wp-block-heading">What is asset tracking?</h2>' ),
		$leaf( 'core/paragraph', '<p>It records every asset with a tag, a location and a history.</p>' ),
		$wrap(
			'core/list',
			'<ul class="wp-block-list">',
			array(
				$leaf( 'core/list-item', '<li>Tag every asset</li>' ),
				$leaf( 'core/list-item', '<li>Scan on the move</li>' ),
				$leaf( 'core/list-item', '<li>Report in real time</li>' ),
			),
			'</ul>'
		),
		$leaf( 'core/image', '<figure class="wp-block-image size-large"><img src="' . esc_url( $img( 'dashboard.png' ) ) . '" alt="The asset dashboard"/></figure>', array( 'sizeSlug' => 'large' ) ),
		$wrap(
			'core/group',
			'<div class="wp-block-group">',
			array(
				$wrap(
					'core/buttons',
					'<div class="wp-block-buttons">',
					array( $leaf( 'core/button', '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/get-a-demo/">Book a demo</a></div>' ) ),
					'</div>'
				),
			),
			'</div>',
			array( 'layout' => array( 'type' => 'constrained' ) )
		),
		$leaf(
			'core/embed',
			'<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">' . "\n" . 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' . "\n" . '</div></figure>',
			array(
				'url'              => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'type'             => 'video',
				'providerNameSlug' => 'youtube',
				'responsive'       => true,
				'className'        => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
			)
		),
		$leaf( 'core/table', '<figure class="wp-block-table"><table><tbody><tr><td>Tag</td><td>Scan</td></tr></tbody></table></figure>' ),
		$kit(
			'assetpanda/article-cta',
			array(
				'title' => 'Track every asset in one place',
				'body'  => 'See Asset Panda with your own data.',
			)
		),
	)
);

$compare = $content(
	array(
		$kit(
			'assetpanda/page-toc',
			array(
				'items' => array(
					array(
						'label'  => 'Quick comparison',
						'anchor' => 'quick',
					),
					array(
						'label'  => 'Verdict',
						'anchor' => 'verdict',
					),
				),
			)
		),
		$leaf( 'core/paragraph', '<p>Asset Panda and EZO both track assets; here is how they differ.</p>' ),
		$kit(
			'assetpanda/compare-table',
			array(
				'before_title' => 'EZO',
				'rows'         => array(
					array(
						'label'  => 'Configurability',
						'after'  => 'Unlimited custom fields',
						'before' => 'Limited',
						'winner' => 'Asset Panda',
					),
					array(
						'label'  => 'Mobile app',
						'after'  => 'Offline scanning',
						'before' => 'Online only',
						'winner' => 'Asset Panda',
					),
				),
			)
		),
		$wrap(
			'core/group',
			'<div class="wp-block-group">',
			array(
				$leaf( 'core/image', '<figure class="wp-block-image size-full is-resized"><img src="' . esc_url( $img( 'badge-a.png' ) ) . '" alt="Badge A"/></figure>', array( 'sizeSlug' => 'full' ) ),
				$leaf( 'core/image', '<figure class="wp-block-image size-full is-resized"><img src="' . esc_url( $img( 'badge-b.png' ) ) . '" alt="Badge B"/></figure>', array( 'sizeSlug' => 'full' ) ),
			),
			'</div>',
			array(
				'layout' => array(
					'type'           => 'flex',
					'flexWrap'       => 'nowrap',
				),
			)
		),
		$kit(
			'assetpanda/article-cta',
			array(
				'title' => 'See Asset Panda in Action',
				'body'  => 'Compare it on your own data.',
			)
		),
	)
);

$make = static function ( array $args, $template = 'page-no-title' ) {
	$id = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'insert failed: ' . $id->get_error_message() . "\n" );
		exit( 3 );
	}
	if ( $template ) {
		update_post_meta( $id, '_wp_page_template', $template );
	}
	return $id;
};

$solutions_parent = get_page_by_path( 'solutions' );
$products_parent  = get_page_by_path( 'products' );
$ids              = array();
$ids['solution']  = $make(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Inventory Management Software',
		'post_name'    => 'inventory-management',
		'post_parent'  => $solutions_parent ? $solutions_parent->ID : 0,
		'post_content' => $solution,
	)
);
$ids['product']   = $make(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Asset Panda for Local Government',
		'post_name'    => 'asset-panda-for-local-government',
		'post_parent'  => $products_parent ? $products_parent->ID : 0,
		'post_content' => $product,
	)
);
$ids['blog']      = $make(
	array(
		'post_type'    => 'resource',
		'post_status'  => 'publish',
		'post_title'   => 'What Is Asset Tracking?',
		'post_name'    => 'what-is-asset-tracking',
		'post_excerpt' => 'A short guide to asset tracking.',
		'post_content' => $blog,
	),
	''
);
$ids['compare']   = $make(
	array(
		'post_type'    => 'resource',
		'post_status'  => 'publish',
		'post_title'   => 'Asset Panda vs EZO',
		'post_name'    => 'asset-panda-vs-ezo',
		'post_content' => $compare,
	),
	''
);
wp_set_object_terms( $ids['blog'], 'blog', 'resource-types' );
wp_set_object_terms( $ids['compare'], 'compare', 'resource-types' );
if ( taxonomy_exists( 'resource-industry' ) ) {
	$edu = term_exists( 'education', 'resource-industry' );
	if ( ! $edu ) {
		$edu = wp_insert_term( 'Education', 'resource-industry', array( 'slug' => 'education' ) );
	}
	if ( ! is_wp_error( $edu ) ) {
		wp_set_object_terms( $ids['blog'], array( (int) $edu['term_id'] ), 'resource-industry' );
	}
}
update_post_meta( $ids['blog'], '_ap_featured_image_url', $img( 'card-a.png' ) );
update_post_meta( $ids['blog'], '_ap_featured_image_alt', 'Asset tags on a shelf' );

// Users. Passwords are random and never printed; the browser uses the cookies below.
$users = array();
foreach ( array(
	'admin'      => 'administrator',
	'editor'     => 'editor',
	'subscriber' => 'subscriber',
) as $slug => $role ) {
	$id = wp_insert_user(
		array(
			'user_login' => 'ap_tl_' . $slug,
			'user_email' => 'ap-tl-' . $slug . '@example.test',
			'user_pass'  => wp_generate_password( 32, true, true ),
			'role'       => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, 'user failed: ' . $id->get_error_message() . "\n" );
		exit( 4 );
	}
	$users[ $slug ] = $id;
	// No welcome guide in the block editor screenshots.
	update_user_meta( $id, $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences', array( 'core/edit-post' => array( 'welcomeGuide' => false ), 'core' => array( 'enableChoosePatternModal' => false ), '_modified' => gmdate( 'c' ) ) );
}

$session = array(
	'host'    => 'asset-panda-local.local',
	'ids'     => $ids,
	'users'   => $users,
	'cookies' => array(),
);
$expire = time() + 6 * HOUR_IN_SECONDS;
foreach ( $users as $slug => $id ) {
	$session['cookies'][ $slug ] = array(
		array(
			'name'  => AUTH_COOKIE,
			'value' => wp_generate_auth_cookie( $id, $expire, 'auth' ),
		),
		array(
			'name'  => LOGGED_IN_COOKIE,
			'value' => wp_generate_auth_cookie( $id, $expire, 'logged_in' ),
		),
	);
}
file_put_contents( $out . '/session.json', wp_json_encode( $session ) );
echo 'fixtures: ' . wp_json_encode( $ids ) . "\nusers: " . wp_json_encode( $users ) . "\n";
