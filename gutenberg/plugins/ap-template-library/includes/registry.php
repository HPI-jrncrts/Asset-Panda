<?php
/**
 * Template registry: the default templates, the ones an admin adds on the settings
 * screen, the `ap_tl_templates` filter, and how each template's reference post is found.
 *
 * A template looks like this:
 *
 *     'solution' => array(
 *         'label'         => 'Solution page',
 *         'group'         => 'solution',               // a key of ap_tl_groups()
 *         'description'   => '…',
 *         'source'        => array( 'path' => 'solutions/inventory-management' ),
 *         'post_type'     => 'page',                   // what "Send to Draft" creates ('' = the reference's type)
 *         'resource_type' => '',                       // resource-types slug for resources ('' = the reference's)
 *     )
 *
 * `source` finds the reference post, which must be published:
 *   array( 'id' => 123 )                                    a post ID
 *   array( 'path' => 'solutions/education' )                a page path (optional 'post_type')
 *   array( 'post_type' => 'resource', 'slug' => 'x',        a resource by slug. If it is gone, the newest
 *          'resource_type' => 'blog' )                      published resource of that type is used instead.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template groups (the library's filter tabs), in display order.
 *
 * @return array<string, string> group key => label.
 */
function ap_tl_groups() {
	$groups = array(
		'solution'    => __( 'Solution', 'ap-template-library' ),
		'product'     => __( 'Product (Who We Help)', 'ap-template-library' ),
		'hub'         => __( 'Industry hub', 'ap-template-library' ),
		'platform'    => __( 'Platform', 'ap-template-library' ),
		'integration' => __( 'Integration', 'ap-template-library' ),
		'landing'     => __( 'Landing page', 'ap-template-library' ),
		'blog'        => __( 'Resource: blog post', 'ap-template-library' ),
		'case-study'  => __( 'Resource: case study', 'ap-template-library' ),
		'compare'     => __( 'Resource: compare', 'ap-template-library' ),
		'resource'    => __( 'Resource: other', 'ap-template-library' ),
		'utility'     => __( 'Utility', 'ap-template-library' ),
		'other'       => __( 'Other', 'ap-template-library' ),
	);

	/**
	 * Filters the template groups shown as tabs in the library.
	 *
	 * @param array<string, string> $groups group key => label.
	 */
	return (array) apply_filters( 'ap_tl_groups', $groups );
}

/**
 * The default registry. Each reference is looked up by path or slug on the current site
 * (checked on assetpanda1dev on 2026-10-29).
 *
 * @return array<string, array>
 */
