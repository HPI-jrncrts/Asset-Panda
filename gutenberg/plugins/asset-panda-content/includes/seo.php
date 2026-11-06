<?php
/**
 * SEO: archive SEO storage in Yoast's own options, and a fallback that prints
 * <title>, meta description, canonical, robots and Open Graph tags from Yoast's
 * keys while Yoast is not active. It stands down by itself when Yoast runs
 * (WPSEO_VERSION), checked per request after all plugins have loaded.
 *
 * Storage (identical to Yoast's, so Yoast prints it when active):
 *   singular:            post meta _yoast_wpseo_title / _metadesc / _canonical / _meta-robots-*
 *   term archive:        option wpseo_taxonomy_meta[<taxonomy>][<term_id>] wpseo_title / wpseo_desc / wpseo_canonical / wpseo_noindex
 *   post type archive:   option wpseo_titles title-ptarchive-<type> / metadesc-ptarchive-<type> / noindex-ptarchive-<type>
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** True when Yoast SEO (or a filter) owns the head. */
function ap_content_yoast_active() {
	return defined( 'WPSEO_VERSION' ) || ( defined( 'AP_CONTENT_DISABLE_SEO' ) && AP_CONTENT_DISABLE_SEO ) || (bool) apply_filters( 'ap_content_seo_disabled', false );
}

/* ── Archive SEO in Yoast's storage ──────────────────────────────────── */

/**
 * Parses an archive target.
 *
 * @param string $target "resource-types:<slug>", "category:<slug>", "resource" or "newsroom".
 * @return array|WP_Error {kind: term|pt, taxonomy?, term?, post_type?}
 */
function ap_content_archive_target( $target ) {
	$target = trim( (string) $target );
	if ( in_array( $target, array( 'resource', 'newsroom' ), true ) ) {
		return array(
			'kind'      => 'pt',
			'post_type' => $target,
		);
	}
	if ( preg_match( '/^(resource-types|category):([a-z0-9_-]+)$/', $target, $m ) ) {
		$term = get_term_by( 'slug', $m[2], $m[1] );
		if ( ! $term ) {
			return new WP_Error( 'ap_content_bad_target', 'No ' . $m[1] . ' term "' . $m[2] . '".', array( 'status' => 400 ) );
		}
		return array(
			'kind'     => 'term',
			'taxonomy' => $m[1],
			'term'     => $term,
		);
	}
	return new WP_Error( 'ap_content_bad_target', 'target must be resource-types:<slug>, category:<slug>, resource or newsroom.', array( 'status' => 400 ) );
}

/**
 * Reads archive SEO.
 *
 * @param array $t Parsed target.
 * @return array{title: string, description: string, canonical: string, noindex: bool, heading: string}
 */
function ap_content_archive_seo_get( $t ) {
	$out = array(
		'title'       => '',
		'description' => '',
		'canonical'   => '',
		'noindex'     => false,
		'heading'     => '',
	);
	if ( 'term' === $t['kind'] ) {
		$all = get_option( 'wpseo_taxonomy_meta' );
		$row = ( is_array( $all ) && isset( $all[ $t['taxonomy'] ][ $t['term']->term_id ] ) ) ? (array) $all[ $t['taxonomy'] ][ $t['term']->term_id ] : array();
		$out['title']       = isset( $row['wpseo_title'] ) ? (string) $row['wpseo_title'] : '';
		$out['description'] = isset( $row['wpseo_desc'] ) ? (string) $row['wpseo_desc'] : '';
		$out['canonical']   = isset( $row['wpseo_canonical'] ) ? (string) $row['wpseo_canonical'] : '';
		$out['noindex']     = isset( $row['wpseo_noindex'] ) && 'noindex' === $row['wpseo_noindex'];
		$out['heading']     = (string) get_term_meta( $t['term']->term_id, 'ap_heading', true );
	} else {
		$titles             = get_option( 'wpseo_titles' );
		$titles             = is_array( $titles ) ? $titles : array();
		$pt                 = $t['post_type'];
		$out['title']       = isset( $titles[ 'title-ptarchive-' . $pt ] ) ? (string) $titles[ 'title-ptarchive-' . $pt ] : '';
		$out['description'] = isset( $titles[ 'metadesc-ptarchive-' . $pt ] ) ? (string) $titles[ 'metadesc-ptarchive-' . $pt ] : '';
		$out['noindex']     = ! empty( $titles[ 'noindex-ptarchive-' . $pt ] );
		$headings           = get_option( 'ap_content_archive_headings' );
		$out['heading']     = ( is_array( $headings ) && isset( $headings[ $pt ] ) ) ? (string) $headings[ $pt ] : '';
	}
	return $out;
}

