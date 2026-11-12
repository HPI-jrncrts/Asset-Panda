<?php
/**
 * Logo boxes (1.3.24). Joseph, 2026-11-10: "Still doesn't have the block for
 * images / icons / logos. The compare vs is better cause it actually has some
 * image containers." In a "Best …" article a company's logo is now an ordinary
 * image block right under its numbered heading: writers see it in the editor
 * and change it the way they change any picture (click it → Replace → Upload).
 *
 * 1. Front end: an image block straight after a ranked heading IS that
 *    company's logo. It is drawn in the design's logo slot, at the size of the
 *    automatic logos, instead of the automatic one. An empty box draws nothing,
 *    so the automatic logo (or the initials) stays.
 * 2. Template Library: a draft started from a "Best …" template gets a box
 *    under every company heading, holding the logo the page shows today, or
 *    empty and ready for an upload.
 * 3. Editor: a "Best …" article with company headings that have no box gets a
 *    notice with "Add logo boxes" (assets/logo-boxes.js); the boxes are shown
 *    at logo size in the editor.
 *
 * 1.3.25 (Joseph, 2026-11-10: "All the icons on each number is very big"):
 * a box is drawn at logo size on the page even when the page is not drawn as
 * a ranked list (a draft with no title yet, whose Preview printed each box as
 * a full-width picture), and Asset Panda's entry has a box too.
 *
 * @package AssetPandaContent
 */

defined( 'ABSPATH' ) || exit;

/** Class on the image blocks this feature adds (styling in the editor only). */
const AP_CONTENT_LOGO_BOX_CLASS = 'ap-vendor-logo';

/**
 * The company named by a ranked heading ("3. Freshservice — Best for IT" →
 * "Freshservice"), or '' when the heading is not a numbered entry. Same rule
 * as the design (ap26x_parse_rank).
 *
 * @param string $html Heading HTML or text.
 * @return string
 */
function ap_content_rank_name( $html ) {
	if ( function_exists( 'ap26x_parse_rank' ) ) {
		$rank = ap26x_parse_rank( $html );
		return $rank ? (string) $rank['name'] : '';
	}
	$plain = trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
	if ( ! preg_match( '/^#\s*(\d{1,2})\s*[:.)\x{2013}\x{2014}-]?\s*(.+)$/u', $plain, $m )
		&& ! preg_match( '/^(\d{1,2})\s*[:.)\x{2013}\x{2014}-]\s*(.+)$/u', $plain, $m ) ) {
		return '';
	}
	$parts = preg_split( '/\s+[\x{2013}\x{2014}-]\s+|:\s+/u', trim( $m[2] ), 2 );
	$name  = ( 2 === count( $parts ) && '' !== trim( $parts[1] ) ) ? trim( $parts[0] ) : trim( $m[2] );
	return ( '' === $name || mb_strlen( $name ) > 60 ) ? '' : $name;
}

/**
 * Whether a company is Asset Panda. Its entry draws its logo, the Capterra
 * rating and the award badges; since 1.3.25 its logo has a box like every
 * other company's (Joseph: "ICON?" under "1. Asset Panda"), and the rating and
 * badges stay under it.
 *
 * @param string $name Company name.
 * @return bool
 */
function ap_content_logo_is_ap( $name ) {
	if ( function_exists( 'ap26x_is_asset_panda' ) ) {
		return (bool) ap26x_is_asset_panda( $name );
	}
	return (bool) preg_match( '/^asset\s*panda\b/i', trim( (string) $name ) );
}

/**
 * A level-2 heading block's company name, or ''.
 *
 * @param array $block Parsed block.
 * @return string
 */
function ap_content_block_rank_name( array $block ) {
	if ( 'core/heading' !== ( $block['blockName'] ?? '' ) || 2 !== (int) ( $block['attrs']['level'] ?? 2 ) ) {
		return '';
	}
	return ap_content_rank_name( (string) ( $block['innerHTML'] ?? '' ) );
}

/**
 * The image address in an image block, or ''.
 *
 * @param array|null $block Parsed block.
 * @return string
 */