function ap_tl_default_templates() {
	$page = static function ( $label, $group, $path, $description ) {
		return array(
			'label'       => $label,
			'group'       => $group,
			'description' => $description,
			'source'      => array( 'path' => $path ),
			'post_type'   => 'page',
		);
	};
	$resource = static function ( $label, $group, $type, $slug, $description ) {
		return array(
			'label'         => $label,
			'group'         => $group,
			'description'   => $description,
			'source'        => array(
				'post_type'     => 'resource',
				'resource_type' => $type,
				'slug'          => $slug,
			),
			'post_type'     => 'resource',
			'resource_type' => $type,
		);
	};

	return array(
		'solution'       => $page( __( 'Solution page', 'ap-template-library' ), 'solution', 'solutions/inventory-management', __( 'A /solutions/ feature page: hero with item cards, problem photos, capability rows, dashboard, proof and a demo form.', 'ap-template-library' ) ),
		'product'        => $page( __( 'Product / Who We Help page', 'ap-template-library' ), 'product', 'products/asset-panda-for-local-government', __( 'A vertical product page (/products/asset-panda-for-…/): composed hero, trust strip, problem callouts, sticky feature list, stories, FAQ and demo form.', 'ap-template-library' ) ),
		'industry-hub'   => $page( __( 'Industry hub', 'ap-template-library' ), 'hub', 'solutions/education', __( 'An industry hub (/solutions/<industry>/): hub hero, sub-industry link cards, problem scene, capabilities and stories.', 'ap-template-library' ) ),
		'industry-sub'   => $page( __( 'Industry sub-page', 'ap-template-library' ), 'hub', 'solutions/healthcare/biomedical', __( 'A page under an industry hub, for example /solutions/healthcare/biomedical/.', 'ap-template-library' ) ),
		'platform-aip'   => $page( __( 'Platform page: Asset Intelligence Platform', 'ap-template-library' ), 'platform', 'asset-intelligence-platform', __( 'The platform overview layout: orbit hero, tab showcase, continuity columns, comparison and FAQ.', 'ap-template-library' ) ),
		'platform-ursa'  => $page( __( 'Platform page: Ursa AI', 'ap-template-library' ), 'platform', 'ursa-ai', __( 'The UrsaAI product layout.', 'ap-template-library' ) ),
		'integration'    => $page( __( 'Integration detail page', 'ap-template-library' ), 'integration', 'integrations/slack', __( 'One integration (/integrations/<name>/): title bar, detail body and more integrations.', 'ap-template-library' ) ),
		'landing-ads'    => $page( __( 'Ads landing page', 'ap-template-library' ), 'landing', 'solutions/it-asset-management-ga', __( 'A paid-traffic landing page (the "-ga" pages).', 'ap-template-library' ) ),
		'landing-guide'  => $page( __( 'Guide landing page', 'ap-template-library' ), 'landing', 'solutions/government/a-practical-guide-to-securing-mission-critical-equipment-for-government-agencies', __( 'A long-form guide page published under an industry hub.', 'ap-template-library' ) ),
		'blog'           => $resource( __( 'Blog post', 'ap-template-library' ), 'blog', 'blog', 'what-is-asset-tracking', __( 'A Resource Center blog post: headings, paragraphs and lists, rendered by the 2026 blog template.', 'ap-template-library' ) ),
		'case-study'     => $resource( __( 'Case study', 'ap-template-library' ), 'case-study', 'case-studies', 'texas-am-university-standardizes-asset-tracking-across-departments-with-asset-panda-on-aws', __( 'A Resource Center case study.', 'ap-template-library' ) ),
		'compare'        => $resource( __( 'Compare: Asset Panda vs X', 'ap-template-library' ), 'compare', 'compare', 'asset-panda-vs-ezo', __( 'A head-to-head comparison article with table of contents, compare table and CTA boxes.', 'ap-template-library' ) ),
		'compare-guide'  => $resource( __( 'Compare: best-of guide', 'ap-template-library' ), 'compare', 'compare', 'best-asset-tracking-software-top-platforms-compared', __( 'A ranked "best X software" comparison guide.', 'ap-template-library' ) ),
		'success-story'  => $resource( __( 'Success story', 'ap-template-library' ), 'resource', 'success-stories', 'university-of-connecticut-tracks-assets-across-seven-locations-with-asset-panda', __( 'A Resource Center success story.', 'ap-template-library' ) ),
		'white-paper'    => $resource( __( 'White paper', 'ap-template-library' ), 'resource', 'white-papers', '10-point-checklist-for-audit-proof-asset-management', __( 'A Resource Center white paper.', 'ap-template-library' ) ),
		'webinar'        => $resource( __( 'Webinar', 'ap-template-library' ), 'resource', 'webinars', 'asset-panda-2-0-power-hour-audit', __( 'A Resource Center webinar page.', 'ap-template-library' ) ),
		// Partners: the new Partners page. Resolves by path; shows "Reference missing" until /partners/ is published.
		'partners'       => $page( __( 'Partners page', 'ap-template-library' ), 'utility', 'partners', __( 'The Partners & Resellers page layout.', 'ap-template-library' ) ),
		'thank-you'      => $page( __( 'Thank-you page', 'ap-template-library' ), 'utility', 'thanks-for-your-new-demo-request', __( 'A form thank-you page.', 'ap-template-library' ) ),
		'company'        => $page( __( 'Company page', 'ap-template-library' ), 'utility', 'our-company', __( 'The About Asset Panda layout.', 'ap-template-library' ) ),
		'legal'          => $page( __( 'Legal / policy page', 'ap-template-library' ), 'utility', 'privacy-policy', __( 'A long text page such as the privacy policy.', 'ap-template-library' ) ),
	);
}

