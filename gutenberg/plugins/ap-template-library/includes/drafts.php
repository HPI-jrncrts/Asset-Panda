<?php
/**
 * Creating the draft, the page settings around it, and saved builder progress.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Largest saved-progress state accepted, in bytes. */
const AP_TL_MAX_PROGRESS = 3000000;

/**
 * Taxonomies the builder offers for a post type: hierarchical, shown in the UI, assignable.
 *
 * @param string $post_type Post type.
 * @return WP_Taxonomy[] Keyed by taxonomy name.
 */
function ap_tl_taxonomies_for( $post_type ) {
	$out = array();
	foreach ( get_object_taxonomies( $post_type, 'objects' ) as $name => $taxonomy ) {
		if ( ! $taxonomy->hierarchical || ! $taxonomy->show_ui || in_array( $name, array( 'nav_menu', 'link_category', 'post_format', 'wp_pattern_category' ), true ) ) {
			continue;
		}
		$out[ $name ] = $taxonomy;
	}

	/**
	 * Filters the taxonomies offered in the builder's page settings.
	 *
	 * @param WP_Taxonomy[] $out       Taxonomies keyed by name.
	 * @param string        $post_type Post type being created.
	 */
	return (array) apply_filters( 'ap_tl_builder_taxonomies', $out, $post_type );
}

/**
 * Meta keys copied from the reference to the new draft (template choice, not content).
 *
 * @param array   $template  Template.
 * @param WP_Post $reference Reference post.
 * @return string[]
 */
function ap_tl_copy_meta_keys( array $template, WP_Post $reference ) {
	/**
	 * Filters the meta keys copied from the reference post to a new draft.
	 *
	 * @param string[] $keys      Meta keys. Default: the page template.
	 * @param array    $template  Template.
	 * @param WP_Post  $reference Reference post.
	 */
	return (array) apply_filters( 'ap_tl_copy_meta_keys', array( '_wp_page_template' ), $template, $reference );
}

/**
 * The reference's featured image: an attachment, or (imported resources) a URL in meta.
 *
 * @param WP_Post $post Reference post.
 * @return array{id: int, url: string, alt: string}
 */
function ap_tl_reference_featured( WP_Post $post ) {
	$id = (int) get_post_thumbnail_id( $post );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, 'medium' );
		return array(
			'id'  => $id,
			'url' => $url ? $url : (string) wp_get_attachment_url( $id ),
			'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
	$url = (string) get_post_meta( $post->ID, '_ap_featured_image_url', true );
	return array(
		'id'  => 0,
		'url' => '' !== $url ? esc_url_raw( $url ) : '',
		'alt' => (string) get_post_meta( $post->ID, '_ap_featured_image_alt', true ),
	);
}

/**
 * Page settings the builder shows (pre-filled from the reference) and the choices for them.
 *
 * @param array $template Template.
 * @param array $context  ap_tl_template_context() result (with a post).
 * @return array{page: array, settings: array}
 */
function ap_tl_page_settings( array $template, array $context ) {
	$ref       = $context['post'];
	$post_type = $context['post_type'];
	$object    = get_post_type_object( $post_type );

	$page = array(
		'title'    => html_entity_decode( get_the_title( $ref ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
		'slug'     => '',
		'parent'   => $ref->post_type === $post_type ? (int) $ref->post_parent : 0,
		'excerpt'  => (string) $ref->post_excerpt,
		'featured' => ap_tl_reference_featured( $ref ),
		'terms'    => new stdClass(),
	);

	$settings = array(
		'post_type'       => $post_type,
		'post_type_label' => $object ? $object->labels->singular_name : $post_type,
		'hierarchical'    => $object && $object->hierarchical,
		'excerpt'         => post_type_supports( $post_type, 'excerpt' ),
		'thumbnail'       => post_type_supports( $post_type, 'thumbnail' ),
		'parents'         => array(),
		'taxonomies'      => array(),
	);

	if ( $settings['hierarchical'] ) {
		$pages = get_pages(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish,draft,private',
				'sort_column' => 'menu_order,post_title',
				'number'      => 1000,
			)
		);
		$pages = is_array( $pages ) ? $pages : array();
		// Children listed under their parents (get_page_hierarchy() takes its argument by reference).
		foreach ( (array) get_page_hierarchy( $pages ) as $id => $slug ) {
			$p = get_post( $id );
			if ( ! $p ) {
				continue;
			}
			$settings['parents'][] = array(
				'id'    => (int) $id,
				'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, get_bloginfo( 'charset' ) ) . ( 'publish' !== $p->post_status ? ' (' . $p->post_status . ')' : '' ),
				'depth' => count( get_post_ancestors( $p ) ),
			);
		}
	}

	$terms = array();
	foreach ( ap_tl_taxonomies_for( $post_type ) as $name => $taxonomy ) {
		if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			continue;
		}
		$all = get_terms(
			array(
				'taxonomy'   => $name,
				'hide_empty' => false,
				'number'     => 500,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $all ) ) {
			continue;
		}
		$list = array();
		foreach ( $all as $term ) {
			$list[] = array(
				'id'     => (int) $term->term_id,
				'name'   => html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
			);
		}
		$settings['taxonomies'][] = array(
			'name'   => $name,
			'label'  => $taxonomy->labels->name,
			'single' => 'resource-types' === $name,
			'terms'  => $list,
		);

		$selected = array();
		if ( is_object_in_taxonomy( $ref->post_type, $name ) ) {
			$selected = wp_get_object_terms( $ref->ID, $name, array( 'fields' => 'ids' ) );
			$selected = is_wp_error( $selected ) ? array() : array_map( 'intval', $selected );
		}
		if ( 'resource-types' === $name && '' !== $context['resource_type'] ) {
			$term = get_term_by( 'slug', $context['resource_type'], $name );
			if ( $term ) {
				$selected = array( (int) $term->term_id );
			}
		}
		$terms[ $name ] = array_values( $selected );
	}
	$page['terms'] = (object) $terms;

	return array(
		'page'     => $page,
		'settings' => $settings,
	);
}

