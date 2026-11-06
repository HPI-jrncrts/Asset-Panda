<?php
/**
 * Videos on pages, from the Video Library.
 *
 * 1. The Video Library block (ap-builder/videos): shows
 *      - the videos placed on a placement ("Video Tutorials page"…), in their order, or
 *      - the videos featured on the page it is on, or
 *      - videos picked by hand (the Videos cabinet adds these)
 *    as the kit's video theater gallery (page-videos, as on /tutorials/) or
 *    as one video band (media-band). An adapter like the kit's blocks: it
 *    fetches the videos and maps them to kit props; the kit draws them. The
 *    player loads only when a visitor presses play (the kit's poster + click).
 * 2. Integration pages: an integration section (assetpanda/integration-detail)
 *    whose Overview has no video of its own shows the library video placed on
 *    that integration page.
 * 3. Search: every page showing library videos gets VideoObject structured
 *    data for them (name, description, picture, date, YouTube embed).
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The library videos a block shows.
 *
 * @param array $a       Block attributes.
 * @param int   $page_id Page the block is on.
 * @return WP_Post[]
 */
function ap_video_block_posts( array $a, $page_id ) {
	$query = array(
		'post_type'        => AP_VIDEO_TYPE,
		'post_status'      => 'publish',
		'numberposts'      => ! empty( $a['limit'] ) ? max( 1, (int) $a['limit'] ) : 60,
		'orderby'          => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
		'suppress_filters' => true,
	);
	$mode = isset( $a['mode'] ) ? (string) $a['mode'] : 'place';
	if ( 'pick' === $mode ) {
		$ids = array_values( array_filter( array_map( 'absint', (array) ( isset( $a['ids'] ) ? $a['ids'] : array() ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$query['post__in'] = $ids;
		$query['orderby']  = 'post__in';
	} elseif ( 'page' === $mode ) {
		if ( ! $page_id ) {
			return array();
		}
		$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_ap_video_page',
				'value' => (string) $page_id,
			),
		);
	} else {
		$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => AP_VIDEO_PLACE,
				'field'    => 'slug',
				'terms'    => sanitize_key( isset( $a['place'] ) ? (string) $a['place'] : 'tutorials' ),
			),
		);
	}
	return get_posts( $query );
}

/** Videos rendered on this request, for the structured data. */
function ap_video_seen( $add = null ) {
	static $seen = array();
	if ( is_array( $add ) ) {
		foreach ( $add as $video ) {
			$seen[ $video['id'] ] = $video;
		}
	}
	return $seen;
}

/**
 * The block's render: library videos → kit props → the kit.
 *
 * @param array    $a       Attributes.
 * @param string   $content Unused.
 * @param WP_Block $block   Block.
 * @return string
 */
function ap_video_block_render( $a, $content = '', $block = null ) {
	if ( ! function_exists( 'ap_render_component' ) ) {
		return '';
	}
	$page_id = $block instanceof WP_Block && ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
	$videos  = array_values(
		array_filter(
			array_map( 'ap_video_data', ap_video_block_posts( (array) $a, $page_id ) ),
			static function ( $v ) {
				return '' !== $v['youtube'];
			}
		)
	);
	if ( ! $videos ) {
		return '';
	}
	ap_video_seen( $videos );
	$s = static function ( $key, $default = '' ) use ( $a ) {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? trim( (string) $a[ $key ] ) : $default;
	};
	if ( 'band' === $s( 'look', 'theater' ) ) {
		$v    = $videos[0];
		$html = ap_render_component(
			'media-band',
			array(
				'eyebrow'      => $s( 'eyebrow' ),
				'title'        => '' !== $s( 'title' ) ? $s( 'title' ) : $v['title'],
				'body'         => '' !== $s( 'intro' ) ? $s( 'intro' ) : $v['body'],
				'cta_label'    => $s( 'cta_label' ),
				'cta_url'      => $s( 'cta_url' ),
				'image'        => $v['poster'],
				'image_width'  => $v['poster_w'],
				'image_height' => $v['poster_h'],
				'video'        => $v['url'],
				'video_title'  => $v['title'],
				'video_look'   => 'card' === $s( 'video_look' ) ? 'card' : 'theater',
				'tone'         => 'white' === $s( 'tone' ) ? 'white' : 'soft',
			)
		);
	} else {
		$html = ap_render_component(
			'page-videos',
			array(
				'eyebrow'        => $s( 'eyebrow' ),
				'title'          => $s( 'title' ),
				'intro'          => $s( 'intro' ),
				'playlist_title' => $s( 'playlist_title' ),
				'tone'           => 'soft' === $s( 'tone' ) ? 'soft' : 'white',
				'card_tag'       => 'h2' === $s( 'card_tag' ) ? 'h2' : 'h3',
				'items'          => array_map(
					static function ( $v ) {
						return array(
							'title'    => $v['title'],
							'video'    => $v['url'],
							'poster'   => $v['poster'],
							'poster_w' => $v['poster_w'],
							'poster_h' => $v['poster_h'],
							'body'     => $v['body'],
						);
					},
					$videos
				),
			)
		);
	}
	if ( '' === trim( (string) $html ) ) {
		return '';
	}
	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes( array( 'style' => 'margin-top:0;margin-bottom:0' ) ),
		$html
	);
}