/**
 * The stored settings, with every key present.
 *
 * @return array{custom: array, hidden: string[], thumbs: array<string,int>}
 */
function ap_tl_settings() {
	$raw = get_option( AP_TL_OPTION, array() );
	$raw = is_array( $raw ) ? $raw : array();

	return array(
		'custom' => isset( $raw['custom'] ) && is_array( $raw['custom'] ) ? $raw['custom'] : array(),
		'hidden' => isset( $raw['hidden'] ) && is_array( $raw['hidden'] ) ? array_values( array_map( 'strval', $raw['hidden'] ) ) : array(),
		'thumbs' => isset( $raw['thumbs'] ) && is_array( $raw['thumbs'] ) ? array_map( 'absint', $raw['thumbs'] ) : array(),
	);
}

/**
 * Saves the settings.
 *
 * @param array $settings Settings in the shape ap_tl_settings() returns.
 */
function ap_tl_save_settings( array $settings ) {
	update_option(
		AP_TL_OPTION,
		array(
			'custom' => array_values( (array) ( $settings['custom'] ?? array() ) ),
			'hidden' => array_values( array_unique( array_map( 'strval', (array) ( $settings['hidden'] ?? array() ) ) ) ),
			'thumbs' => array_filter( array_map( 'absint', (array) ( $settings['thumbs'] ?? array() ) ) ),
		),
		false
	);
	ap_tl_templates_reset();
}

/**
 * Brings one template into the shape every screen relies on. Returns null when it cannot be used.
 *
 * @param string $key      Template key.
 * @param mixed  $template Raw template.
 * @param string $origin   default | custom | code.
 * @return array|null
 */
function ap_tl_normalize_template( $key, $template, $origin ) {
	if ( ! is_array( $template ) ) {
		return null;
	}
	$key = sanitize_key( (string) $key );
	if ( '' === $key || empty( $template['source'] ) || ! is_array( $template['source'] ) ) {
		return null;
	}
	$groups = ap_tl_groups();
	$group  = isset( $template['group'] ) ? sanitize_key( (string) $template['group'] ) : 'other';
	if ( ! isset( $groups[ $group ] ) ) {
		$group = 'other';
	}
	$label = isset( $template['label'] ) ? trim( wp_strip_all_tags( (string) $template['label'] ) ) : '';

	return array(
		'key'           => $key,
		'label'         => '' !== $label ? $label : $key,
		'group'         => $group,
		'group_label'   => $groups[ $group ],
		'description'   => isset( $template['description'] ) ? wp_strip_all_tags( (string) $template['description'] ) : '',
		'source'        => $template['source'],
		'post_type'     => isset( $template['post_type'] ) ? sanitize_key( (string) $template['post_type'] ) : '',
		'resource_type' => isset( $template['resource_type'] ) ? sanitize_title( (string) $template['resource_type'] ) : '',
		'origin'        => $origin,
	);
}

/**
 * Per-request cache of the registry.
 *
 * @param bool $reset Drop the cache.
 * @return array|null
 */
function ap_tl_templates_cache( $reset = false ) {
	static $cache = null;
	if ( $reset ) {
		$cache = null;
		return null;
	}
	if ( null === $cache ) {
		$cache = ap_tl_build_registry();
	}
	return $cache;
}

/** Drops the per-request registry cache (after the settings change). */
function ap_tl_templates_reset() {
	ap_tl_templates_cache( true );
}

/**
 * Builds the registry: defaults, plus admin-added templates, plus the `ap_tl_templates` filter.
 *
 * @return array{all: array<string,array>, enabled: array<string,array>}
 */