/**
 * Creates the draft. Never publishes.
 *
 * @param string $key     Template key.
 * @param array  $payload { page: {...}, blocks: [...] }.
 * @return array|WP_Error
 */
function ap_tl_create_draft( $key, array $payload ) {
	$template = ap_tl_get_template( $key );
	if ( ! $template ) {
		return new WP_Error( 'ap_tl_unknown_template', __( 'That template no longer exists.', 'ap-template-library' ), array( 'status' => 404 ) );
	}
	$context = ap_tl_template_context( $template );
	if ( 'ok' !== $context['status'] ) {
		return new WP_Error( 'ap_tl_reference_missing', __( 'The reference page for this template is missing, so a draft cannot be built from it.', 'ap-template-library' ), array( 'status' => 409 ) );
	}
	$post_type = $context['post_type'];
	if ( ! ap_tl_user_can_create( $post_type ) ) {
		return new WP_Error( 'ap_tl_forbidden', __( 'You are not allowed to create this kind of content.', 'ap-template-library' ), array( 'status' => 403 ) );
	}
	$reference = $context['post'];
	$page      = isset( $payload['page'] ) && is_array( $payload['page'] ) ? $payload['page'] : array();

	$title = isset( $page['title'] ) && is_scalar( $page['title'] ) ? sanitize_text_field( (string) $page['title'] ) : '';
	if ( '' === $title ) {
		return new WP_Error( 'ap_tl_title_required', __( 'Give the new page a title.', 'ap-template-library' ), array( 'status' => 400 ) );
	}

	$content = ap_tl_build_content( $reference, $payload['blocks'] ?? null );
	if ( is_wp_error( $content ) ) {
		return $content;
	}

	$postarr = array(
		'post_type'      => $post_type,
		'post_status'    => 'draft',
		'post_title'     => $title,
		'post_content'   => $content,
		'post_author'    => get_current_user_id(),
		'comment_status' => $reference->post_type === $post_type ? $reference->comment_status : get_default_comment_status( $post_type ),
		'ping_status'    => $reference->post_type === $post_type ? $reference->ping_status : get_default_comment_status( $post_type, 'pingback' ),
	);
	if ( ! empty( $page['slug'] ) && is_scalar( $page['slug'] ) ) {
		$slug = sanitize_title( (string) $page['slug'] );
		if ( '' !== $slug ) {
			$postarr['post_name'] = $slug;
		}
	}
	if ( post_type_supports( $post_type, 'excerpt' ) && isset( $page['excerpt'] ) && is_scalar( $page['excerpt'] ) ) {
		$postarr['post_excerpt'] = sanitize_textarea_field( (string) $page['excerpt'] );
	}
	if ( is_post_type_hierarchical( $post_type ) && ! empty( $page['parent'] ) ) {
		$parent = get_post( absint( $page['parent'] ) );
		if ( $parent && $parent->post_type === $post_type && 'trash' !== $parent->post_status ) {
			$postarr['post_parent'] = $parent->ID;
		}
	}

	// wp_insert_post() unslashes its input; the serialized attributes contain backslashes.
	$post_id = wp_insert_post( wp_slash( $postarr ), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	foreach ( ap_tl_copy_meta_keys( $template, $reference ) as $meta_key ) {
		$value = get_post_meta( $reference->ID, (string) $meta_key, true );
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $post_id, (string) $meta_key, wp_slash( $value ) );
		}
	}
	update_post_meta( $post_id, AP_TL_SOURCE_META, $template['key'] );

	// Terms: only ids that exist in a taxonomy offered for this post type.
	$submitted_terms = isset( $page['terms'] ) && is_array( $page['terms'] ) ? $page['terms'] : array();
	foreach ( ap_tl_taxonomies_for( $post_type ) as $name => $taxonomy ) {
		if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			continue;
		}
		$ids = array();
		if ( isset( $submitted_terms[ $name ] ) && is_array( $submitted_terms[ $name ] ) ) {
			foreach ( array_slice( $submitted_terms[ $name ], 0, 100 ) as $term_id ) {
				$term = is_numeric( $term_id ) ? get_term( (int) $term_id, $name ) : null;
				if ( $term instanceof WP_Term ) {
					$ids[] = (int) $term->term_id;
				}
			}
		}
		if ( ! $ids && 'resource-types' === $name && '' !== $context['resource_type'] ) {
			$term = get_term_by( 'slug', $context['resource_type'], $name );
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( $ids ) {
			wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $name );
		}
	}

	// Featured image: an attachment chosen in the media library, or the reference's imported URL kept as is.
	if ( post_type_supports( $post_type, 'thumbnail' ) && isset( $page['featured'] ) && is_array( $page['featured'] ) ) {
		$featured = $page['featured'];
		$id       = isset( $featured['id'] ) ? absint( $featured['id'] ) : 0;
		$url      = isset( $featured['url'] ) && is_string( $featured['url'] ) ? $featured['url'] : '';
		$ref_img  = ap_tl_reference_featured( $reference );
		if ( $id && wp_attachment_is_image( $id ) ) {
			set_post_thumbnail( $post_id, $id );
		} elseif ( ! $id && '' !== $url && $url === $ref_img['url'] && 0 === $ref_img['id'] ) {
			update_post_meta( $post_id, '_ap_featured_image_url', esc_url_raw( $url ) );
			if ( '' !== $ref_img['alt'] ) {
				update_post_meta( $post_id, '_ap_featured_image_alt', sanitize_text_field( $ref_img['alt'] ) );
			}
		}
	}

	ap_tl_delete_progress( get_current_user_id(), $template['key'] );

	/**
	 * Fires after the Template Library created a draft.
	 *
	 * @param int     $post_id   New draft.
	 * @param array   $template  Template.
	 * @param WP_Post $reference Reference post.
	 */
	do_action( 'ap_tl_draft_created', $post_id, $template, $reference );

	return array(
		'id'           => (int) $post_id,
		// get_the_title() is texturized HTML ("–" comes back as &#8211;); the builder prints it as text.
		'title'        => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
		'status'       => get_post_status( $post_id ),
		'post_type'    => $post_type,
		'edit_link'    => (string) get_edit_post_link( $post_id, 'raw' ),
		'preview_link' => (string) get_preview_post_link( $post_id ),
	);
}

