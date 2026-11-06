<?php
/**
 * The Video Library (AP Builder 1.1.0, 2026-11): one place to add videos and
 * choose where they show, instead of pasting YouTube links into each section.
 *
 * - "Video Library" in wp-admin: a video is a YouTube link plus its title,
 *   description and picture (filled from YouTube when added). Videos are
 *   hosted and compressed by YouTube; the site keeps a local poster (served
 *   as WebP by the media localizer) and loads the player only on click.
 * - "Show on": Video Tutorials page, an integration page, or featured on any
 *   pages. Pages show their videos through the Video Library block
 *   (video-block.php); integration pages also get theirs in the integration
 *   section's own video slot when that slot is empty.
 * - New uploads to the Asset Panda YouTube channel (and any playlists) come
 *   in by themselves (video-sync.php).
 * - The builder library's Videos cabinet lists them and adds them to pages.
 *
 * Who: anyone who can edit pages (edit_pages), as for the builder.
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_VIDEO_TYPE  = 'ap_video';
const AP_VIDEO_PLACE = 'ap_video_place';

/** The placements every site has: slug => [ name, description ]. */
function ap_video_default_places() {
	return array(
		'tutorials'    => array( 'Video Tutorials page', 'Shows in the gallery on /tutorials/.' ),
		'integrations' => array( 'Integration page', 'Shows on the integration page picked below.' ),
		'featured'     => array( 'Featured on pages', 'Shows on the pages picked below, wherever they have a Video Library block.' ),
	);
}

add_action(
	'init',
	static function () {
		register_post_type(
			AP_VIDEO_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Videos', 'ap-builder' ),
					'singular_name'      => __( 'Video', 'ap-builder' ),
					'menu_name'          => __( 'Video Library', 'ap-builder' ),
					'all_items'          => __( 'All videos', 'ap-builder' ),
					'add_new'            => __( 'Add video', 'ap-builder' ),
					'add_new_item'       => __( 'Add a video', 'ap-builder' ),
					'edit_item'          => __( 'Edit video', 'ap-builder' ),
					'search_items'       => __( 'Search videos', 'ap-builder' ),
					'not_found'          => __( 'No videos yet. Add one with its YouTube link.', 'ap-builder' ),
					'not_found_in_trash' => __( 'No videos in the trash.', 'ap-builder' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 21,
				'menu_icon'           => 'dashicons-video-alt3',
				'show_in_rest'        => true,
				'rest_base'           => 'ap-videos',
				'supports'            => array( 'title', 'excerpt', 'thumbnail', 'page-attributes' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
			)
		);
		register_taxonomy(
			AP_VIDEO_PLACE,
			AP_VIDEO_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Show on', 'ap-builder' ),
					'singular_name' => __( 'Placement', 'ap-builder' ),
					'menu_name'     => __( 'Placements', 'ap-builder' ),
					'add_new_item'  => __( 'Add a placement', 'ap-builder' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'hierarchical'      => true, // Checkboxes in the editor.
				'show_admin_column' => true,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'edit_pages',
					'edit_terms'   => 'edit_pages',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_pages',
				),
			)
		);
		foreach ( array( '_ap_video_url', '_ap_video_id', '_ap_video_source', '_ap_video_published' ) as $key ) {
			register_post_meta(
				AP_VIDEO_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static function () {
						return current_user_can( 'edit_pages' );
					},
				)
			);
		}
	}
);

/** The default placements, created once (and again if one is deleted). */
add_action(
	'admin_init',
	static function () {
		if ( get_option( 'ap_video_places_v' ) === AP_BUILDER_VERSION || ! taxonomy_exists( AP_VIDEO_PLACE ) ) {
			return;
		}
		foreach ( ap_video_default_places() as $slug => $meta ) {
			if ( ! term_exists( $slug, AP_VIDEO_PLACE ) ) {
				wp_insert_term( $meta[0], AP_VIDEO_PLACE, array( 'slug' => $slug, 'description' => $meta[1] ) );
			}
		}
		update_option( 'ap_video_places_v', AP_BUILDER_VERSION, false );
	}
);

/**
 * The YouTube id in a link (watch, youtu.be, shorts, live, embed) or a bare id.
 *
 * @param string $url Link or id.
 * @return string 11-character id, or ''.
 */