function ap_tl_build_registry() {
	$settings = ap_tl_settings();
	$raw      = array();
	$origins  = array();

	foreach ( ap_tl_default_templates() as $key => $template ) {
		$raw[ $key ]     = $template;
		$origins[ $key ] = 'default';
	}
	foreach ( $settings['custom'] as $custom ) {
		if ( ! is_array( $custom ) || empty( $custom['key'] ) || empty( $custom['source_id'] ) ) {
			continue;
		}
		$key             = sanitize_key( (string) $custom['key'] );
		$raw[ $key ]     = array(
			'label'         => $custom['label'] ?? $key,
			'group'         => $custom['group'] ?? 'other',
			'description'   => $custom['description'] ?? '',
			'source'        => array( 'id' => absint( $custom['source_id'] ) ),
			'post_type'     => $custom['post_type'] ?? '',
			'resource_type' => $custom['resource_type'] ?? '',
		);
		$origins[ $key ] = 'custom';
	}
	$hidden = array_flip( $settings['hidden'] );

	/**
	 * Filters the template registry before it is shown.
	 *
	 * Add, change or remove templates here. Keys are template keys; values use the
	 * shape documented at the top of includes/registry.php. Templates an admin hid on
	 * the settings screen are not passed in.
	 *
	 * @param array<string, array> $templates Templates, keyed by template key.
	 */
	$filtered = apply_filters( 'ap_tl_templates', array_diff_key( $raw, $hidden ) );
	$filtered = is_array( $filtered ) ? $filtered : array();

	$all     = array();
	$enabled = array();
	foreach ( $raw as $key => $template ) {
		$norm = ap_tl_normalize_template( $key, $template, $origins[ $key ] );
		if ( $norm ) {
			$norm['hidden']       = isset( $hidden[ $key ] );
			$norm['thumbnail_id'] = isset( $settings['thumbs'][ $norm['key'] ] ) ? (int) $settings['thumbs'][ $norm['key'] ] : 0;
			$all[ $norm['key'] ]  = $norm;
		}
	}
	foreach ( $filtered as $key => $template ) {
		$origin = isset( $origins[ $key ] ) ? $origins[ $key ] : 'code';
		$norm   = ap_tl_normalize_template( $key, $template, $origin );
		if ( ! $norm ) {
			continue;
		}
		$norm['hidden']           = false;
		$norm['thumbnail_id']     = isset( $settings['thumbs'][ $norm['key'] ] ) ? (int) $settings['thumbs'][ $norm['key'] ] : 0;
		$enabled[ $norm['key'] ]  = $norm;
		if ( 'code' === $origin ) {
			$all[ $norm['key'] ] = $norm;
		}
	}

	return array(
		'all'     => $all,
		'enabled' => $enabled,
	);
}

/**
 * The templates shown in the library (enabled, after the filter), keyed by template key.
 *
 * @return array<string, array>
 */
function ap_tl_templates() {
	$registry = ap_tl_templates_cache();
	return $registry['enabled'];
}

/**
 * Every known template, including hidden defaults (for the settings screen).
 *
 * @return array<string, array>
 */
function ap_tl_all_templates() {
	$registry = ap_tl_templates_cache();
	return $registry['all'];
}

/**
 * One enabled template.
 *
 * @param string $key Template key.
 * @return array|null
 */
function ap_tl_get_template( $key ) {
	$templates = ap_tl_templates();
	$key       = sanitize_key( (string) $key );
	return isset( $templates[ $key ] ) ? $templates[ $key ] : null;
}

/**
 * Post types a reference may be (and a template may create).
 *
 * @return string[]
 */
function ap_tl_source_post_types() {
	$types = array( 'page' );
	foreach ( array( 'resource', 'post', 'newsroom' ) as $maybe ) {
		if ( post_type_exists( $maybe ) ) {
			$types[] = $maybe;
		}
	}

	/**
	 * Filters the post types that can be a template reference.
	 *
	 * @param string[] $types Post type names.
	 */
	return array_values( array_filter( (array) apply_filters( 'ap_tl_source_post_types', $types ), 'post_type_exists' ) );
}

/**
 * Finds a template's reference post. Only published posts count.
 *
 * @param array $source Template source.
 * @return WP_Post|null
 */