function ap_content_block_image_src( $block ) {
	if ( ! is_array( $block ) || 'core/image' !== ( $block['blockName'] ?? '' ) ) {
		return '';
	}
	if ( ! empty( $block['attrs']['url'] ) ) {
		return (string) $block['attrs']['url'];
	}
	return preg_match( '/<img\b[^>]*\ssrc="([^"]+)"/i', (string) ( $block['innerHTML'] ?? '' ), $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : '';
}

/**
 * The next real block after index $i (skipping the blank text between blocks).
 *
 * @param array[] $blocks Top-level parsed blocks.
 * @param int     $i      Index.
 * @return array|null
 */
function ap_content_next_block( array $blocks, $i ) {
	$n = count( $blocks );
	for ( $j = $i + 1; $j < $n; $j++ ) {
		if ( ! empty( $blocks[ $j ]['blockName'] ) ) {
			return $blocks[ $j ];
		}
		if ( '' !== trim( (string) ( $blocks[ $j ]['innerHTML'] ?? '' ) ) ) {
			return $blocks[ $j ];
		}
	}
	return null;
}

/**
 * The logo the page shows today for a company: one added on Resources →
 * Vendor logos, the design's built-in ones, or the older listicle logos.
 *
 * @param string $name Company name.
 * @return array|null url, w, h (shown size).
 */
function ap_content_logo_lookup( $name ) {
	if ( function_exists( 'ap26x_logo_for' ) ) {
		$logo = ap26x_logo_for( $name );
		return $logo ? array(
			'url' => (string) $logo['url'],
			'w'   => (int) $logo['w'],
			'h'   => (int) $logo['h'],
		) : null;
	}
	// wp-admin and REST without the front-end design snippet: the same lists, same order.
	$n = ap_content_logo_norm( $name );
	if ( '' === $n ) {
		return null;
	}
	$base = trailingslashit( wp_get_upload_dir()['baseurl'] );
	$all  = array();
	foreach ( ap_content_vendor_logos() as $row ) {
		$asset = ap_content_logo_asset( $row );
		if ( $asset ) {
			$all[] = $asset;
		}
	}
	foreach ( ap_content_builtin_logos() as $row ) {
		$all[] = array(
			'url'     => $base . '2026/09/' . $row[0],
			'w'       => $row[1],
			'h'       => $row[2],
			'aliases' => $row[3],
		);
	}
	if ( function_exists( 'ap_content_listicle_logos' ) ) {
		foreach ( ap_content_listicle_logos() as $row ) {
			$all[] = array(
				'url'     => 'https://www.assetpanda.com/wp-content/uploads/' . $row[0],
				'w'       => $row[1],
				'h'       => $row[2],
				'aliases' => $row[3],
			);
		}
	}
	foreach ( array( true, false ) as $exact ) {
		foreach ( $all as $logo ) {
			foreach ( (array) $logo['aliases'] as $alias ) {
				if ( $exact ? $n === $alias : 0 === strpos( $n . ' ', $alias . ' ' ) ) {
					return array(
						'url' => (string) $logo['url'],
						'w'   => (int) $logo['w'],
						'h'   => (int) $logo['h'],
					);
				}
			}
		}
	}
	return null;
}

/**
 * The design's built-in logos (snippet 8, ap26x_assets): file in uploads/2026/09,
 * shown width and height, names.
 *
 * @return array[]
 */
function ap_content_builtin_logos() {
	return array(
		array( 'ap26x-logo-asset-panda.png', 252, 95, array( 'asset panda' ) ),
		array( 'ap26x-logo-safetyculture.png', 370, 55, array( 'safetyculture', 'safety culture' ) ),
		array( 'ap26x-logo-aveva.png', 240, 55, array( 'aveva', 'aveva eam', 'aveva enterprise asset management' ) ),
		array( 'ap26x-logo-invgate.png', 270, 70, array( 'invgate', 'invgate assets' ) ),
		array( 'ap26x-logo-sage.png', 198, 112, array( 'sage', 'sage fixed assets' ) ),
		array( 'ap26x-logo-sap.png', 240, 119, array( 'sap', 'sap asset manager' ) ),
		array( 'ap26x-logo-ibm.png', 240, 90, array( 'ibm', 'ibm maximo', 'maximo' ) ),
		array( 'ap26x-logo-manageengine.png', 370, 65, array( 'manageengine', 'assetexplorer', 'manageengine assetexplorer' ) ),
		array( 'ap26x-logo-upkeep.png', 370, 94, array( 'upkeep' ) ),
		array( 'ap26x-logo-maintainx.png', 370, 59, array( 'maintainx' ) ),
		array( 'ap26x-logo-limble.png', 370, 91, array( 'limble', 'limble cmms' ) ),
		array( 'ap26x-logo-hexagon.png', 370, 114, array( 'hexagon', 'hxgn eam', 'hexagon eam' ) ),
		array( 'ap26x-logo-accruent.png', 370, 73, array( 'accruent' ) ),
	);
}

/**
 * An image block for a company's logo box, serialized the way the editor
 * saves it (so it opens without a "block contains unexpected content" notice).
 *
 * @param string $url Image address ('' = an empty box).
 * @param string $alt Alt text.
 * @return string
 */
function ap_content_logo_box_markup( $url, $alt ) {
	$attrs = array();
	if ( '' !== $url ) {
		$attrs['url'] = $url;
		$attrs['alt'] = $alt;
	}
	$attrs['className'] = AP_CONTENT_LOGO_BOX_CLASS;
	$img                = '' !== $url
		? '<img src="' . esc_attr( $url ) . '" alt="' . esc_attr( $alt ) . '"/>'
		: '<img alt=""/>';
	$html               = "\n" . '<figure class="wp-block-image ' . AP_CONTENT_LOGO_BOX_CLASS . '">' . $img . '</figure>' . "\n";
	return serialize_block(
		array(
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		)
	);
}

/**
 * Puts a logo box under every company heading that has none.
 *
 * @param string $content Post content.
 * @param bool   $fill    Fill each box with the logo shown today.
 * @return array{content:string, added:int}
 */
function ap_content_add_logo_boxes( $content, $fill = true ) {
	$blocks = parse_blocks( (string) $content );
	$out    = array();
	$added  = 0;
	foreach ( $blocks as $i => $block ) {
		$out[] = serialize_block( $block );
		$name  = ap_content_block_rank_name( $block );
		if ( '' === $name || 'core/image' === ( ap_content_next_block( $blocks, $i )['blockName'] ?? '' ) ) {
			continue;
		}
		$logo  = $fill ? ap_content_logo_lookup( $name ) : null;
		$alt   = ap_content_logo_is_ap( $name ) ? 'Asset Panda logo' : $name . ' logo';
		$out[] = "\n\n" . ap_content_logo_box_markup( $logo ? $logo['url'] : '', $alt );
		++$added;
	}
	return array(
		'content' => $added ? implode( '', $out ) : (string) $content,
		'added'   => $added,
	);
}

/**
 * Whether content reads as a "Best …" list: three or more numbered company
 * headings, or two under a best / top / vs / compared title.
 *
 * @param string $content Post content.
 * @param string $title   Title ('' when not known yet).
 * @return bool
 */
function ap_content_is_listicle( $content, $title = '' ) {
	$ranks = 0;
	foreach ( parse_blocks( (string) $content ) as $block ) {
		if ( '' !== ap_content_block_rank_name( $block ) ) {
			++$ranks;
		}
	}
	$titled = (bool) preg_match( '/\b(best|top|alternatives?|vs\.?|compared|comparison)\b/i', (string) $title ) && ! preg_match( '/\bbest practices?\b/i', (string) $title );
	return $ranks >= 3 && ( '' === trim( (string) $title ) || $titled ) || ( $ranks >= 2 && $titled );
}

/* 2. Template Library drafts of a "Best …" article start with their logo boxes. */
add_action(
	'ap_tl_draft_created',
	function ( $post_id, $template = null, $reference = null ) {
		$post = get_post( $post_id );
		if ( ! $post || ! ap_content_render_enabled() ) {
			return;
		}
		$title = $reference instanceof WP_Post ? $reference->post_title : $post->post_title;
		if ( ! ap_content_is_listicle( $post->post_content, $title ) ) {
			return;
		}
		$res = ap_content_add_logo_boxes( $post->post_content );
		if ( $res['added'] ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post->ID,
						'post_content' => $res['content'],
					)
				)
			);
		}
	},
	10,
	3
);

