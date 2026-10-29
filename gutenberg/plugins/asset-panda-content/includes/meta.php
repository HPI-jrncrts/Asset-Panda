<?php
/**
 * Post meta for the importer and for Yoast, the write-only REST helpers
 * (`ap_terms`, `ap_modified_gmt`), the no-twins guard and the external
 * featured image.
 *
 * Yoast compatibility: Yoast registers its own `_yoast_wpseo_*` keys with
 * register_meta( 'post', … ) and a sanitize callback. The keys are registered
 * here per post type, for REST only, WITHOUT a sanitize callback and WITHOUT a
 * default: a subtype sanitize filter would replace Yoast's own sanitizing
 * (sanitize_meta() runs only the subtype filter when one exists), and a
 * default would change what Yoast reads for posts that never stored a value.
 * The auth callback only gates REST/XML-RPC writes; Yoast's metabox and
 * sidebar keep saving through update_post_meta() as before.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast's own keys, writable over REST.
 *
 * @return string[]
 */
function ap_content_yoast_meta_keys() {
	return array(
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_canonical',
		'_yoast_wpseo_meta-robots-noindex',
		'_yoast_wpseo_meta-robots-nofollow',
		'_yoast_wpseo_meta-robots-adv',
		'_yoast_wpseo_opengraph-title',
		'_yoast_wpseo_opengraph-description',
		'_yoast_wpseo_opengraph-image',
		'_yoast_wpseo_opengraph-image-id',
		'_yoast_wpseo_focuskw',
		'_yoast_wpseo_bctitle',
		'_yoast_wpseo_primary_category',
	);
}

/**
 * This plugin's keys: key → [type, post types].
 *
 * @return array<string, array{0: string, 1: string[]}>
 */
function ap_content_own_meta_keys() {
	$all = array( 'resource', 'newsroom', 'page' );
	return array(
		'_ap_source_url'           => array( 'string', $all ),
		'_ap_source_id'            => array( 'integer', $all ),
		'_ap_source_type'          => array( 'string', $all ),
		'_ap_import_hash'          => array( 'string', $all ),
		'_ap_featured_image_url'   => array( 'string', $all ),
		'_ap_featured_image_alt'   => array( 'string', $all ),
		'_ap_source_modified_gmt'  => array( 'string', $all ),
		// Form panel: white papers (1.0) and pages (1.1).
		'_ap_gate_form_id'         => array( 'string', array( 'resource', 'page' ) ),
		'_ap_gate_portal_id'       => array( 'string', array( 'resource', 'page' ) ),
		'_ap_gate_region'          => array( 'string', array( 'resource', 'page' ) ),
		'_ap_gate_heading'         => array( 'string', array( 'resource', 'page' ) ),
		'_ap_gate_text'            => array( 'string', array( 'resource', 'page' ) ),
		'_ap_gate_button_label'    => array( 'string', array( 'resource', 'page' ) ),
	);
}

/**
 * Write access to a protected key: the user can edit posts and this one.
 *
 * @param bool   $allowed   Default.
 * @param string $meta_key  Key.
 * @param int    $object_id Post id.
 * @param int    $user_id   User id.
 * @return bool
 */
function ap_content_meta_auth( $allowed, $meta_key, $object_id, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return user_can( $user_id, 'edit_posts' ) && ( ! $object_id || user_can( $user_id, 'edit_post', (int) $object_id ) );
}

add_action(
	'init',
	function () {
		$auth = 'ap_content_meta_auth';
		foreach ( array( 'post', 'page', 'resource', 'newsroom' ) as $type ) {
			foreach ( ap_content_yoast_meta_keys() as $key ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => $auth,
					)
				);
			}
		}
		register_post_meta(
			'resource',
			'_yoast_wpseo_primary_resource-types',
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);
		foreach ( ap_content_own_meta_keys() as $key => $def ) {
			foreach ( $def[1] as $type ) {
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => $def[0],
						'single'            => true,
						'show_in_rest'      => true,
						'auth_callback'     => $auth,
						'sanitize_callback' => 'integer' === $def[0] ? 'absint' : 'ap_content_sanitize_text_meta',
					)
				);
			}
		}
		// 1.1: JSON-LD carried over from the old page body (a JSON array of schema objects).
		foreach ( array( 'page', 'post', 'resource', 'newsroom' ) as $type ) {
			register_post_meta(
				$type,
				'_ap_jsonld',
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => $auth,
					'sanitize_callback' => 'ap_content_sanitize_jsonld_meta',
				)
			);
		}
	},
	12
);

