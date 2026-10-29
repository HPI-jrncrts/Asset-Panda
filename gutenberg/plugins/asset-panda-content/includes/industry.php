<?php
/**
 * Industries (1.3.0): live's second resource taxonomy.
 *
 * www.assetpanda.com files resources under two kinds of category: the blog's
 * topics (Accounting & Finance, Fixed Assets, …: WordPress `category` here)
 * and industries (Education, Banking & Finance, Legal, …), which the Resource
 * Center, Case Studies, Success Stories, White Papers and Webinars filter by
 * (/resource-center/filter/search/<industry>/category/). The import only
 * brought the topics, so this registers `resource-industry` and fills it once
 * from data/industries.json (read from live's industry listings): add-only,
 * matched by resource slug, never removing a term an editor set.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Seed version: bump to re-apply data/industries.json (add-only). */
define( 'AP_CONTENT_INDUSTRY_SEED', '1' );

/**
 * Live's industries in its tab order: slug → name.
 *
 * @return array<string, string>
 */
function ap_content_industry_defaults() {
	return (array) apply_filters(
		'ap_content_industries',
		array(
			'education'              => 'Education',
			'construction'           => 'Construction',
			'healthcare'             => 'Healthcare',
			'banking-finance'        => 'Banking & Finance',
			'first-responders'       => 'First Responders',
			'government'             => 'Government',
			'non-profit'             => 'Non-Profit',
			'religious-institutions' => 'Religious Institutions',
			'manufacturing'          => 'Manufacturing',
			'oil-and-gas'            => 'Oil & Gas',
			'transportation'         => 'Transportation',
			'legal'                  => 'Legal',
			'hotels'                 => 'Hotels',
			'real-estate'            => 'Real Estate',
		)
	);
}

add_action(
	'init',
	function () {
		if ( taxonomy_exists( 'resource-industry' ) ) {
			return;
		}
		register_taxonomy(
			'resource-industry',
			array( 'resource' ),
			array(
				'labels'            => array(
					'name'          => 'Industries',
					'singular_name' => 'Industry',
					'menu_name'     => 'Industries',
					'all_items'     => 'All Industries',
					'edit_item'     => 'Edit Industry',
					'add_new_item'  => 'Add New Industry',
					'search_items'  => 'Search Industries',
				),
				'public'            => true,
				'publicly_queryable' => false,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'resource-industry',
				'query_var'         => false,
				'rewrite'           => false,
			)
		);
	},
	6
);

/** Creates the industries and applies the seed, once per seed version. */
function ap_content_industry_seed() {
	if ( ! taxonomy_exists( 'resource-industry' ) || ! post_type_exists( 'resource' ) ) {
		return;
	}
	// One request at a time.
	if ( get_transient( 'ap_content_industry_seeding' ) ) {
		return;
	}
	set_transient( 'ap_content_industry_seeding', 1, 120 );
	$report = array(
		'terms'    => 0,
		'resources' => 0,
		'added'    => 0,
		'missing'  => array(),
	);
	try {
		$ids = array();
		foreach ( ap_content_industry_defaults() as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'resource-industry' );
			if ( ! $term ) {
				$made = wp_insert_term( $name, 'resource-industry', array( 'slug' => $slug ) );
				if ( is_wp_error( $made ) ) {
					continue;
				}
				++$report['terms'];
				$ids[ $slug ] = (int) $made['term_id'];
			} else {
				$ids[ $slug ] = (int) $term->term_id;
			}
		}
		$file = AP_CONTENT_DIR . '/data/industries.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$rows = is_array( $data ) && isset( $data['resources'] ) && is_array( $data['resources'] ) ? $data['resources'] : array();
		foreach ( $rows as $slug => $industries ) {
			$id = ap_content_find_by_slug( 'resource', sanitize_title( (string) $slug ) );
			if ( ! $id ) {
				$report['missing'][] = (string) $slug;
				continue;
			}
			$have = wp_get_object_terms( $id, 'resource-industry', array( 'fields' => 'ids' ) );
			$have = is_wp_error( $have ) ? array() : array_map( 'intval', $have );
			$add  = array();
			foreach ( (array) $industries as $ind ) {
				if ( isset( $ids[ $ind ] ) && ! in_array( $ids[ $ind ], $have, true ) ) {
					$add[] = $ids[ $ind ];
				}
			}
			++$report['resources'];
			if ( $add ) {
				wp_set_object_terms( $id, $add, 'resource-industry', true );
				$report['added'] += count( $add );
			}
		}
		update_option( 'ap_content_industry_seed', AP_CONTENT_INDUSTRY_SEED, true );
		update_option( 'ap_content_industry_seed_report', $report + array( 'at' => gmdate( 'c' ) ), false );
		if ( function_exists( 'ap_content_search_bump' ) ) {
			ap_content_search_bump();
		}
	} catch ( \Throwable $e ) {
		update_option( 'ap_content_last_error', 'industry seed: ' . $e->getMessage(), false );
	}
	delete_transient( 'ap_content_industry_seeding' );
}

add_action(
	'init',
	function () {
		if ( get_option( 'ap_content_industry_seed' ) !== AP_CONTENT_INDUSTRY_SEED ) {
			ap_content_industry_seed();
		}
	},
	25
);

/**
 * A resource's industries in live's tab order.
 *
 * @param int $post_id Post.
 * @return WP_Term[]
 */
function ap_content_post_industries( $post_id ) {
	$terms = get_the_terms( $post_id, 'resource-industry' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	$order = array_flip( array_keys( ap_content_industry_defaults() ) );
	usort(
		$terms,
		function ( $a, $b ) use ( $order ) {
			$x = isset( $order[ $a->slug ] ) ? $order[ $a->slug ] : 99;
			$y = isset( $order[ $b->slug ] ) ? $order[ $b->slug ] : 99;
			return $x - $y;
		}
	);
	return $terms;
}