/*
 * 1. Front end. The design pass (snippet 8, armed at the_content priority 8)
 * renders a ranked heading with its automatic logo slot at render_block 10.
 * Here, at 9, the post's headings that have an image box straight after them
 * are noted; at render_block 11 the automatic slot is taken off those headings
 * and the image that follows is drawn in the slot's place.
 */

/**
 * Per-render state: headings (md5 of their HTML) whose next block is a filled
 * image box; the company whose box comes next; the automatic slot taken off
 * its heading (put back if no box follows after all); and, for Asset Panda,
 * what the slot drew after the logo (the rating and badges), drawn after the box.
 *
 * @param array|null $set New state.
 * @return array
 */
function ap_content_logo_box_state( $set = null ) {
	static $state = array(
		'heads'   => array(),
		'pending' => '',
		'slot'    => '',
		'tail'    => '',
	);
	if ( null !== $set ) {
		$state = $set;
	}
	return $state;
}

// Priority 8: the blocks are still markup here (do_blocks renders them at 9).
// Whether the design pass draws this post as a ranked list is read later, from
// the heading it prints (class ap26x-rank), so the order of the priority-8
// filters does not matter.
add_filter(
	'the_content',
	function ( $content ) {
		$heads = array();
		if ( ap_content_render_enabled() && is_singular() && in_the_loop() && is_main_query() && false !== strpos( (string) $content, '<!-- wp:image' ) ) {
			$blocks = parse_blocks( (string) $content );
			foreach ( $blocks as $i => $block ) {
				$name = ap_content_block_rank_name( $block );
				if ( '' !== $name && '' !== ap_content_block_image_src( ap_content_next_block( $blocks, $i ) ) ) {
					$heads[ md5( (string) $block['innerHTML'] ) ] = $name;
				}
			}
		}
		ap_content_logo_box_state(
			array(
				'heads'   => $heads,
				'pending' => '',
				'slot'    => '',
				'tail'    => '',
			)
		);
		return $content;
	},
	8
);