/**
 * Parses `_ap_jsonld`: a JSON array of schema objects (a single object is
 * accepted as a one-item array). Objects stay objects (stdClass), so `{}`
 * never turns into `[]` when printed again.
 *
 * @param mixed $value Stored or sent value.
 * @return array|WP_Error List of objects ([] for empty), or an error.
 */
function ap_content_parse_jsonld( $value ) {
	if ( ! is_string( $value ) ) {
		return new WP_Error( 'ap_content_bad_jsonld', '_ap_jsonld must be a string holding a JSON array of objects.', array( 'status' => 400 ) );
	}
	$value = trim( $value );
	if ( '' === $value ) {
		return array();
	}
	$data = json_decode( $value );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error( 'ap_content_bad_jsonld', '_ap_jsonld is not valid JSON: ' . json_last_error_msg() . '.', array( 'status' => 400 ) );
	}
	$list = is_array( $data ) ? $data : array( $data );
	foreach ( $list as $item ) {
		if ( ! is_object( $item ) ) {
			return new WP_Error( 'ap_content_bad_jsonld', '_ap_jsonld must be an array of JSON objects.', array( 'status' => 400 ) );
		}
	}
	return $list;
}

/**
 * Keeps the JSON text as sent (trimmed); validity is checked on REST writes
 * and again before printing.
 *
 * @param mixed $value Value.
 * @return string
 */
function ap_content_sanitize_jsonld_meta( $value ) {
	return is_string( $value ) ? trim( wp_check_invalid_utf8( $value ) ) : '';
}

/**
 * Plain one-line text; URLs keep their characters.
 *
 * @param mixed $value Value.
 * @return string
 */
function ap_content_sanitize_text_meta( $value ) {
	return is_scalar( $value ) ? trim( wp_check_invalid_utf8( (string) $value ) ) : '';
}

/* ── REST helpers: ap_terms and ap_modified_gmt ────────────────────────── */

add_action(
	'rest_api_init',
	function () {
		register_rest_field(
			array( 'resource', 'newsroom', 'page' ),
			'ap_modified_gmt',
			array(
				'get_callback'    => function ( $obj ) {
					$gmt = (string) get_post_meta( (int) $obj['id'], '_ap_source_modified_gmt', true );
					return '' === $gmt ? '' : str_replace( ' ', 'T', $gmt );
				},
				'update_callback' => 'ap_content_rest_set_modified',
				'schema'          => array(
					'description' => 'The live post_modified_gmt (YYYY-MM-DDTHH:MM:SS); written to post_modified(_gmt) after the save.',
					'type'        => 'string',
					'context'     => array( 'edit' ),
				),
			)
		);
		register_rest_field(
			array( 'resource' ),
			'ap_terms',
			array(
				'get_callback'    => function ( $obj ) {
					$out = array();
					foreach ( array( 'resource-types', 'category' ) as $tax ) {
						$terms       = get_the_terms( (int) $obj['id'], $tax );
						$out[ $tax ] = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'slug' ) : array();
					}
					$primary = ap_content_primary_type( (int) $obj['id'] );
					if ( $primary ) {
						$out['resource-types'] = array_values( array_unique( array_merge( array( $primary->slug ), $out['resource-types'] ) ) );
					}
					return $out;
				},
				'update_callback' => 'ap_content_rest_set_terms',
				'schema'          => array(
					'description' => 'Terms by slug: {"resource-types":["case-studies"],"category":["construction",{"slug":"x","name":"X"}]}. The first resource type is the one in the URL.',
					'type'        => 'object',
					'context'     => array( 'edit' ),
				),
			)
		);
	}
);

/**
 * Parses a GMT datetime. Accepts YYYY-MM-DDTHH:MM:SS, an optional Z or
 * +00:00, or a space instead of the T.
 *
 * @param mixed $value Value.
 * @return string|null MySQL datetime or null when invalid.
 */
function ap_content_parse_gmt( $value ) {
	if ( ! is_string( $value ) || ! preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:Z|[+-]00:?00)?$/', trim( $value ), $m ) ) {
		return null;
	}
	$dt = $m[1] . ' ' . $m[2];
	$ts = strtotime( $dt . ' UTC' );
	return ( false === $ts || gmdate( 'Y-m-d H:i:s', $ts ) !== $dt ) ? null : $dt;
}