/**
 * All saved progress of a user (template key => {state, saved, hash}).
 *
 * @param int $user_id User.
 * @return array
 */
function ap_tl_get_progress( $user_id ) {
	$all = get_user_meta( (int) $user_id, AP_TL_PROGRESS_META, true );
	return is_array( $all ) ? $all : array();
}

/**
 * Saves a user's builder progress for one template.
 *
 * @param int    $user_id User.
 * @param string $key     Template key.
 * @param string $state   JSON state from the builder.
 * @return array|WP_Error {saved: int}
 */
function ap_tl_save_progress( $user_id, $key, $state ) {
	if ( ! is_string( $state ) || strlen( $state ) > AP_TL_MAX_PROGRESS ) {
		return new WP_Error( 'ap_tl_progress_too_large', __( 'This progress is too large to save.', 'ap-template-library' ), array( 'status' => 413 ) );
	}
	$decoded = json_decode( $state, true );
	if ( ! is_array( $decoded ) || ! isset( $decoded['boxes'] ) || ! is_array( $decoded['boxes'] ) ) {
		return new WP_Error( 'ap_tl_progress_invalid', __( 'The progress could not be read.', 'ap-template-library' ), array( 'status' => 400 ) );
	}
	$all         = ap_tl_get_progress( $user_id );
	$all[ $key ] = array(
		'state' => wp_json_encode( $decoded ), // Re-encoded: nothing but valid JSON is stored.
		'saved' => time(),
		'hash'  => isset( $decoded['hash'] ) && is_string( $decoded['hash'] ) ? preg_replace( '/[^a-f0-9]/', '', $decoded['hash'] ) : '',
	);
	update_user_meta( (int) $user_id, AP_TL_PROGRESS_META, wp_slash( $all ) );
	return array( 'saved' => $all[ $key ]['saved'] );
}

/**
 * Deletes a user's saved progress for one template.
 *
 * @param int    $user_id User.
 * @param string $key     Template key.
 */
function ap_tl_delete_progress( $user_id, $key ) {
	$all = ap_tl_get_progress( $user_id );
	if ( isset( $all[ $key ] ) ) {
		unset( $all[ $key ] );
		if ( $all ) {
			update_user_meta( (int) $user_id, AP_TL_PROGRESS_META, wp_slash( $all ) );
		} else {
			delete_user_meta( (int) $user_id, AP_TL_PROGRESS_META );
		}
	}
}