/**
 * Writes archive SEO into Yoast's storage (only the fields given).
 *
 * @param array $t    Parsed target.
 * @param array $data title, description, canonical, noindex, heading.
 * @return array The stored values.
 */
function ap_content_archive_seo_set( $t, $data ) {
	if ( 'term' === $t['kind'] ) {
		$all = get_option( 'wpseo_taxonomy_meta' );
		$all = is_array( $all ) ? $all : array();
		$tid = (int) $t['term']->term_id;
		$row = isset( $all[ $t['taxonomy'] ][ $tid ] ) ? (array) $all[ $t['taxonomy'] ][ $tid ] : array();
		if ( array_key_exists( 'title', $data ) ) {
			$row['wpseo_title'] = sanitize_text_field( (string) $data['title'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$row['wpseo_desc'] = sanitize_text_field( (string) $data['description'] );
		}
		if ( array_key_exists( 'canonical', $data ) ) {
			$row['wpseo_canonical'] = esc_url_raw( (string) $data['canonical'] );
		}
		if ( array_key_exists( 'noindex', $data ) ) {
			$row['wpseo_noindex'] = $data['noindex'] ? 'noindex' : 'default';
		}
		$all[ $t['taxonomy'] ][ $tid ] = $row;
		update_option( 'wpseo_taxonomy_meta', $all );
		if ( array_key_exists( 'heading', $data ) ) {
			update_term_meta( $tid, 'ap_heading', sanitize_text_field( (string) $data['heading'] ) );
		}
		// Yoast rebuilds a term's stored SEO data on edited_term (its admin
		// save runs on edit_term, which is not fired here).
		if ( defined( 'WPSEO_VERSION' ) ) {
			do_action( 'edited_term', $tid, (int) $t['term']->term_taxonomy_id, $t['taxonomy'], array() );
		}
	} else {
		$pt     = $t['post_type'];
		$titles = get_option( 'wpseo_titles' );
		$titles = is_array( $titles ) ? $titles : array();
		if ( array_key_exists( 'title', $data ) ) {
			$titles[ 'title-ptarchive-' . $pt ] = sanitize_text_field( (string) $data['title'] );
		}
		if ( array_key_exists( 'description', $data ) ) {
			$titles[ 'metadesc-ptarchive-' . $pt ] = sanitize_text_field( (string) $data['description'] );
		}
		if ( array_key_exists( 'noindex', $data ) ) {
			$titles[ 'noindex-ptarchive-' . $pt ] = (bool) $data['noindex'];
		}
		update_option( 'wpseo_titles', $titles );
		if ( array_key_exists( 'heading', $data ) ) {
			$headings        = get_option( 'ap_content_archive_headings' );
			$headings        = is_array( $headings ) ? $headings : array();
			$headings[ $pt ] = sanitize_text_field( (string) $data['heading'] );
			update_option( 'ap_content_archive_headings', $headings, false );
		}
	}
	return ap_content_archive_seo_get( $t );
}

/*
 * Contract 1.0 told the importer to put /resource-center/'s SEO on page 491
 * (the page at path resource-center). That address is the resource listing,
 * so the page's Yoast title/description are mirrored to the listing.
 */
function ap_content_mirror_hub_meta( $meta_id, $post_id, $meta_key, $value ) {
	if ( ! in_array( $meta_key, array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ), true ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'page' !== $post->post_type || 'resource-center' !== get_page_uri( $post ) ) {
		return;
	}
	$key = '_yoast_wpseo_title' === $meta_key ? 'title' : 'description';
	ap_content_archive_seo_set(
		array(
			'kind'      => 'pt',
			'post_type' => 'resource',
		),
		array( $key => is_scalar( $value ) ? (string) $value : '' )
	);
}
add_action( 'added_post_meta', 'ap_content_mirror_hub_meta', 10, 4 );
add_action( 'updated_post_meta', 'ap_content_mirror_hub_meta', 10, 4 );

/* ── JSON-LD carried over from old page bodies (1.1) ─────────────────── */

/*
 * `_ap_jsonld` (a JSON array of schema objects) prints in <head>, one
 * <script type="application/ld+json"> per object, separate from Yoast's graph
 * (live prints these blocks on their own, beside Yoast's). Printed whether or
 * not Yoast is active. Only valid JSON is printed; every object is re-encoded
 * with JSON_HEX_TAG, so "</script>" or "<!--" inside a string cannot end the tag.
 */
add_action(
	'wp_head',
	function () {
		if ( ! is_singular() || ! (bool) apply_filters( 'ap_content_jsonld_enabled', true ) ) {
			return;
		}
		try {
			$id = get_queried_object_id();
			if ( ! $id || ! in_array( get_post_type( $id ), array( 'page', 'post', 'resource', 'newsroom' ), true ) ) {
				return;
			}
			$list = ap_content_parse_jsonld( (string) get_post_meta( $id, '_ap_jsonld', true ) );
			if ( is_wp_error( $list ) || ! $list ) {
				return;
			}
			$out = '';
			foreach ( $list as $obj ) {
				$json = wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
				if ( is_string( $json ) && '' !== $json ) {
					$out .= '<script type="application/ld+json" class="ap-content-jsonld">' . $json . '</script>' . "\n";
				}
			}
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and > hex-escaped.
		} catch ( \Throwable $e ) {
			return;
		}
	},
	30
);

/* ── Fallback head (only while Yoast is not active) ──────────────────── */

add_action(
	'wp',
	function () {
		if ( is_admin() || ap_content_yoast_active() ) {
			return;
		}
		add_filter( 'pre_get_document_title', 'ap_content_seo_document_title', 20 );
		add_filter( 'wp_robots', 'ap_content_seo_robots', 20 );
		add_action( 'wp_head', 'ap_content_seo_head', 2 );
		if ( is_singular() ) {
			// The canonical is printed by ap_content_seo_head().
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}
);

/**
 * Replaces the common Yoast variables in a stored title or description.
 *
 * @param string $text Text.
 * @param array  $vars Values.
 * @return string
 */
function ap_content_seo_vars( $text, $vars ) {
	if ( false === strpos( $text, '%%' ) ) {
		return $text;
	}
	$text = str_replace( array_map( function ( $k ) { return '%%' . $k . '%%'; }, array_keys( $vars ) ), array_values( $vars ), $text );
	$text = preg_replace( '/%%[a-z0-9_]+%%/i', '', $text );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * What the current request should print. Cached per request.
 *
 * @return array{title: string, description: string, canonical: string, noindex: bool, nofollow: bool, adv: string, og_type: string, og_title: string, og_description: string, og_image: string}
 */
function ap_content_seo_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	$data = array(
		'title'          => '',
		'description'    => '',
		'canonical'      => '',
		'noindex'        => false,
		'nofollow'       => false,
		'adv'            => '',
		'og_type'        => 'website',
		'og_title'       => '',
		'og_description' => '',
		'og_image'       => '',
	);
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$vars  = array(
		'sitename' => get_bloginfo( 'name' ),
		'sitedesc' => get_bloginfo( 'description' ),
		'sep'      => '-',
		'page'     => $paged > 1 ? 'Page ' . $paged : '',
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $data;
		}
		$id              = $post->ID;
		$vars['title']   = wp_strip_all_tags( get_the_title( $post ) );
		$vars['excerpt'] = wp_strip_all_tags( $post->post_excerpt );
		$data['title']          = ap_content_seo_vars( (string) get_post_meta( $id, '_yoast_wpseo_title', true ), $vars );
		$data['description']    = ap_content_seo_vars( (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ), $vars );
		$canonical              = (string) get_post_meta( $id, '_yoast_wpseo_canonical', true );
		$data['canonical']      = '' !== $canonical ? $canonical : (string) wp_get_canonical_url( $post );
		$data['noindex']        = '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true );
		$data['nofollow']       = '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-nofollow', true );
		$data['adv']            = (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-adv', true );
		$data['og_type']        = 'page' === $post->post_type ? 'website' : 'article';
		$data['og_title']       = (string) get_post_meta( $id, '_yoast_wpseo_opengraph-title', true );
		$data['og_description'] = (string) get_post_meta( $id, '_yoast_wpseo_opengraph-description', true );
		$image                  = (string) get_post_meta( $id, '_yoast_wpseo_opengraph-image', true );
		if ( '' === $image && has_post_thumbnail( $post ) ) {
			$thumb = (int) get_post_thumbnail_id( $post );
			$image = $thumb ? (string) wp_get_attachment_image_url( $thumb, 'full' ) : ap_content_external_image( $id );
		}
		$data['og_image'] = $image;
	} elseif ( is_tax( 'resource-types' ) || is_category() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$seo                 = ap_content_archive_seo_get(
				array(
					'kind'     => 'term',
					'taxonomy' => $term->taxonomy,
					'term'     => $term,
				)
			);
			$vars['term_title']  = $term->name;
			$data['title']       = ap_content_seo_vars( $seo['title'], $vars );
			$data['description'] = ap_content_seo_vars( $seo['description'], $vars );
			$data['noindex']     = $seo['noindex'];
			$data['canonical']   = '' !== $seo['canonical'] ? $seo['canonical'] : ( $paged > 1 ? get_pagenum_link( $paged ) : (string) get_term_link( $term ) );
		}
	} elseif ( is_post_type_archive( array( 'resource', 'newsroom' ) ) ) {
		$pt                  = is_post_type_archive( 'resource' ) ? 'resource' : 'newsroom';
		$seo                 = ap_content_archive_seo_get(
			array(
				'kind'      => 'pt',
				'post_type' => $pt,
			)
		);
		$obj                 = get_post_type_object( $pt );
		$vars['pt_plural']   = $obj ? $obj->labels->name : '';
		$data['title']       = ap_content_seo_vars( $seo['title'], $vars );
		$data['description'] = ap_content_seo_vars( $seo['description'], $vars );
		$data['noindex']     = $seo['noindex'];
		$data['canonical']   = $paged > 1 ? get_pagenum_link( $paged ) : (string) get_post_type_archive_link( $pt );
	}
	$data = (array) apply_filters( 'ap_content_seo_data', $data );
	return $data;
}

/**
 * pre_get_document_title.
 *
 * @param string $title Title.
 * @return string
 */
function ap_content_seo_document_title( $title ) {
	try {
		$seo = ap_content_seo_data();
		return '' !== $seo['title'] ? $seo['title'] : $title;
	} catch ( \Throwable $e ) {
		return $title;
	}
}

/**
 * wp_robots.
 *
 * @param array $robots Directives.
 * @return array
 */
function ap_content_seo_robots( $robots ) {
	try {
		$seo = ap_content_seo_data();
		if ( $seo['noindex'] ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		if ( $seo['nofollow'] ) {
			$robots['nofollow'] = true;
		}
		foreach ( array_filter( array_map( 'trim', explode( ',', $seo['adv'] ) ) ) as $flag ) {
			if ( in_array( $flag, array( 'noimageindex', 'noarchive', 'nosnippet' ), true ) ) {
				$robots[ $flag ] = true;
			}
		}
	} catch ( \Throwable $e ) {
		return $robots;
	}
	return $robots;
}

/** wp_head: description, canonical and Open Graph. */
function ap_content_seo_head() {
	try {
		$seo = ap_content_seo_data();
	} catch ( \Throwable $e ) {
		return;
	}
	$out = "\n<!-- Asset Panda Content SEO (Yoast inactive) -->\n";
	if ( '' !== $seo['description'] ) {
		$out .= '<meta name="description" content="' . esc_attr( $seo['description'] ) . '" />' . "\n";
	}
	if ( '' !== $seo['canonical'] && ! $seo['noindex'] ) {
		$out .= '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '" />' . "\n";
	}
	$title = '' !== $seo['og_title'] ? $seo['og_title'] : ( '' !== $seo['title'] ? $seo['title'] : wp_get_document_title() );
	$desc  = '' !== $seo['og_description'] ? $seo['og_description'] : $seo['description'];
	$out  .= '<meta property="og:type" content="' . esc_attr( $seo['og_type'] ) . '" />' . "\n";
	$out  .= '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	if ( '' !== $desc ) {
		$out .= '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}
	if ( '' !== $seo['canonical'] ) {
		$out .= '<meta property="og:url" content="' . esc_url( $seo['canonical'] ) . '" />' . "\n";
	}
	if ( '' !== $seo['og_image'] ) {
		$out .= '<meta property="og:image" content="' . esc_url( $seo['og_image'] ) . '" />' . "\n";
	}
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Alt text for images in a page's own content (crawl fix, 2026-11-04): images
 * written into post content (an article's figures, a case study's fact icons,
 * raw-HTML sections) with an empty alt. Kit sections are handled by the kit
 * (ap_ui_alt_pass). For each empty alt, in order:
 *  1. the Media Library alt of the attachment (wp-image-N, or the file);
 *  2. the image's own caption;
 *  3. otherwise a label from where it sits (the heading or label right after
 *     it, else the heading before it, else the page title) with
 *     aria-hidden="true", so screen readers still skip it as they did.
 * Never the file name; alts that are set are left alone.
 *
 * @param string $content Rendered content.
 * @return string
 */
function ap_content_alt_pass( $content ) {
	if ( ! is_string( $content ) || false === stripos( $content, '<img' ) || is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_singular() ) {
		return $content;
	}
	$empty = '#<img\b(?![^>]*(?<![\w-])alt\s*=\s*(["\'])\s*[^"\'\s])[^>]*>#i';
	if ( ! preg_match( $empty, $content ) ) {
		return $content;
	}
	$clean = static function ( $html ) {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( preg_match( '/^(.{120}).+$/us', $text, $cut ) ) {
			$text = (string) preg_replace( '/[\s,.;:\x{2013}\x{2014}-]+$/u', '', (string) preg_replace( '/\s+\S*$/u', '', $cut[1] ) );
		}
		return $text;
	};
	$get   = static function ( $tag, $name ) {
		return preg_match( '/(?<![\w-])' . $name . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? $m[2] : null;
	};
	$title = $clean( get_the_title( get_queried_object_id() ) );

	$out = preg_replace_callback(
		'#<img\b[^>]*>#i',
		static function ( $m ) use ( $content, $clean, $get, $title ) {
			$tag = $m[0][0];
			$at  = $m[0][1];
			$alt = $get( $tag, 'alt' );
			if ( null !== $alt && '' !== trim( $alt ) ) {
				return $tag;
			}
			$text = '';
			if ( preg_match( '/\bwp-image-(\d+)\b/', $tag, $id ) ) {
				$text = trim( (string) get_post_meta( (int) $id[1], '_wp_attachment_image_alt', true ) );
			}
			$src = html_entity_decode( (string) $get( $tag, 'src' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' === $text && '' !== $src && function_exists( 'ap_ui_media_alts' ) ) {
				$found = ap_ui_media_alts( array( $src ) );
				$text  = isset( $found[ $src ] ) ? $found[ $src ] : '';
			}
			if ( '' === $text ) {
				// The caption of the figure the image is in.
				$before = substr( $content, 0, $at );
				$open   = strripos( $before, '<figure' );
				$shut   = strripos( $before, '</figure>' );
				if ( false !== $open && ( false === $shut || $shut < $open ) ) {
					$end = stripos( $content, '</figure>', $at );
					if ( false !== $end && preg_match( '#<figcaption\b[^>]*>(.*?)</figcaption>#is', substr( $content, $at, $end - $at ), $cap ) ) {
						$text = $clean( $cap[1] );
					}
				}
			}
			$hide = '' === $text;
			if ( $hide ) {
				$after = substr( $content, $at + strlen( $tag ), 300 );
				if ( preg_match( '#<(h[1-6]|strong|b)\b[^>]*>(.*?)</\1>#is', $after, $next ) && false === stripos( substr( $after, 0, strpos( $after, $next[0] ) ), '<p' ) ) {
					$text = $clean( $next[2] );
				}
				if ( '' === $text && preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1>#is', substr( $content, 0, $at ), $heads ) ) {
					$text = $clean( end( $heads[2] ) );
				}
				if ( '' === $text ) {
					$text = '' !== $title ? $title : 'Asset Panda';
				}
			}
			$alt_attr = 'alt="' . esc_attr( $text ) . '"';
			if ( null !== $alt ) {
				$tag = preg_replace_callback(
					'/(?<![\w-])alt\s*=\s*(["\'])(.*?)\1/is',
					static function () use ( $alt_attr ) {
						return $alt_attr;
					},
					$tag,
					1
				);
			} else {
				$tag = preg_replace_callback(
					'#\s*(/?)>$#',
					static function ( $end ) use ( $alt_attr ) {
						return ' ' . $alt_attr . ( '' !== $end[1] ? ' />' : '>' );
					},
					$tag,
					1
				);
			}
			if ( $hide && ! preg_match( '/\baria-hidden\s*=/i', $tag ) ) {
				$tag = preg_replace( '/^<img\b/i', '<img aria-hidden="true"', $tag, 1 );
			}
			return $tag;
		},
		$content,
		-1,
		$count,
		PREG_OFFSET_CAPTURE
	);
	return is_string( $out ) ? $out : $content;
}
add_filter( 'the_content', 'ap_content_alt_pass', 40 );