/**
 * The shown size of a logo box's image: from the attachment when it is one,
 * from a known logo with the same address, else from the img's own width and
 * height; [ 0, 0 ] when none of those say.
 *
 * @param array  $block Parsed image block.
 * @param string $src   Image address.
 * @return int[]
 */
function ap_content_logo_box_size( array $block, $src ) {
	// A logo the design already has keeps exactly the size it is drawn at without a box.
	$path = (string) wp_parse_url( $src, PHP_URL_PATH );
	foreach ( ap_content_builtin_logos() as $row ) {
		if ( wp_basename( $path ) === $row[0] ) {
			return array( $row[1], $row[2] );
		}
	}
	if ( function_exists( 'ap_content_listicle_logos' ) ) {
		foreach ( ap_content_listicle_logos() as $row ) {
			if ( '' !== $path && str_ends_with( $path, '/' . $row[0] ) ) {
				return array( $row[1], $row[2] );
			}
		}
	}
	$id = (int) ( $block['attrs']['id'] ?? 0 );
	if ( ! $id ) {
		$id = (int) attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $src ) );
	}
	if ( $id ) {
		list( $w, $h ) = ap_content_logo_natural( $id );
		if ( $w > 0 && $h > 0 ) {
			return ap_content_logo_fit( $w, $h );
		}
	}
	$w = preg_match( '/<img\b[^>]*\swidth="(\d+)"/i', (string) $block['innerHTML'], $m ) ? (int) $m[1] : 0;
	$h = preg_match( '/<img\b[^>]*\sheight="(\d+)"/i', (string) $block['innerHTML'], $m ) ? (int) $m[1] : 0;
	return ( $w && $h ) ? ap_content_logo_fit( $w, $h ) : array( 0, 0 );
}

/**
 * Whether a parsed block is one of this feature's logo boxes.
 *
 * @param array $block Parsed block.
 * @return bool
 */
function ap_content_is_logo_box( array $block ) {
	return 'core/image' === ( $block['blockName'] ?? '' )
		&& false !== strpos( ' ' . (string) ( $block['attrs']['className'] ?? '' ) . ' ', ' ' . AP_CONTENT_LOGO_BOX_CLASS . ' ' );
}