/**
 * Validates `ap_terms` before anything is written.
 *
 * @param mixed  $value     Field value.
 * @param string $post_type Post type.
 * @return true|WP_Error
 */
function ap_content_validate_terms( $value, $post_type ) {
	if ( ! is_array( $value ) ) {
		return new WP_Error( 'ap_content_bad_term', 'ap_terms must be an object of taxonomy => slugs.', array( 'status' => 400 ) );
	}
	foreach ( $value as $tax => $items ) {
		if ( ! in_array( $tax, array( 'resource-types', 'category' ), true ) ) {
			return new WP_Error( 'ap_content_bad_term', 'ap_terms: unknown taxonomy ' . $tax . '.', array( 'status' => 400 ) );
		}
		if ( 'resource' !== $post_type ) {
			continue;
		}
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'ap_content_bad_term', 'ap_terms.' . $tax . ' must be a list.', array( 'status' => 400 ) );
		}
		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				if ( ! get_term_by( 'slug', $item, $tax ) ) {
					return new WP_Error( 'ap_content_bad_term', 'ap_terms: no ' . $tax . ' term with slug "' . $item . '".', array( 'status' => 400 ) );
				}
			} elseif ( 'category' === $tax && is_array( $item ) && ! empty( $item['slug'] ) && is_string( $item['slug'] ) ) {
				continue;
			} else {
				return new WP_Error( 'ap_content_bad_term', 'ap_terms.' . $tax . ': each item must be a slug' . ( 'category' === $tax ? ' or {"slug","name"}' : '' ) . '.', array( 'status' => 400 ) );
			}
		}
	}
	return true;
}

/**
 * Applies `ap_terms` (validated in rest_pre_insert). The first Resource Type
 * becomes Yoast's primary term, which the permalink uses.
 *
 * @param mixed   $value Field value.
 * @param WP_Post $post  Saved post.
 * @return true|WP_Error
 */