function ap_video_youtube_id( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $url ) ) {
		return $url;
	}
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/** The library video holding a YouTube id, or 0. */
function ap_video_find( $youtube_id ) {
	$found = get_posts(
		array(
			'post_type'        => AP_VIDEO_TYPE,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'meta_key'         => '_ap_video_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => (string) $youtube_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'numberposts'      => 1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);
	return $found ? (int) $found[0] : 0;
}

/**
 * YouTube's own details for a video (title, author), through oEmbed. Cached a day.
 *
 * @param string $youtube_id Id.
 * @return array { title, author } or empty.
 */
function ap_video_oembed( $youtube_id ) {
	$key    = 'ap_video_oe2_' . $youtube_id;
	$cached = get_transient( $key );
	if ( is_array( $cached ) && $cached ) {
		return $cached;
	}
	$url  = 'https://www.youtube.com/watch?v=' . $youtube_id;
	$data = null;
	// WordPress's own oEmbed client first: the same request the editor's YouTube embeds make, known to work from this host.
	if ( function_exists( '_wp_oembed_get_object' ) ) {
		$object = _wp_oembed_get_object()->get_data( $url, array() );
		$data   = $object ? (array) $object : null;
	}
	if ( ! $data ) {
		$res  = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( $url ), array( 'timeout' => 10 ) );
		$data = 200 === wp_remote_retrieve_response_code( $res ) ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
	}
	$out = is_array( $data ) && ! empty( $data['title'] ) ? array(
		'title'  => (string) $data['title'],
		'author' => isset( $data['author_name'] ) ? (string) $data['author_name'] : '',
	) : array();
	set_transient( $key, $out, $out ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * Puts YouTube's largest picture for the video in the Media Library and makes it the poster.
 *
 * @param int    $post_id    Video post.
 * @param string $youtube_id Id.
 * @return int Attachment id, or 0.
 */
function ap_video_fetch_poster( $post_id, $youtube_id ) {
	if ( has_post_thumbnail( $post_id ) || '' === $youtube_id ) {
		return (int) get_post_thumbnail_id( $post_id );
	}
	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$title = get_the_title( $post_id );
	foreach ( array( 'maxresdefault', 'sddefault', 'hqdefault' ) as $size ) {
		$url  = 'https://i.ytimg.com/vi/' . $youtube_id . '/' . $size . '.jpg';
		$head = wp_remote_head( $url, array( 'timeout' => 8 ) );
		if ( 200 !== wp_remote_retrieve_response_code( $head ) ) {
			continue;
		}
		$id = media_sideload_image( $url, $post_id, $title, 'id' );
		if ( is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( (int) $id, '_wp_attachment_image_alt', $title );
		set_post_thumbnail( $post_id, (int) $id );
		return (int) $id;
	}
	return 0;
}

/**
 * Adds a video to the library (or returns the one already there).
 *
 * @param string $url    YouTube link.
 * @param array  $args   Optional: title, excerpt, places (slugs), source, published (ISO date), status, menu_order, poster_id.
 * @return int|WP_Error Video post id.
 */
function ap_video_add( $url, array $args = array() ) {
	$yt = ap_video_youtube_id( $url );
	if ( '' === $yt ) {
		return new WP_Error( 'ap_video_bad_link', __( 'That is not a YouTube video link.', 'ap-builder' ), array( 'status' => 400 ) );
	}
	$existing = ap_video_find( $yt );
	if ( $existing ) {
		return $existing;
	}
	$title = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
	if ( '' === $title ) {
		$oe    = ap_video_oembed( $yt );
		$title = isset( $oe['title'] ) && '' !== $oe['title'] ? $oe['title'] : 'YouTube video ' . $yt;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => AP_VIDEO_TYPE,
			'post_status'  => isset( $args['status'] ) ? (string) $args['status'] : 'publish',
			'post_title'   => wp_strip_all_tags( $title ),
			'post_excerpt' => isset( $args['excerpt'] ) ? sanitize_textarea_field( (string) $args['excerpt'] ) : '',
			'menu_order'   => isset( $args['menu_order'] ) ? (int) $args['menu_order'] : 0,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_ap_video_id', $yt );
	update_post_meta( $id, '_ap_video_url', 'https://www.youtube.com/watch?v=' . $yt );
	update_post_meta( $id, '_ap_video_source', isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : 'manual' );
	if ( ! empty( $args['published'] ) ) {
		update_post_meta( $id, '_ap_video_published', sanitize_text_field( (string) $args['published'] ) );
	}
	if ( ! empty( $args['places'] ) ) {
		wp_set_object_terms( $id, array_map( 'sanitize_key', (array) $args['places'] ), AP_VIDEO_PLACE );
	}
	if ( ! empty( $args['poster_id'] ) && wp_attachment_is_image( (int) $args['poster_id'] ) ) {
		set_post_thumbnail( $id, (int) $args['poster_id'] );
	} elseif ( empty( $args['skip_poster'] ) ) {
		ap_video_fetch_poster( $id, $yt );
	}
	return $id;
}

/**
 * One video as the pages and the library use it.
 *
 * @param int|WP_Post $post Video.
 * @return array
 */
function ap_video_data( $post ) {
	$post   = get_post( $post );
	$yt     = (string) get_post_meta( $post->ID, '_ap_video_id', true );
	$thumb  = (int) get_post_thumbnail_id( $post->ID );
	$poster = $thumb ? wp_get_attachment_image_src( $thumb, 'full' ) : false;
	$places = wp_get_object_terms( $post->ID, AP_VIDEO_PLACE, array( 'fields' => 'slugs' ) );
	return array(
		'id'          => (int) $post->ID,
		'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'body'        => (string) $post->post_excerpt,
		'youtube'     => $yt,
		'url'         => '' !== $yt ? 'https://www.youtube.com/watch?v=' . $yt : '',
		'poster'      => $poster ? (string) $poster[0] : ( '' !== $yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : '' ),
		'poster_w'    => $poster ? (int) $poster[1] : 480,
		'poster_h'    => $poster ? (int) $poster[2] : 360,
		'thumb'       => $thumb ? (string) wp_get_attachment_image_url( $thumb, 'medium' ) : ( '' !== $yt ? 'https://i.ytimg.com/vi/' . $yt . '/mqdefault.jpg' : '' ),
		'places'      => is_wp_error( $places ) ? array() : array_values( $places ),
		'pages'       => array_map( 'intval', (array) get_post_meta( $post->ID, '_ap_video_page' ) ),
		'integration' => (int) get_post_meta( $post->ID, '_ap_video_integration', true ),
		'published'   => (string) get_post_meta( $post->ID, '_ap_video_published', true ),
		'date'        => get_post_time( 'c', true, $post ),
		'status'      => (string) $post->post_status,
		'order'       => (int) $post->menu_order,
	);
}

/**
 * Shows a video on a page, or stops showing it there.
 *
 * @param int  $video_id Video.
 * @param int  $page_id  Page.
 * @param bool $on       Show or stop.
 */
function ap_video_set_page( $video_id, $page_id, $on ) {
	$pages = array_map( 'intval', (array) get_post_meta( $video_id, '_ap_video_page' ) );
	if ( $on && ! in_array( $page_id, $pages, true ) ) {
		add_post_meta( $video_id, '_ap_video_page', $page_id );
		wp_set_object_terms( $video_id, 'featured', AP_VIDEO_PLACE, true );
	} elseif ( ! $on && in_array( $page_id, $pages, true ) ) {
		delete_post_meta( $video_id, '_ap_video_page', $page_id );
	}
}

/* ---- The edit screen -------------------------------------------------- */

add_action(
	'add_meta_boxes_' . AP_VIDEO_TYPE,
	static function () {
		add_meta_box( 'ap-video-link', __( 'YouTube video', 'ap-builder' ), 'ap_video_box_link', AP_VIDEO_TYPE, 'normal', 'high' );
		add_meta_box( 'ap-video-pages', __( 'Where it shows', 'ap-builder' ), 'ap_video_box_pages', AP_VIDEO_TYPE, 'normal', 'default' );
	}
);

/** The pages that are integration pages (under /integrations/). */
function ap_video_integration_pages() {
	$parent = get_page_by_path( 'integrations' );
	if ( ! $parent ) {
		return array();
	}
	return get_pages(
		array(
			'child_of'    => $parent->ID,
			'sort_column' => 'post_title',
			'post_status' => array( 'publish', 'draft', 'private' ),
		)
	);
}

/** The link box. */
function ap_video_box_link( $post ) {
	wp_nonce_field( 'ap_video_save', 'ap_video_nonce' );
	$url = (string) get_post_meta( $post->ID, '_ap_video_url', true );
	$yt  = (string) get_post_meta( $post->ID, '_ap_video_id', true );
	$src = (string) get_post_meta( $post->ID, '_ap_video_source', true );
	?>
	<p>
		<label for="ap-video-url"><strong><?php esc_html_e( 'YouTube link', 'ap-builder' ); ?></strong></label><br />
		<input type="url" id="ap-video-url" name="ap_video_url" value="<?php echo esc_attr( $url ); ?>" class="widefat" placeholder="https://www.youtube.com/watch?v=…" />
		<span class="description"><?php esc_html_e( 'Paste the video’s link (watch, youtu.be, Shorts or embed). Leave the title empty to use YouTube’s; the picture comes from YouTube too and is kept in the Media Library.', 'ap-builder' ); ?></span>
	</p>
	<?php if ( '' !== $yt ) : ?>
		<p><a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $yt ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $yt . '/mqdefault.jpg' ); ?>" alt="" width="320" height="180" style="border-radius:6px;display:block" /></a></p>
		<?php if ( '' !== $src && 'manual' !== $src ) : ?>
			<p class="description"><?php echo esc_html( sprintf( /* translators: %s: where the video came from */ __( 'Added from: %s', 'ap-builder' ), $src ) ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'The description (Excerpt below) shows under the video while it plays.', 'ap-builder' ); ?></p>
	<?php
}

/** The pages box: the integration page and the featured pages. */
function ap_video_box_pages( $post ) {
	$integration = (int) get_post_meta( $post->ID, '_ap_video_integration', true );
	$chosen      = array_map( 'intval', (array) get_post_meta( $post->ID, '_ap_video_page' ) );
	$pages       = get_pages(
		array(
			'sort_column' => 'post_title',
			'post_status' => array( 'publish', 'draft', 'private' ),
		)
	);
	?>
	<p class="description"><?php esc_html_e( 'Tick the placements in the “Show on” box, then pick the pages here. A page shows its videos where it has a Video Library block (add one from the Videos cabinet in the editor). Integration pages show theirs in the integration section’s video spot when it has no video of its own.', 'ap-builder' ); ?></p>
	<p>
		<label for="ap-video-integration"><strong><?php esc_html_e( 'Integration page', 'ap-builder' ); ?></strong></label><br />
		<select id="ap-video-integration" name="ap_video_integration" class="widefat">
			<option value="0"><?php esc_html_e( '— None —', 'ap-builder' ); ?></option>
			<?php foreach ( ap_video_integration_pages() as $page ) : ?>
				<option value="<?php echo esc_attr( (string) $page->ID ); ?>"<?php selected( $integration, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="ap-video-pages"><strong><?php esc_html_e( 'Featured on these pages', 'ap-builder' ); ?></strong></label><br />
		<select id="ap-video-pages" name="ap_video_pages[]" multiple size="10" class="widefat">
			<?php foreach ( $pages as $page ) : ?>
				<option value="<?php echo esc_attr( (string) $page->ID ); ?>"<?php selected( in_array( (int) $page->ID, $chosen, true ) ); ?>><?php echo esc_html( $page->post_title . ( 'publish' !== $page->post_status ? ' (' . $page->post_status . ')' : '' ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Ctrl+click (Cmd+click on a Mac) to pick several.', 'ap-builder' ); ?></span>
	</p>
	<?php
}

add_action(
	'save_post_' . AP_VIDEO_TYPE,
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['ap_video_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ap_video_nonce'] ) ), 'ap_video_save' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = isset( $_POST['ap_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['ap_video_url'] ) ) : '';
		$yt  = ap_video_youtube_id( $url );
		update_post_meta( $post_id, '_ap_video_url', '' !== $yt ? 'https://www.youtube.com/watch?v=' . $yt : '' );
		update_post_meta( $post_id, '_ap_video_id', $yt );
		if ( '' === (string) get_post_meta( $post_id, '_ap_video_source', true ) ) {
			update_post_meta( $post_id, '_ap_video_source', 'manual' );
		}
		update_post_meta( $post_id, '_ap_video_integration', isset( $_POST['ap_video_integration'] ) ? absint( $_POST['ap_video_integration'] ) : 0 );
		$want = isset( $_POST['ap_video_pages'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ap_video_pages'] ) ) : array();
		delete_post_meta( $post_id, '_ap_video_page' );
		foreach ( array_unique( array_filter( $want ) ) as $page ) {
			add_post_meta( $post_id, '_ap_video_page', $page );
		}
		// YouTube's title when none was typed, and its picture as the poster.
		if ( '' !== $yt ) {
			remove_all_actions( 'save_post_' . AP_VIDEO_TYPE ); // No re-entry from the updates below.
			if ( '' === trim( $post->post_title ) || 0 === strpos( $post->post_title, 'Auto Draft' ) ) {
				$oe = ap_video_oembed( $yt );
				if ( ! empty( $oe['title'] ) ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => $oe['title'],
						)
					);
				}
			}
			ap_video_fetch_poster( $post_id, $yt );
		}
	},
	10,
	2
);

/* The list: picture and link columns. */
add_filter(
	'manage_' . AP_VIDEO_TYPE . '_posts_columns',
	static function ( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['ap_poster'] = __( 'Video', 'ap-builder' );
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['ap_where'] = __( 'Pages', 'ap-builder' );
			}
		}
		return $out;
	}
);

add_action(
	'manage_' . AP_VIDEO_TYPE . '_posts_custom_column',
	static function ( $column, $post_id ) {
		if ( 'ap_poster' === $column ) {
			$d = ap_video_data( $post_id );
			if ( '' !== $d['thumb'] ) {
				echo '<a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $d['thumb'] ) . '" alt="" width="120" height="68" style="object-fit:cover;border-radius:4px" /></a>';
			}
		} elseif ( 'ap_where' === $column ) {
			$d     = ap_video_data( $post_id );
			$names = array();
			if ( $d['integration'] ) {
				$names[] = get_the_title( $d['integration'] );
			}
			foreach ( $d['pages'] as $page ) {
				$names[] = get_the_title( $page );
			}
			echo esc_html( $names ? implode( ', ', $names ) : '—' );
		}
	},
	10,
	2
);

/* ---- REST for the builder's Videos cabinet ----------------------------- */

add_action(
	'rest_api_init',
	static function () {
		$can = static function () {
			return current_user_can( 'edit_pages' );
		};
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/videos',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
					'callback'            => static function () {
						$posts = get_posts(
							array(
								'post_type'        => AP_VIDEO_TYPE,
								'post_status'      => array( 'publish', 'draft' ),
								'numberposts'      => 300,
								'orderby'          => array(
									'menu_order' => 'ASC',
									'date'       => 'DESC',
								),
								'suppress_filters' => true,
							)
						);
						$places = get_terms(
							array(
								'taxonomy'   => AP_VIDEO_PLACE,
								'hide_empty' => false,
							)
						);
						return rest_ensure_response(
							array(
								'videos' => array_map( 'ap_video_data', $posts ),
								'places' => is_wp_error( $places ) ? array() : array_map(
									static function ( $t ) {
										return array(
											'slug' => $t->slug,
											'name' => $t->name,
										);
									},
									$places
								),
								'manage' => admin_url( 'edit.php?post_type=' . AP_VIDEO_TYPE ),
							)
						);
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can,
					'args'                => array(
						'url'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'places' => array(
							'type'    => 'array',
							'default' => array(),
						),
						'page'   => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
					'callback'            => static function ( WP_REST_Request $request ) {
						$id = ap_video_add( (string) $request['url'], array( 'places' => (array) $request['places'] ) );
						if ( is_wp_error( $id ) ) {
							return $id;
						}
						if ( (int) $request['page'] > 0 && current_user_can( 'edit_post', (int) $request['page'] ) ) {
							ap_video_set_page( $id, (int) $request['page'], true );
						}
						return rest_ensure_response( ap_video_data( $id ) );
					},
				),
			)
		);
		register_rest_route(
			AP_BUILDER_REST_NS,
			'/videos/(?P<id>\d+)/page',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => static function ( WP_REST_Request $request ) {
					return current_user_can( 'edit_pages' ) && current_user_can( 'edit_post', (int) $request['page'] );
				},
				'args'                => array(
					'page' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'on'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'callback'            => static function ( WP_REST_Request $request ) {
					$id = (int) $request['id'];
					if ( AP_VIDEO_TYPE !== get_post_type( $id ) ) {
						return new WP_Error( 'ap_video_missing', __( 'No such video.', 'ap-builder' ), array( 'status' => 404 ) );
					}
					ap_video_set_page( $id, (int) $request['page'], (bool) $request['on'] );
					return rest_ensure_response( ap_video_data( $id ) );
				},
			)
		);
	}
);