function ap_tl_resolve_source( $source ) {
	if ( ! is_array( $source ) ) {
		return null;
	}
	$allowed = ap_tl_source_post_types();
	$usable  = static function ( $post ) use ( $allowed ) {
		return $post instanceof WP_Post && 'publish' === $post->post_status && in_array( $post->post_type, $allowed, true );
	};

	if ( ! empty( $source['id'] ) ) {
		$post = get_post( absint( $source['id'] ) );
		return $usable( $post ) ? $post : null;
	}

	if ( ! empty( $source['path'] ) ) {
		$types = ! empty( $source['post_type'] ) ? array_map( 'sanitize_key', (array) $source['post_type'] ) : array( 'page' );
		$post  = get_page_by_path( trim( (string) $source['path'], '/' ), OBJECT, $types );
		if ( $usable( $post ) ) {
			return $post;
		}
	}

	$type = ! empty( $source['post_type'] ) && is_string( $source['post_type'] ) ? sanitize_key( $source['post_type'] ) : '';
	if ( '' !== $type && ! empty( $source['slug'] ) && post_type_exists( $type ) ) {
		$found = get_posts(
			array(
				'name'             => sanitize_title( (string) $source['slug'] ),
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => true,
			)
		);
		if ( $found && $usable( $found[0] ) ) {
			return $found[0];
		}
	}

	// Fallback: the newest published resource of that type, preferring one built from blocks.
	if ( '' !== $type && ! empty( $source['resource_type'] ) && post_type_exists( $type ) && taxonomy_exists( 'resource-types' ) ) {
		$found = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 5,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'        => array(
					array(
						'taxonomy' => 'resource-types',
						'field'    => 'slug',
						'terms'    => sanitize_title( (string) $source['resource_type'] ),
					),
				),
			)
		);
		foreach ( $found as $post ) {
			if ( $usable( $post ) && has_blocks( $post->post_content ) ) {
				return $post;
			}
		}
		if ( $found && $usable( $found[0] ) ) {
			return $found[0];
		}
	}

	return null;
}

/**
 * Everything a screen needs about a template: its reference, what it creates, and whether it can be used.
 *
 * @param array $template Normalised template.
 * @return array{post: WP_Post|null, status: string, post_type: string, resource_type: string}
 */
function ap_tl_template_context( array $template ) {
	$post      = ap_tl_resolve_source( $template['source'] );
	$post_type = '' !== $template['post_type'] ? $template['post_type'] : ( $post ? $post->post_type : 'page' );
	$status    = 'ok';
	if ( ! $post ) {
		$status = 'missing';
	} elseif ( ! post_type_exists( $post_type ) ) {
		$status = 'no_post_type';
	}

	$resource_type = $template['resource_type'];
	if ( '' === $resource_type && $post && taxonomy_exists( 'resource-types' ) && is_object_in_taxonomy( $post->post_type, 'resource-types' ) ) {
		$terms = get_the_terms( $post, 'resource-types' );
		if ( is_array( $terms ) && $terms ) {
			$resource_type = $terms[0]->slug;
		}
	}

	return array(
		'post'          => $post,
		'status'        => $status,
		'post_type'     => $post_type,
		'resource_type' => $resource_type,
	);
}

/**
 * Whether the current user may create posts of this type.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function ap_tl_user_can_create( $post_type ) {
	$object = get_post_type_object( $post_type );
	if ( ! $object ) {
		return false;
	}
	return current_user_can( 'edit_pages' ) && current_user_can( $object->cap->create_posts );
}

/**
 * Thumbnail URL for a template card: the admin's image, else the reference's featured image.
 * An empty string means "draw the placeholder".
 *
 * @param array        $template Normalised template.
 * @param WP_Post|null $post     Reference post.
 * @return string
 */
function ap_tl_thumbnail_url( array $template, $post ) {
	if ( ! empty( $template['thumbnail_id'] ) ) {
		$url = wp_get_attachment_image_url( (int) $template['thumbnail_id'], 'medium_large' );
		if ( $url ) {
			return $url;
		}
	}
	if ( $post ) {
		$url = get_the_post_thumbnail_url( $post, 'medium_large' );
		if ( $url ) {
			return $url;
		}
		// Imported resources keep their featured image as a URL (asset-panda-content).
		$meta = (string) get_post_meta( $post->ID, '_ap_featured_image_url', true );
		if ( '' !== $meta && preg_match( '#^(https?:)?//|^/#i', $meta ) ) {
			return esc_url_raw( $meta );
		}
	}
	return '';
}