function ap_content_rest_set_terms( $value, $post ) {
	if ( ! $post instanceof WP_Post || 'resource' !== $post->post_type || ! is_array( $value ) ) {
		return true;
	}
	if ( isset( $value['resource-types'] ) && is_array( $value['resource-types'] ) ) {
		$ids = array();
		foreach ( $value['resource-types'] as $slug ) {
			$term = is_string( $slug ) ? get_term_by( 'slug', $slug, 'resource-types' ) : null;
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$set = wp_set_object_terms( $post->ID, $ids, 'resource-types', false );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
		if ( $ids ) {
			update_post_meta( $post->ID, '_yoast_wpseo_primary_resource-types', (string) $ids[0] );
		} else {
			delete_post_meta( $post->ID, '_yoast_wpseo_primary_resource-types' );
		}
	}
	if ( isset( $value['category'] ) && is_array( $value['category'] ) ) {
		$ids = array();
		foreach ( $value['category'] as $item ) {
			if ( is_string( $item ) ) {
				$term = get_term_by( 'slug', $item, 'category' );
			} else {
				$slug = sanitize_title( (string) $item['slug'] );
				$term = get_term_by( 'slug', $slug, 'category' );
				if ( ! $term ) {
					$name    = isset( $item['name'] ) && is_string( $item['name'] ) && '' !== trim( $item['name'] ) ? trim( $item['name'] ) : $slug;
					$created = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
					if ( is_wp_error( $created ) ) {
						return $created;
					}
					$term = get_term( (int) $created['term_id'], 'category' );
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		$set = wp_set_object_terms( $post->ID, array_values( array_unique( $ids ) ), 'category', false );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
	}
	return true;
}

/**
 * Writes the live modified date after the save (core REST cannot set it).
 * Changes the response's post object too, so the reply shows the new value.
 *
 * @param mixed   $value Field value.
 * @param WP_Post $post  Saved post.
 * @return true|WP_Error
 */
function ap_content_rest_set_modified( $value, $post ) {
	global $wpdb;
	if ( ! $post instanceof WP_Post ) {
		return true;
	}
	if ( null === $value || '' === $value ) {
		return true;
	}
	$gmt = ap_content_parse_gmt( $value );
	if ( ! $gmt ) {
		return new WP_Error( 'ap_content_bad_date', 'ap_modified_gmt must be YYYY-MM-DDTHH:MM:SS (GMT).', array( 'status' => 400 ) );
	}
	$local = get_date_from_gmt( $gmt );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update(
		$wpdb->posts,
		array(
			'post_modified'     => $local,
			'post_modified_gmt' => $gmt,
		),
		array( 'ID' => $post->ID )
	);
	update_post_meta( $post->ID, '_ap_source_modified_gmt', $gmt );
	clean_post_cache( $post->ID );
	$post->post_modified     = $local;
	$post->post_modified_gmt = $gmt;
	return true;
}

/* ── Validation and the no-twins guard (before anything is written) ──── */

/**
 * The item already using a source URL (any status but trash), or 0.
 *
 * @param string $url     Live URL.
 * @param int    $exclude Post id to ignore.
 * @return int
 */
function ap_content_find_by_source_url( $url, $exclude = 0 ) {
	global $wpdb;
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return 0;
	}
	$base     = preg_replace( '#^https?://#i', '', untrailingslashit( preg_replace( '/[?#].*$/', '', $url ) ) );
	$variants = array();
	foreach ( array( 'https://', 'http://' ) as $scheme ) {
		$variants[] = $scheme . $base;
		$variants[] = $scheme . $base . '/';
	}
	$in = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sql = $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
		WHERE m.meta_key = '_ap_source_url' AND m.meta_value IN ($in) AND p.ID <> %d
		AND p.post_status NOT IN ('trash','auto-draft','inherit') AND p.post_type IN ('resource','newsroom','page')
		ORDER BY (p.post_status = 'publish') DESC, p.ID ASC LIMIT 1",
		array_merge( $variants, array( (int) $exclude ) )
	);
	return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * rest_pre_insert_{type}: validates the helper fields and refuses a create
 * that would make a twin.
 *
 * @param stdClass|WP_Error $prepared Prepared post.
 * @param WP_REST_Request   $request  Request.
 * @return stdClass|WP_Error
 */
function ap_content_rest_pre_insert( $prepared, $request ) {
	if ( is_wp_error( $prepared ) ) {
		return $prepared;
	}
	try {
		$type = isset( $prepared->post_type ) ? $prepared->post_type : '';
		if ( ! $type ) {
			$route = (string) $request->get_route();
			$type  = false !== strpos( $route, '/wp/v2/resource' ) ? 'resource' : ( false !== strpos( $route, '/wp/v2/newsroom' ) ? 'newsroom' : 'page' );
		}
		// 1.1: refuse JSON-LD that would never print.
		$sent_meta = isset( $request['meta'] ) && is_array( $request['meta'] ) ? $request['meta'] : array();
		if ( array_key_exists( '_ap_jsonld', $sent_meta ) ) {
			$ld = ap_content_parse_jsonld( $sent_meta['_ap_jsonld'] );
			if ( is_wp_error( $ld ) ) {
				return $ld;
			}
		}
		if ( 'post' === $type ) {
			return $prepared; // Core posts: only the JSON-LD check.
		}
		if ( isset( $request['ap_terms'] ) ) {
			$ok = ap_content_validate_terms( $request['ap_terms'], $type );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		}
		if ( isset( $request['ap_modified_gmt'] ) && '' !== $request['ap_modified_gmt'] && null !== $request['ap_modified_gmt']
			&& ! ap_content_parse_gmt( $request['ap_modified_gmt'] ) ) {
			return new WP_Error( 'ap_content_bad_date', 'ap_modified_gmt must be YYYY-MM-DDTHH:MM:SS (GMT).', array( 'status' => 400 ) );
		}

		// Core REST derives date_gmt from `date` and the site's timezone and
		// ignores a date_gmt sent with it. Keep both exactly as live has them.
		if ( ! empty( $request['date'] ) && ! empty( $request['date_gmt'] ) && ! empty( $prepared->post_date ) ) {
			$gmt = rest_get_date_with_gmt( $request['date_gmt'], true );
			if ( ! empty( $gmt[1] ) ) {
				$prepared->post_date_gmt = $gmt[1];
				$prepared->edit_date     = true;
			}
		}

		$is_create  = empty( $prepared->ID );
		$meta       = isset( $request['meta'] ) && is_array( $request['meta'] ) ? $request['meta'] : array();
		$source_url = isset( $meta['_ap_source_url'] ) && is_string( $meta['_ap_source_url'] ) ? $meta['_ap_source_url'] : '';

		// A source URL belongs to one item, on create and on update.
		if ( '' !== $source_url ) {
			$owner = ap_content_find_by_source_url( $source_url, $is_create ? 0 : (int) $prepared->ID );
			if ( $owner ) {
				return ap_content_duplicate_error( $owner, 'another item already has this _ap_source_url' );
			}
		}
		if ( ! $is_create ) {
			return $prepared;
		}
		$slug = isset( $prepared->post_name ) ? (string) $prepared->post_name : '';
		if ( in_array( $type, array( 'resource', 'newsroom' ), true ) && '' !== $slug ) {
			$owner = ap_content_find_by_slug( $type, $slug );
			if ( $owner ) {
				return ap_content_duplicate_error( $owner, 'a ' . $type . ' with this slug already exists' );
			}
		}
		if ( 'page' === $type && '' !== $source_url && '' !== $slug ) {
			$parent = isset( $prepared->post_parent ) ? (int) $prepared->post_parent : 0;
			$path   = $slug;
			if ( $parent ) {
				$parent_path = get_page_uri( $parent );
				$path        = $parent_path ? $parent_path . '/' . $slug : $slug;
			}
			$page = get_page_by_path( $path, OBJECT, array( 'page' ) );
			if ( $page && 'trash' !== $page->post_status ) {
				return ap_content_duplicate_error( (int) $page->ID, 'a page already has this path' );
			}
		}
	} catch ( \Throwable $e ) {
		return new WP_Error( 'ap_content_error', $e->getMessage(), array( 'status' => 500 ) );
	}
	return $prepared;
}

/**
 * The 409 the contract promises.
 *
 * @param int    $existing Existing post id.
 * @param string $why      Reason.
 * @return WP_Error
 */
function ap_content_duplicate_error( $existing, $why ) {
	return new WP_Error(
		'ap_content_duplicate',
		'Create refused: ' . $why . ' (id ' . (int) $existing . '). Update that item instead.',
		array(
			'status'      => 409,
			'existing_id' => (int) $existing,
		)
	);
}

foreach ( array( 'resource', 'newsroom', 'page', 'post' ) as $ap_content_type ) {
	add_filter( 'rest_pre_insert_' . $ap_content_type, 'ap_content_rest_pre_insert', 10, 2 );
}
unset( $ap_content_type );

/* ── External featured image (no media attachment) ──────────────────── */

/**
 * The external image URL of a post that has no featured attachment.
 *
 * @param int $post_id Post id.
 * @return string
 */
function ap_content_external_image( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || (int) get_post_meta( $post_id, '_thumbnail_id', true ) ) {
		return '';
	}
	$url = (string) get_post_meta( $post_id, '_ap_featured_image_url', true );
	return ( '' !== $url && wp_http_validate_url( $url ) ) ? $url : '';
}

add_filter(
	'has_post_thumbnail',
	function ( $has, $post ) {
		if ( $has || is_admin() ) {
			return $has;
		}
		$post = get_post( $post );
		return $post ? '' !== ap_content_external_image( $post->ID ) : $has;
	},
	10,
	2
);

add_filter(
	'post_thumbnail_html',
	function ( $html, $post_id, $thumb_id, $size, $attr ) {
		if ( '' !== $html || is_admin() ) {
			return $html;
		}
		$url = ap_content_external_image( $post_id );
		if ( '' === $url ) {
			return $html;
		}
		$attr  = wp_parse_args( $attr );
		$size  = is_string( $size ) ? $size : 'full';
		$class = trim( 'attachment-' . $size . ' size-' . $size . ' wp-post-image ' . ( isset( $attr['class'] ) ? $attr['class'] : '' ) );
		$alt   = isset( $attr['alt'] ) ? $attr['alt'] : (string) get_post_meta( $post_id, '_ap_featured_image_alt', true );
		$out   = '<img src="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" alt="' . esc_attr( $alt ) . '" decoding="async"';
		if ( ! empty( $attr['loading'] ) ) {
			$out .= ' loading="' . esc_attr( $attr['loading'] ) . '"';
		}
		return $out . ' />';
	},
	10,
	5
);
