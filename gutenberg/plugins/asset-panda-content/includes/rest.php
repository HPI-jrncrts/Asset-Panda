<?php
/**
 * REST: ap-content/v1 — contract, lookup, items, archive-seo.
 * Writes go through core wp/v2 (resource, newsroom, pages); see CONTRACT.md.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Permission: logged-in user who can edit posts. */
function ap_content_rest_can_read() {
	return current_user_can( 'edit_posts' ) ? true : new WP_Error( 'rest_forbidden', 'Sorry, you need the edit_posts capability.', array( 'status' => is_user_logged_in() ? 403 : 401 ) );
}

/** Permission: archive SEO (site-wide SEO storage) needs an Editor or Administrator. */
function ap_content_rest_can_seo() {
	return current_user_can( 'edit_others_posts' ) ? true : new WP_Error( 'rest_forbidden', 'Sorry, archive SEO needs an Editor or Administrator.', array( 'status' => is_user_logged_in() ? 403 : 401 ) );
}

/**
 * Wraps a handler so a Throwable becomes a JSON error, never a 500 page.
 *
 * @param callable $fn Handler.
 * @return callable
 */
function ap_content_rest_safe( $fn ) {
	return function ( $request ) use ( $fn ) {
		try {
			return call_user_func( $fn, $request );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'ap_content_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	};
}

/**
 * Summary of one item for lookup / items.
 *
 * @param WP_Post $post Post.
 * @return array
 */
function ap_content_item_summary( $post ) {
	$obj  = get_post_type_object( $post->post_type );
	$type = ap_content_primary_type( $post );
	return array(
		'id'           => (int) $post->ID,
		'type'         => $post->post_type,
		'rest_base'    => $obj && $obj->rest_base ? $obj->rest_base : $post->post_type,
		'status'       => $post->post_status,
		'slug'         => $post->post_name,
		'path'         => 'page' === $post->post_type ? get_page_uri( $post ) : '',
		'link'         => get_permalink( $post ),
		'template'     => 'page' === $post->post_type ? (string) get_page_template_slug( $post ) : '',
		'source_url'   => (string) get_post_meta( $post->ID, '_ap_source_url', true ),
		'source_id'    => (int) get_post_meta( $post->ID, '_ap_source_id', true ),
		'primary_type' => $type ? $type->slug : '',
		'modified_gmt' => mysql_to_rfc3339( $post->post_modified_gmt ),
		'import_hash'  => (string) get_post_meta( $post->ID, '_ap_import_hash', true ),
		'kit'          => false !== strpos( (string) $post->post_content, '<!-- wp:assetpanda/' ),
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap-content/v1',
			'/contract',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'ap_content_rest_can_read',
				'callback'            => ap_content_rest_safe(
					function () {
						$terms = get_terms(
							array(
								'taxonomy'   => 'resource-types',
								'hide_empty' => false,
							)
						);
						return array(
							'plugin_version'   => AP_CONTENT_VERSION,
							'contract_version' => AP_CONTENT_CONTRACT,
							'types'            => array(
								'resource' => array(
									'rest_base' => 'resource',
									'url'       => '/resource-center/<resource-type>/<slug>/',
								),
								'newsroom' => array(
									'rest_base' => 'newsroom',
									'url'       => '/pressroom/<slug>/',
								),
								'page'     => array(
									'rest_base' => 'pages',
									'templates' => array( 'page-article' ),
								),
							),
							'resource_types'   => is_wp_error( $terms ) ? array() : array_map(
								function ( $t ) {
									return array(
										'id'   => (int) $t->term_id,
										'slug' => $t->slug,
										'name' => $t->name,
										'link' => get_term_link( $t ),
									);
								},
								$terms
							),
							'yoast_meta'       => ap_content_yoast_meta_keys(),
							'own_meta'         => array_merge( array_keys( ap_content_own_meta_keys() ), array( '_ap_jsonld' ) ),
							'gate_types'       => array( 'resource', 'page' ),
							'yoast_active'     => defined( 'WPSEO_VERSION' ),
							// 1.3.0: what the deploy check reads.
							'industry_seed'    => get_option( 'ap_content_industry_seed_report' ),
							'search_index'     => function_exists( 'ap_content_search_index_url' ) ? ap_content_search_index_url() : '',
							'kit_components'   => function_exists( 'ap_content_kit_ready' ) ? array(
								'resource-hero'    => ap_content_kit_ready( 'resource-hero', 'resource-search' ),
								'resource-cards'   => ap_content_kit_ready( 'resource-cards' ),
								'resource-header'  => ap_content_kit_ready( 'resource-header' ),
								'resource-summary' => ap_content_kit_ready( 'resource-summary' ),
							) : array(),
							'design'           => array(
								'snippet6_blocks'      => WP_Block_Type_Registry::get_instance()->is_registered( 'ap26/post-header' ),
								'snippet8_design_pass' => function_exists( 'ap26x_state' ),
								'page_article'         => function_exists( 'register_block_template' ),
							),
						);
					}
				),
			)
		);

		register_rest_route(
			'ap-content/v1',
			'/lookup',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'ap_content_rest_can_read',
				'args'                => array(
					'source_url' => array( 'type' => 'string' ),
					'type'       => array(
						'type' => 'string',
						'enum' => array( 'resource', 'newsroom', 'page', 'post' ),
					),
					'slug'       => array( 'type' => 'string' ),
					'path'       => array( 'type' => 'string' ),
				),
				'callback'            => ap_content_rest_safe(
					function ( WP_REST_Request $request ) {
						$id = 0;
						if ( $request['source_url'] ) {
							$id = ap_content_find_by_source_url( $request['source_url'] );
						} elseif ( 'page' === $request['type'] && ( $request['path'] || $request['slug'] ) ) {
							$path = trim( (string) ( $request['path'] ? $request['path'] : $request['slug'] ), '/' );
							$page = get_page_by_path( $path, OBJECT, array( 'page' ) );
							$id   = ( $page && 'trash' !== $page->post_status ) ? (int) $page->ID : 0;
						} elseif ( $request['type'] && $request['slug'] ) {
							$id = ap_content_find_by_slug( $request['type'], sanitize_title( $request['slug'] ) );
						} else {
							return new WP_Error( 'ap_content_bad_lookup', 'Give source_url, or type and slug (pages: type=page&path=).', array( 'status' => 400 ) );
						}
						$post = $id ? get_post( $id ) : null;
						if ( ! $post ) {
							return array( 'found' => false );
						}
						return array( 'found' => true ) + ap_content_item_summary( $post );
					}
				),
			)
		);

		register_rest_route(
			'ap-content/v1',
			'/items',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'ap_content_rest_can_read',
				'args'                => array(
					'type'     => array(
						'type'     => 'string',
						'enum'     => array( 'resource', 'newsroom', 'page' ),
						'required' => true,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 100,
						'minimum' => 1,
						'maximum' => 500,
					),
				),
				'callback'            => ap_content_rest_safe(
					function ( WP_REST_Request $request ) {
						$q        = new WP_Query(
							array(
								'post_type'              => $request['type'],
								'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
								'posts_per_page'         => (int) $request['per_page'],
								'paged'                  => (int) $request['page'],
								'orderby'                => 'ID',
								'order'                  => 'ASC',
								'ignore_sticky_posts'    => true,
								'suppress_filters'       => true,
								'update_post_term_cache' => true,
							)
						);
						$response = rest_ensure_response( array_map( 'ap_content_item_summary', $q->posts ) );
						$response->header( 'X-WP-Total', (string) (int) $q->found_posts );
						$response->header( 'X-WP-TotalPages', (string) (int) $q->max_num_pages );
						return $response;
					}
				),
			)
		);

		register_rest_route(
			'ap-content/v1',
			'/archive-seo',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => 'ap_content_rest_can_read',
					'args'                => array(
						'target' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
					'callback'            => ap_content_rest_safe(
						function ( WP_REST_Request $request ) {
							$t = ap_content_archive_target( $request['target'] );
							return is_wp_error( $t ) ? $t : array( 'target' => $request['target'] ) + ap_content_archive_seo_get( $t );
						}
					),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => 'ap_content_rest_can_seo',
					'args'                => array(
						'target'      => array(
							'type'     => 'string',
							'required' => true,
						),
						'title'       => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'canonical'   => array( 'type' => 'string' ),
						'noindex'     => array( 'type' => 'boolean' ),
						'heading'     => array( 'type' => 'string' ),
					),
					'callback'            => ap_content_rest_safe(
						function ( WP_REST_Request $request ) {
							$t = ap_content_archive_target( $request['target'] );
							if ( is_wp_error( $t ) ) {
								return $t;
							}
							$data = array();
							foreach ( array( 'title', 'description', 'canonical', 'noindex', 'heading' ) as $key ) {
								if ( $request->has_param( $key ) ) {
									$data[ $key ] = $request[ $key ];
								}
							}
							return array( 'target' => $request['target'] ) + ap_content_archive_seo_set( $t, $data );
						}
					),
				),
			)
		);
	}
);