add_action(
	'init',
	static function () {
		register_block_type(
			'ap-builder/videos',
			array(
				'api_version'     => 3,
				'title'           => __( 'Video Library', 'ap-builder' ),
				'description'     => __( 'Videos from the Video Library: a placement’s videos (e.g. the tutorials), the videos featured on this page, or videos picked by hand — as the video gallery or one video band.', 'ap-builder' ),
				'category'        => 'media',
				'icon'            => 'video-alt3',
				'uses_context'    => array( 'postId' ),
				'supports'        => array(
					'html'  => false,
					'align' => array( 'full', 'wide' ),
				),
				'attributes'      => array(
					'mode'           => array(
						'type'    => 'string',
						'default' => 'place',
						'enum'    => array( 'place', 'page', 'pick' ),
					),
					'place'          => array(
						'type'    => 'string',
						'default' => 'tutorials',
					),
					'ids'            => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array( 'type' => 'integer' ),
					),
					'look'           => array(
						'type'    => 'string',
						'default' => 'theater',
						'enum'    => array( 'theater', 'band' ),
					),
					'video_look'     => array(
						'type'    => 'string',
						'default' => 'theater',
						'enum'    => array( 'theater', 'card' ),
					),
					'tone'           => array(
						'type'    => 'string',
						'default' => 'white',
						'enum'    => array( 'white', 'soft' ),
					),
					'eyebrow'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'title'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'intro'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'playlist_title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'card_tag'       => array(
						'type'    => 'string',
						'default' => 'h3',
						'enum'    => array( 'h2', 'h3' ),
					),
					'cta_label'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'cta_url'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit'          => array(
						'type'    => 'number',
						'default' => 0,
					),
					'align'          => array(
						'type'    => 'string',
						'default' => 'full',
					),
				),
				'render_callback' => 'ap_video_block_render',
			)
		);
	}
);

/* 2. Integration pages: an empty Overview video slot takes the library video placed on the page. */
add_filter(
	'render_block_data',
	static function ( $parsed ) {
		if ( 'assetpanda/integration-detail' !== ( isset( $parsed['blockName'] ) ? $parsed['blockName'] : '' ) || is_admin() ) {
			return $parsed;
		}
		$overview = isset( $parsed['attrs']['overview'] ) ? $parsed['attrs']['overview'] : null;
		if ( ! is_array( $overview ) || ! empty( $overview['video_url'] ) ) {
			return $parsed;
		}
		$page = (int) get_queried_object_id();
		if ( ! $page || ! post_type_exists( AP_VIDEO_TYPE ) ) {
			return $parsed;
		}
		$found = get_posts(
			array(
				'post_type'        => AP_VIDEO_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'meta_key'         => '_ap_video_integration', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => (string) $page, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => true,
			)
		);
		if ( ! $found ) {
			return $parsed;
		}
		$video                 = ap_video_data( $found[0] );
		$overview['video_url'] = $video['url'];
		if ( empty( $overview['video_poster'] ) ) {
			$overview['video_poster'] = $video['poster'];
		}
		$parsed['attrs']['overview'] = $overview;
		ap_video_seen( array( $video ) );
		return $parsed;
	}
);

/* 3. VideoObject structured data for the library videos this page showed. */
add_action(
	'wp_footer',
	static function () {
		$videos = ap_video_seen();
		if ( ! $videos || is_admin() ) {
			return;
		}
		$graph = array();
		foreach ( $videos as $v ) {
			$graph[] = array_filter(
				array(
					'@type'        => 'VideoObject',
					'name'         => $v['title'],
					'description'  => '' !== $v['body'] ? wp_html_excerpt( $v['body'], 300, '…' ) : $v['title'],
					'thumbnailUrl' => array( $v['poster'] ),
					'uploadDate'   => '' !== $v['published'] ? $v['published'] : $v['date'],
					'embedUrl'     => 'https://www.youtube.com/embed/' . $v['youtube'],
					'url'          => $v['url'],
				)
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	20
);