add_filter(
	'render_block',
	function ( $html, $block ) {
		$name = $block['blockName'] ?? '';
		if ( '' === (string) $name ) {
			return $html; // The blank text between blocks.
		}
		$st = ap_content_logo_box_state();
		if ( 'core/heading' === $name ) {
			if ( ! $st['heads'] && '' === $st['pending'] ) {
				return $html;
			}
			$key     = md5( (string) ( $block['innerHTML'] ?? '' ) );
			$company = ( isset( $st['heads'][ $key ] ) && false !== strpos( (string) $html, 'ap26x-rank' ) ) ? $st['heads'][ $key ] : '';
			$st      = array_merge(
				$st,
				array(
					'pending' => $company,
					'slot'    => '',
					'tail'    => '',
				)
			);
			// The automatic slot is the last thing the design pass printed after the heading: the logo
			// (or initials) box, and for Asset Panda the rating and badges after it. Its logo box holds no
			// nested <div>, so the first </div> closes it.
			if ( '' !== $company && preg_match( '#^(.*</h2>)\s*(<div class="ap26x-logo(?: ap26x-logo--(?:mono|brand))?"[^>]*>.*?</div>)(.*)$#s', (string) $html, $m ) ) {
				$html       = $m[1];
				$st['slot'] = $m[2] . $m[3];
				$st['tail'] = ap_content_logo_is_ap( $company ) ? $m[3] : '';
			}
			ap_content_logo_box_state( $st );
			return $html;
		}
		$company = $st['pending'];
		$slot    = $st['slot'];
		$tail    = $st['tail'];
		if ( '' !== $company ) {
			ap_content_logo_box_state(
				array_merge(
					$st,
					array(
						'pending' => '',
						'slot'    => '',
						'tail'    => '',
					)
				)
			);
		}
		$src = 'core/image' === $name ? ap_content_block_image_src( $block ) : '';
		if ( '' !== $company && '' === $src ) {
			// The box expected under this heading is not there after all: give the heading its slot back.
			return $slot . $html;
		}
		if ( '' === $company && ! ap_content_is_logo_box( $block ) ) {
			return $html;
		}
		if ( '' === $src ) {
			return ''; // An empty box draws nothing.
		}
		// A logo box is drawn at logo size wherever it is, also when the page is not (yet) drawn as a
		// ranked list (a draft without a title, a preview): never as a full-width picture.
		$alt           = preg_match( '/<img\b[^>]*\salt="([^"]*)"/i', (string) $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : '';
		list( $w, $h ) = ap_content_logo_box_size( $block, $src );
		$size          = ( $w && $h ) ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : ' style="width:auto;max-height:96px"';
		$brand         = '' !== $company && ap_content_logo_is_ap( $company );
		$fallback      = '' !== $company ? $company . ' logo' : 'Logo';
		return '<div class="ap26x-logo' . ( $brand ? ' ap26x-logo--brand' : '' ) . '"><img class="ap26x-logo__img" src="' . esc_url( $src ) . '"' . $size
			. ' alt="' . esc_attr( '' !== trim( $alt ) ? $alt : $fallback ) . '" loading="lazy" decoding="async" /></div>' . $tail;
	},
	11,
	2
);

// The state ends with the post body (the design pass closes at the_content 11).
add_filter(
	'the_content',
	function ( $content ) {
		ap_content_logo_box_state(
			array(
				'heads'   => array(),
				'pending' => '',
				'slot'    => '',
				'tail'    => '',
			)
		);
		return $content;
	},
	12
);

/* 3. Editor: logo-size boxes and the "Add logo boxes" notice. */
add_action(
	'enqueue_block_assets',
	function () {
		if ( ! ap_content_render_enabled() || ! function_exists( 'ap_content_editor_post' ) || ! ap_content_editor_post() ) {
			return;
		}
		$c = '.editor-styles-wrapper .wp-block-image.' . AP_CONTENT_LOGO_BOX_CLASS;
		wp_register_style( 'ap-content-logo-boxes', false, array(), AP_CONTENT_VERSION );
		wp_enqueue_style( 'ap-content-logo-boxes' );
		wp_add_inline_style(
			'ap-content-logo-boxes',
			$c . '{margin:20px auto 18px;text-align:center}'
			. $c . ' img{display:inline-block;width:auto;max-width:min(100%,370px);max-height:96px;height:auto}'
			. $c . ' .components-placeholder{min-height:0;max-width:520px;margin:0 auto}'
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		$post = function_exists( 'ap_content_editor_post' ) ? ap_content_editor_post() : null;
		$path = AP_CONTENT_DIR . '/assets/logo-boxes.js';
		if ( ! ap_content_render_enabled() || ! $post || ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'ap-content-logo-boxes',
			plugins_url( 'assets/logo-boxes.js', AP_CONTENT_FILE ),
			array( 'wp-data', 'wp-blocks', 'wp-api-fetch', 'wp-notices', 'wp-block-editor', 'wp-editor', 'wp-dom-ready' ),
			AP_CONTENT_VERSION . '.' . filemtime( $path ),
			true
		);
		wp_add_inline_script( 'ap-content-logo-boxes', 'window.AP_LOGO_BOXES = ' . wp_json_encode( array( 'boxClass' => AP_CONTENT_LOGO_BOX_CLASS ) ) . ';', 'before' );
	}
);

/* REST for the editor: the logo shown today for each company name. */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap-content/v1',
			'/logos',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'names' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'required' => true,
					),
				),
				'callback'            => function ( WP_REST_Request $req ) {
					$out = array();
					foreach ( array_slice( (array) $req['names'], 0, 80 ) as $name ) {
						$name = sanitize_text_field( (string) $name );
						if ( '' !== $name ) {
							$logo         = ap_content_logo_lookup( $name );
							$out[ $name ] = $logo ? $logo['url'] : '';
						}
					}
					return rest_ensure_response( array( 'logos' => (object) $out ) );
				},
			)
		);
	}
);
