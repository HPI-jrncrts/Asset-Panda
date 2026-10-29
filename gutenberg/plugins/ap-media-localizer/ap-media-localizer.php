<?php
/**
 * Plugin Name:       Asset Panda Media Localizer
 * Description:       Gives the new site its own copy of every file it uses from www.assetpanda.com. An administrator sends a list of live file addresses to the REST route; each file is copied into this site's uploads folder at the SAME relative path (so the address is still right after the domain moves), JPG/GIF images (and heavy PNGs) get a WebP copy beside them, and the pages are served with the local copies. Images the pages serve without a WebP or phone-size copy get one made in the background, and a page whose first screen has no image preload gets one for its hero image. Settings live in the option ap_media_localizer.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       ap-media-localizer
 *
 * How addresses map (ap_media_rel_path()):
 * - https://www.assetpanda.com/wp-content/uploads/<rel>  →  <uploads>/<rel>
 * - https://www.assetpanda.com/wp-content/<other>/<rel>  →  <uploads>/ap-localized/<other>/<rel>
 *   (theme and webp-express files have no home on the new site otherwise)
 *
 * Front end (ap_media_rewrite_html()): on page views, every www.assetpanda.com/wp-content/ address in the
 * HTML whose local copy exists is pointed at this site — plain and JSON-escaped (\/) forms, src, srcset,
 * inline CSS and data attributes alike — and a JPG/GIF/PNG is swapped for its .webp copy when one exists.
 * Root-relative addresses (/wp-content/uploads/…, the form the content uses since the relink) get the same
 * WebP swap and keep their relative form. Addresses without a local copy are left alone, so nothing turns
 * blank. After cutover (home = www) the host part is a no-op and only the WebP swap remains.
 *
 * Companions (ap_media_make_companions()): a raster the pages serve without its .webp (JPG/GIF, PNG over
 * png_webp_min) or, when wider than ~1000px, without its 828px phone copy is queued and made by a WP-Cron
 * event a minute later (or at once with POST /ap-media/v1/companions). Pages pick the copies up on the
 * next view. Nothing is generated during a page view.
 *
 * First-screen preload (ap_media_preload_lcp()): when a page carries no image preload, the first
 * high-priority image after <main> (the hero's own fetchpriority="high" image, or the first inline
 * background-image) is preloaded from <head>, with the same srcset/sizes as the <img>, so the browser
 * starts it before it has parsed the stylesheets and the header.
 *
 * Safety: nothing runs on activation; the REST routes need manage_options; only the live host's
 * /wp-content/ files with an allowed extension are fetched; SVGs containing script are refused;
 * every hook is wrapped so an error never breaks a page. Kill switch: define( 'AP_MEDIA_DISABLE', true ).
 *
 * @package AssetPandaMediaLocalizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_MEDIA_VERSION', '1.2.0' );
define( 'AP_MEDIA_OPTION', 'ap_media_localizer' );

/** Live hosts whose /wp-content/ files are copied. */
function ap_media_source_hosts() {
	return array( 'www.assetpanda.com', 'assetpanda.com' );
}

/** File types that may be copied, with their size cap in bytes. */
function ap_media_allowed_exts() {
	return array(
		'png'   => 15 * MB_IN_BYTES,
		'jpg'   => 15 * MB_IN_BYTES,
		'jpeg'  => 15 * MB_IN_BYTES,
		'gif'   => 15 * MB_IN_BYTES,
		'webp'  => 15 * MB_IN_BYTES,
		'avif'  => 15 * MB_IN_BYTES,
		'svg'   => 5 * MB_IN_BYTES,
		'ico'   => 1 * MB_IN_BYTES,
		'pdf'   => 40 * MB_IN_BYTES,
		'mp4'   => 60 * MB_IN_BYTES,
		'webm'  => 60 * MB_IN_BYTES,
		'woff2' => 2 * MB_IN_BYTES,
		'woff'  => 2 * MB_IN_BYTES,
		'json'  => 5 * MB_IN_BYTES,
	);
}

/** Settings with defaults. */
function ap_media_settings() {
	$saved = get_option( AP_MEDIA_OPTION, array() );
	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'rewrite'       => true,  // Point page HTML at the local copies.
			'webp_swap'     => true,  // Serve the .webp copy of JPG/GIF/PNG when it exists.
			'responsive'    => true,  // Add phone-size srcset candidates to big images (see ap_media_responsive_html).
			'companions'    => true,  // Make missing WebP / phone copies in the background (see ap_media_make_companions).
			'preload'       => true,  // Preload the first screen's hero image when the page has no image preload.
			'png_webp_min'  => 150 * KB_IN_BYTES, // PNGs at least this big get a WebP copy.
			'webp_quality'  => 82,
		)
	);
}

/** Logs without interrupting the request. */
function ap_media_log( $message ) {
	error_log( '[ap-media-localizer] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * The uploads-relative path a live /wp-content/ address maps to, or '' when it is not one we copy.
 *
 * @param string $url Absolute live address (query string allowed).
 * @return string Relative path under the uploads base dir, without a leading slash.
 */
function ap_media_rel_path( $url ) {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return '';
	}
	if ( ! in_array( strtolower( $parts['host'] ), ap_media_source_hosts(), true ) ) {
		return '';
	}
	$path = rawurldecode( $parts['path'] );
	if ( 0 !== strpos( $path, '/wp-content/' ) || false !== strpos( $path, '..' ) || false !== strpos( $path, "\0" ) ) {
		return '';
	}
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( ! isset( ap_media_allowed_exts()[ $ext ] ) ) {
		return '';
	}
	if ( 0 === strpos( $path, '/wp-content/uploads/' ) ) {
		return substr( $path, strlen( '/wp-content/uploads/' ) );
	}
	return 'ap-localized/' . substr( $path, strlen( '/wp-content/' ) );
}

/**
 * Per-request lookup caches (address → served address, SVG → raster). One PHP request serves one page, so a file
 * that exists at the start of the request exists at the end; ap_media_reset_caches() is for long-running processes
 * (tests, WP-CLI) that make files and then render again.
 *
 * @param string $bucket Cache name.
 * @return array Reference to the bucket.
 */
function &ap_media_cache( $bucket ) {
	if ( ! isset( $GLOBALS['ap_media_cache'][ $bucket ] ) ) {
		$GLOBALS['ap_media_cache'][ $bucket ] = array();
	}
	return $GLOBALS['ap_media_cache'][ $bucket ];
}

/** Forgets every per-request lookup. */
function ap_media_reset_caches() {
	$GLOBALS['ap_media_cache'] = array();
}

/** Absolute local file path for an uploads-relative path. */
function ap_media_local_file( $rel ) {
	$up = wp_get_upload_dir();
	return trailingslashit( $up['basedir'] ) . $rel;
}

/** Public URL for an uploads-relative path (rawurlencoded per segment). */
function ap_media_local_url_for_rel( $rel ) {
	$up = wp_get_upload_dir();
	return trailingslashit( $up['baseurl'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) );
}

/** The WebP companion's uploads-relative path for a raster file. */
function ap_media_webp_rel( $rel ) {
	return $rel . '.webp';
}

/**
 * Local address for a live (or local) file address, or the address unchanged when there is no local copy.
 * Other code (templates, REST indexes) may call this directly.
 *
 * @param string $url Address.
 * @return string
 */
function ap_media_local_url( $url ) {
	$cache = &ap_media_cache( 'url' );
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$out = $url;
	$rel = ap_media_rel_path( $url );
	if ( '' === $rel ) {
		// A file already on this site: only the WebP swap applies. The address is matched without its scheme
		// (http/https/protocol-relative all count) and written back with whatever prefix the author used.
		$up       = wp_get_upload_dir();
		$base     = trailingslashit( $up['baseurl'] );
		$bare     = (string) strtok( $url, '?#' );
		$noscheme = static function ( $u ) {
			return preg_replace( '#^https?:#i', '', (string) $u );
		};
		$rel_raw  = '';
		if ( 0 === strpos( $noscheme( $bare ), $noscheme( $base ) ) ) {
			$rel_raw = substr( $noscheme( $bare ), strlen( $noscheme( $base ) ) );
		} elseif ( '/' === substr( $bare, 0, 1 ) && 0 === strpos( $bare, wp_make_link_relative( $base ) ) ) {
			$rel_raw = substr( $bare, strlen( wp_make_link_relative( $base ) ) );
		} else {
			$cache[ $url ] = $url;
			return $url;
		}
		$rel = rawurldecode( (string) $rel_raw );
		if ( '' === $rel || false !== strpos( $rel, '..' ) ) {
			$cache[ $url ] = $url;
			return $url;
		}
		$swap = ap_media_webp_choice( $rel );
		if ( '' === $swap && ap_media_is_raster( $rel ) ) {
			ap_media_note_companion( $rel );
		}
		$cache[ $url ] = $swap ? substr( $bare, 0, strlen( $bare ) - strlen( $rel_raw ) ) . implode( '/', array_map( 'rawurlencode', explode( '/', $swap ) ) ) : $url;
		return $cache[ $url ];
	}
	if ( file_exists( ap_media_local_file( $rel ) ) ) {
		$swap = ap_media_webp_choice( $rel );
		if ( '' === $swap && ap_media_is_raster( $rel ) ) {
			ap_media_note_companion( $rel );
		}
		$out = ap_media_local_url_for_rel( $swap ? $swap : $rel );
	} else {
		ap_media_note_miss( $url );
	}
	$cache[ $url ] = $out;
	return $out;
}

/**
 * Remembers a live file a page used that has no local copy yet, so an administrator can copy it
 * (GET /ap-media/v1/misses → POST /localize). Saved once per request, capped at 5,000 entries.
 *
 * @param string $url Live address.
 */
function ap_media_note_miss( $url ) {
	static $hooked = false;
	$GLOBALS['ap_media_misses'][ strtok( $url, '?#' ) ] = true;
	if ( $hooked ) {
		return;
	}
	$hooked = true;
	add_action(
		'shutdown',
		static function () {
			if ( empty( $GLOBALS['ap_media_misses'] ) ) {
				return;
			}
			$saved = get_option( 'ap_media_misses', array() );
			$saved = is_array( $saved ) ? $saved : array();
			$new   = array_diff( array_keys( $GLOBALS['ap_media_misses'] ), $saved );
			if ( $new ) {
				update_option( 'ap_media_misses', array_slice( array_merge( $saved, array_values( $new ) ), -5000 ), false );
			}
		}
	);
}

/** Whether a path is a JPG/GIF/PNG — the formats that get a WebP copy. */
function ap_media_is_raster( $rel ) {
	return in_array( strtolower( pathinfo( (string) $rel, PATHINFO_EXTENSION ) ), array( 'jpg', 'jpeg', 'gif', 'png' ), true );
}

/** The WebP companion to serve instead of $rel, or ''. */
function ap_media_webp_choice( $rel ) {
	$s = ap_media_settings();
	if ( empty( $s['webp_swap'] ) ) {
		return '';
	}
	$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'gif', 'png' ), true ) ) {
		return '';
	}
	$webp = ap_media_webp_rel( $rel );
	return file_exists( ap_media_local_file( $webp ) ) ? $webp : '';
}

/**
 * Rewrites every live /wp-content/ address (plain or JSON-escaped) and every local raster with a WebP copy.
 *
 * Addresses come in three shapes and all three are handled: absolute (https://host/wp-content/…),
 * protocol-relative (//host/wp-content/…) and root-relative (/wp-content/…). A root-relative address
 * is only taken when it stands on its own (after a quote, bracket, space, = or comma) so the path part of
 * some other host's address is never mistaken for one, and it is written back root-relative.
 *
 * @param string $html Page HTML.
 * @return string
 */
function ap_media_rewrite_html( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	$up       = wp_get_upload_dir();
	$parts    = wp_parse_url( $up['baseurl'] );
	$host     = isset( $parts['host'] ) ? $parts['host'] : '';
	$origin   = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $host;
	$base     = preg_quote( $host, '#' );
	$hosts    = implode( '|', array_map( static function ( $h ) { return preg_quote( $h, '#' ); }, ap_media_source_hosts() ) );
	$sep      = '(?:/|\\\\/)'; // "/" or JSON's "\/".
	$pattern  = '#(?:(?:https?:)?' . $sep . $sep . '(' . $hosts . ( $base ? '|' . $base : '' ) . ')|(?<![\w:./\\\\-]))((?:' . $sep . ')wp-content(?:' . $sep . ')[^"\'\s<>()\\\\,&]*(?:\\\\/[^"\'\s<>()\\\\,&]*)*)#i'; // Stops at & so entity-quoted CSS (url(&quot;…&quot;)) ends cleanly.
	// <meta> tags (og:image, twitter:image) keep the original file: social cards are read by other services.
	$metas = array();
	$html  = preg_replace_callback(
		'#<meta\b[^>]*>#i',
		static function ( $m ) use ( &$metas ) {
			$metas[] = $m[0];
			return "\x1A" . 'ap-media-meta-' . ( count( $metas ) - 1 ) . "\x1A";
		},
		$html
	);
	$html = preg_replace_callback(
		$pattern,
		static function ( $m ) use ( $origin ) {
			$escaped  = false !== strpos( $m[0], '\\/' );
			$plain    = str_replace( '\\/', '/', $m[0] );
			$relative = false;
			if ( 0 === strpos( $plain, '//' ) ) {
				$plain = 'https:' . $plain;
			} elseif ( '/' === substr( $plain, 0, 1 ) ) {
				$relative = true;
				$plain    = $origin . $plain;
			}
			$q   = '';
			$cut = strcspn( $plain, '?#' );
			if ( $cut < strlen( $plain ) ) {
				$q     = substr( $plain, $cut );
				$plain = substr( $plain, 0, $cut );
			}
			$local = ap_media_local_url( $plain );
			if ( $local === $plain ) {
				return $m[0];
			}
			if ( $relative ) {
				$local = wp_make_link_relative( $local );
			}
			$local .= $q;
			return $escaped ? str_replace( '/', '\\/', $local ) : $local;
		},
		$html
	);
	if ( $metas ) {
		$html = preg_replace_callback(
			"#\x1Aap-media-meta-(\d+)\x1A#",
			static function ( $m ) use ( $metas ) {
				return isset( $metas[ (int) $m[1] ] ) ? $metas[ (int) $m[1] ] : '';
			},
			$html
		);
	}
	$s = ap_media_settings();
	if ( ! empty( $s['responsive'] ) ) {
		$html = ap_media_responsive_html( $html );
	}
	return empty( $s['preload'] ) ? $html : ap_media_preload_lcp( $html );
}

/** Width of the phone-size companions (2× a 414px phone). */
define( 'AP_MEDIA_PHONE_W', 828 );

/**
 * Widths of the smaller copies cut from a big image, largest first. 828 covers a full-width image on a 2× phone;
 * 640 covers an image drawn at two thirds of a phone (a hero's person layer, a card) and a 475px desktop column.
 * The browser picks the smallest candidate that is still at least as wide as the drawn size × screen density.
 *
 * @return int[]
 */
function ap_media_variant_widths() {
	return array( AP_MEDIA_PHONE_W, 640 );
}

/** The smaller copy of a served uploads file at one width: `<served rel>.w<width>.webp`. */
function ap_media_variant_rel( $rel, $width ) {
	return $rel . '.w' . (int) $width . '.webp';
}

/** The phone-size companion of a served uploads file: `<served rel>.w828.webp`. */
function ap_media_phone_rel( $rel ) {
	return ap_media_variant_rel( $rel, AP_MEDIA_PHONE_W );
}

/** The raster copy of a flattened SVG (see ap_media_svg_flat_parts): `<rel>.webp`, with `<rel>.webp.json` holding the SVG's own width/height. */
function ap_media_svg_raster_rel( $rel ) {
	return $rel . '.webp';
}

/**
 * The raster copy to serve instead of an SVG, or '' when there is none yet (then the SVG is queued for the maker,
 * which decides whether it is a flattened Figma export at all).
 *
 * @param string $rel Uploads-relative path of the SVG.
 * @return string
 */
function ap_media_svg_raster_choice( $rel ) {
	$cache = &ap_media_cache( 'svg' );
	if ( isset( $cache[ $rel ] ) ) {
		return $cache[ $rel ];
	}
	$cache[ $rel ] = '';
	$raster        = ap_media_svg_raster_rel( $rel );
	if ( file_exists( ap_media_local_file( $raster ) ) && file_exists( ap_media_local_file( $raster . '.json' ) ) ) {
		$cache[ $rel ] = $raster;
	} else {
		$file = ap_media_local_file( $rel );
		// Icons and logos are small; a flattened export carries a raster and is not. 8 KB keeps the queue to real candidates.
		if ( is_file( $file ) && filesize( $file ) >= 8 * KB_IN_BYTES ) {
			ap_media_note_companion( $rel );
		}
	}
	return $cache[ $rel ];
}

/** The SVG's own width/height (CSS px) saved beside its raster copy, or null. */
function ap_media_svg_raster_size( $raster_rel ) {
	$json = @file_get_contents( ap_media_local_file( $raster_rel . '.json' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$size = $json ? json_decode( $json, true ) : null;
	return ( is_array( $size ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) ? array( (int) $size['width'], (int) $size['height'] ) : null;
}

/**
 * Reads a "flattened" SVG — a Figma export that holds one raster and nothing else: a <rect> over the whole canvas
 * filled with a <pattern> that draws one base64 <image>, optionally cropped through <use transform="matrix(a 0 0 d e f)">
 * in objectBoundingBox units. Such a file is a raster in disguise: the browser parses the SVG, decodes the base64 and
 * rasterises it on the main thread on every paint, and it is a third bigger than the raster inside it.
 *
 * @param string $svg File contents.
 * @return array|null type, b64, w, h (canvas, CSS px), iw, ih (the <image> element's declared size), crop (x, y, w, h in
 *                    <image> units) — or null when the file is anything else (vector content, several images, unknown transforms).
 */
function ap_media_svg_flat_parts( $svg ) {
	if ( ! is_string( $svg ) || strlen( $svg ) > 6 * MB_IN_BYTES || false === stripos( $svg, '<svg' ) ) {
		return null;
	}
	if ( preg_match( '#<(path|text|tspan|filter|foreignObject|circle|polygon|polyline|line|ellipse|script|style|mask|clipPath|linearGradient|radialGradient|symbol|a)\b#i', $svg ) ) {
		return null;
	}
	if ( 1 !== preg_match_all( '#<image\b[^>]*>#i', $svg, $images ) ) {
		return null;
	}
	$image = $images[0][0];
	if ( ! preg_match( '#(?:xlink:)?href="data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=\s]+)"#i', $image, $data ) ) {
		return null;
	}
	if ( ! preg_match( '#<svg\b[^>]*>#i', $svg, $root ) ) {
		return null;
	}
	$w = preg_match( '#\swidth="([\d.]+)"#i', $root[0], $m ) ? (float) $m[1] : 0;
	$h = preg_match( '#\sheight="([\d.]+)"#i', $root[0], $m ) ? (float) $m[1] : 0;
	if ( ( $w <= 0 || $h <= 0 ) && preg_match( '#\sviewBox="[\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.]+)[\s,]+([\d.]+)"#i', $root[0], $m ) ) {
		$w = (float) $m[1];
		$h = (float) $m[2];
	}
	if ( $w <= 0 || $h <= 0 ) {
		return null;
	}
	$iw   = preg_match( '#\swidth="([\d.]+)"#i', $image, $m ) ? (float) $m[1] : 0;
	$ih   = preg_match( '#\sheight="([\d.]+)"#i', $image, $m ) ? (float) $m[1] : 0;
	$crop = null;
	if ( preg_match( '#<use\b[^>]*transform="matrix\(\s*([\d.eE+\-]+)[\s,]+0[\s,]+0[\s,]+([\d.eE+\-]+)[\s,]+([\d.eE+\-]+)[\s,]+([\d.eE+\-]+)\s*\)"#i', $svg, $u ) ) {
		if ( ! preg_match( '#patternContentUnits="objectBoundingBox"#i', $svg ) || $iw <= 0 || $ih <= 0 ) {
			return null;
		}
		$a = (float) $u[1];
		$d = (float) $u[2];
		if ( $a <= 0 || $d <= 0 ) {
			return null;
		}
		// The pattern tile is the unit square; the image spans [e, e + a·iw] × [f, f + d·ih] of it, so the visible
		// part of the image is the square mapped back: x from -e/a, width 1/a (and likewise for y).
		$crop = array( 'x' => -(float) $u[3] / $a, 'y' => -(float) $u[4] / $d, 'w' => 1 / $a, 'h' => 1 / $d );
		$tol  = 1.5;
		if ( $crop['x'] < -$tol || $crop['y'] < -$tol || $crop['x'] + $crop['w'] > $iw + $tol || $crop['y'] + $crop['h'] > $ih + $tol ) {
			return null; // Part of the canvas shows no image: not a plain raster.
		}
	} elseif ( preg_match( '#<use\b#i', $svg ) || preg_match( '#\stransform=#i', $image ) ) {
		return null;
	}
	return array( 'type' => strtolower( $data[1] ), 'b64' => $data[2], 'w' => $w, 'h' => $h, 'iw' => $iw, 'ih' => $ih, 'crop' => $crop );
}

/**
 * Cuts the raster out of a flattened SVG (ap_media_svg_flat_parts) into `<rel>.webp` plus `<rel>.webp.json` with the
 * SVG's own size, so pages can serve the raster with width/height attributes and the browser paints the same picture
 * without parsing an SVG. The raster keeps its native pixels unless it is more than four times the SVG's declared size,
 * which keeps it crisp on any 2× or 3× screen while never shipping a 1000px file for a 50px badge.
 *
 * @param string $rel Uploads-relative path of the SVG.
 * @return string What happened.
 */
function ap_media_make_svg_raster( $rel ) {
	if ( '' === $rel || false !== strpos( $rel, '..' ) || 'svg' !== strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ) ) {
		return 'refused';
	}
	$src = ap_media_local_file( $rel );
	$dst = ap_media_local_file( ap_media_svg_raster_rel( $rel ) );
	if ( ! is_file( $src ) ) {
		return 'no file';
	}
	if ( file_exists( $dst ) && file_exists( $dst . '.json' ) ) {
		return 'exists';
	}
	if ( file_exists( $dst ) ) {
		// Somebody made <name>.svg.webp by hand (the fire hero's person, for one): never overwrite it, and never serve it for the SVG either.
		return 'a file of that name already exists, left alone';
	}
	$parts = ap_media_svg_flat_parts( (string) file_get_contents( $src ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! $parts ) {
		return 'not a flattened svg';
	}
	$bytes = base64_decode( preg_replace( '/\s+/', '', $parts['b64'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $bytes || strlen( $bytes ) < 100 ) {
		return 'bad image data';
	}
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return 'no webp support';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$ext = 'jpeg' === $parts['type'] || 'jpg' === $parts['type'] ? 'jpg' : $parts['type'];
	$tmp = wp_tempnam( 'ap-media-flat' );
	if ( ! $tmp ) {
		return 'could not make a temp file';
	}
	// wp_tempnam() ends the name in .tmp; the editors pick the right loader from the real extension.
	if ( @rename( $tmp, $tmp . '.' . $ext ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$tmp .= '.' . $ext;
	}
	if ( false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return 'could not write temp file';
	}
	try {
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info ) {
			return 'embedded data is not an image';
		}
		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			return 'editor: ' . $editor->get_error_message();
		}
		$size = $editor->get_size();
		$rw   = (int) $size['width'];
		$rh   = (int) $size['height'];
		if ( $parts['crop'] ) {
			// The <image> element may declare a size other than the raster's real pixels: scale the crop to the pixels.
			$sx = $parts['iw'] > 0 ? $rw / $parts['iw'] : 1;
			$sy = $parts['ih'] > 0 ? $rh / $parts['ih'] : 1;
			$x  = max( 0, (int) round( $parts['crop']['x'] * $sx ) );
			$y  = max( 0, (int) round( $parts['crop']['y'] * $sy ) );
			$cw = min( $rw - $x, (int) round( $parts['crop']['w'] * $sx ) );
			$ch = min( $rh - $y, (int) round( $parts['crop']['h'] * $sy ) );
			if ( $cw < 8 || $ch < 8 ) {
				return 'crop too small';
			}
			$editor->crop( $x, $y, $cw, $ch );
			$rw = $cw;
			$rh = $ch;
		}
		$max_w = (int) round( 4 * $parts['w'] );
		if ( $rw > $max_w ) {
			$editor->resize( $max_w, null, false );
		}
		$editor->set_quality( (int) ap_media_settings()['webp_quality'] );
		$saved = $editor->save( $dst, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return 'save: ' . $saved->get_error_message();
		}
		if ( ! empty( $saved['path'] ) && $saved['path'] !== $dst && file_exists( $saved['path'] ) ) {
			@rename( $saved['path'], $dst ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! file_exists( $dst ) ) {
			return 'not made';
		}
		file_put_contents( $dst . '.json', wp_json_encode( array( 'width' => (int) round( $parts['w'] ), 'height' => (int) round( $parts['h'] ) ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return 'made ' . filesize( $src ) . '→' . filesize( $dst );
	} finally {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

/**
 * Uploads-relative path of an address on this site's uploads (absolute or root-relative), or ''.
 *
 * @param string $url Address.
 * @return string
 */
function ap_media_site_rel( $url ) {
	$up   = wp_get_upload_dir();
	$base = trailingslashit( $up['baseurl'] );
	$bare = (string) strtok( (string) $url, '?#' );
	$rel  = '';
	$nb   = preg_replace( '#^https?:#i', '', $bare );
	$nbase = preg_replace( '#^https?:#i', '', $base );
	if ( 0 === strpos( $nb, $nbase ) ) {
		$rel = rawurldecode( substr( $nb, strlen( $nbase ) ) );
	} else {
		$relbase = wp_make_link_relative( $base );
		if ( '/' === substr( $bare, 0, 1 ) && 0 === strpos( $bare, $relbase ) ) {
			$rel = rawurldecode( substr( $bare, strlen( $relbase ) ) );
		}
	}
	return ( '' !== $rel && false === strpos( $rel, '..' ) ) ? $rel : '';
}

/**
 * Gives big images a phone-size candidate: every <img> without a srcset, and every image preload, whose file is
 * wider than ~1000px and has a `.w828.webp` companion gets `srcset` / `imagesrcset` + `sizes`, so phones download
 * the 828px copy (PageSpeed "properly size images") and desktops keep the original. Images still missing a
 * companion are listed for /ap-media/v1/variants. Preloads get the same candidates as their <img>, so the browser
 * never fetches both.
 *
 * @param string $html Page HTML (already rewritten to local addresses).
 * @return string
 */
function ap_media_responsive_html( $html ) {
	$widths = array();
	$cands  = static function ( $src ) use ( &$widths ) {
		$src = html_entity_decode( (string) $src, ENT_QUOTES );
		$rel = ap_media_site_rel( $src );
		if ( '' === $rel || preg_match( '#\.w\d+\.webp$#', $rel ) ) {
			return null;
		}
		$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'png', 'jpg', 'jpeg', 'webp' ), true ) ) {
			return null;
		}
		if ( ! isset( $widths[ $rel ] ) ) {
			$file           = ap_media_local_file( $rel );
			$info           = is_file( $file ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$widths[ $rel ] = $info ? (int) $info[0] : 0;
		}
		$full     = $widths[ $rel ];
		$relative = '/' === substr( $src, 0, 1 );
		$list     = array();
		$missing  = false;
		foreach ( ap_media_variant_widths() as $w ) {
			if ( $full < $w + 200 ) {
				continue;
			}
			$variant = ap_media_variant_rel( $rel, $w );
			if ( file_exists( ap_media_local_file( $variant ) ) ) {
				$url    = ap_media_local_url_for_rel( $variant );
				$list[] = ( $relative ? wp_make_link_relative( $url ) : $url ) . ' ' . $w . 'w';
			} else {
				$missing = true;
			}
		}
		if ( $missing ) {
			ap_media_note_companion( $rel );
		}
		if ( ! $list ) {
			return null;
		}
		$list   = array_reverse( $list ); // smallest first
		$list[] = $src . ' ' . $full . 'w';
		return array(
			implode( ', ', $list ),
			'(max-width: 900px) 100vw, ' . $full . 'px',
		);
	};
	$html = preg_replace_callback(
		'#<img\b[^>]*>#i',
		static function ( $m ) use ( $cands ) {
			$tag = $m[0];
			if ( ! preg_match( '#\ssrc=(["\'])([^"\']+)\1#i', $tag, $s ) ) {
				return $tag;
			}
			// A flattened SVG is served as the raster inside it, with the SVG's own size as width/height when the tag has none.
			$src_plain = html_entity_decode( $s[2], ENT_QUOTES );
			$svg_rel   = ap_media_site_rel( $src_plain );
			if ( '' !== $svg_rel && 'svg' === strtolower( pathinfo( $svg_rel, PATHINFO_EXTENSION ) ) ) {
				$raster = ap_media_svg_raster_choice( $svg_rel );
				if ( '' !== $raster ) {
					$url = ap_media_local_url_for_rel( $raster );
					if ( '/' === substr( $src_plain, 0, 1 ) ) {
						$url = wp_make_link_relative( $url );
					}
					$tag  = preg_replace( '#\ssrc=(["\'])[^"\']+\1#i', ' src="' . esc_attr( $url ) . '"', $tag, 1 );
					$size = ap_media_svg_raster_size( $raster );
					if ( $size && ! preg_match( '#\s(width|height)=#i', $tag ) ) {
						$tag = preg_replace( '#\ssrc=#i', ' width="' . $size[0] . '" height="' . $size[1] . '" src=', $tag, 1 );
					}
					$s[2] = $url;
				}
			}
			if ( false !== stripos( $tag, ' srcset=' ) ) {
				return $tag;
			}
			$c = $cands( $s[2] );
			if ( ! $c ) {
				return $tag;
			}
			// An author's sizes hint (the width the image is really drawn at) beats the generic one.
			if ( preg_match( '#\ssizes=(["\'])[^"\']+\1#i', $tag ) ) {
				return preg_replace( '#\ssrc=#i', ' srcset="' . esc_attr( $c[0] ) . '" src=', $tag, 1 );
			}
			return preg_replace( '#\ssrc=#i', ' srcset="' . esc_attr( $c[0] ) . '" sizes="' . esc_attr( $c[1] ) . '" src=', $tag, 1 );
		},
		$html
	);
	return preg_replace_callback(
		'#<link\b[^>]*\brel=(["\'])preload\1[^>]*>#i',
		static function ( $m ) use ( $cands ) {
			$tag = $m[0];
			if ( false !== stripos( $tag, 'imagesrcset' ) || ! preg_match( '#\bas=(["\'])image\1#i', $tag ) || ! preg_match( '#\shref=(["\'])([^"\']+)\1#i', $tag, $s ) ) {
				return $tag;
			}
			$c = $cands( $s[2] );
			if ( ! $c ) {
				return $tag;
			}
			return preg_replace( '#\shref=#i', ' imagesrcset="' . esc_attr( $c[0] ) . '" imagesizes="' . esc_attr( $c[1] ) . '" href=', $tag, 1 );
		},
		$html
	);
}

/**
 * Gives a page without an image preload one for its first-screen image (PageSpeed LCP).
 *
 * Heroes that preload their own backdrop already carry <link rel="preload" as="image"> (ap_ui_preload_image in
 * the kit); this covers the rest. The candidate is the first <img> after <main> that the component marked
 * fetchpriority="high" and did not lazy-load — exactly the image it wants painted first — and the preload copies
 * that <img>'s final src/srcset/sizes so the browser fetches the same candidate once. When no such <img> exists,
 * the first inline background-image after <main> (a hero section's own backdrop) is preloaded instead. Nothing
 * changes when a preload is already present, when there is no <main>, or when the candidate is further than the
 * first ~60 KB of content (then it is not the first screen).
 *
 * @param string $html Page HTML (addresses already rewritten).
 * @return string
 */
function ap_media_preload_lcp( $html ) {
	$head_end = stripos( $html, '</head>' );
	$main_at  = stripos( $html, '<main' );
	if ( false === $head_end || false === $main_at || $main_at < $head_end ) {
		return $html;
	}
	if ( preg_match( '#<link\b[^>]*\brel=(["\'])preload\1[^>]*\bas=(["\'])image\2#i', substr( $html, 0, $head_end ) ) || preg_match( '#<link\b[^>]*\bas=(["\'])image\1[^>]*\brel=(["\'])preload\2#i', substr( $html, 0, $head_end ) ) ) {
		return $html;
	}
	$window = substr( $html, $main_at, 60000 );
	$attrs  = '';
	if ( preg_match_all( '#<img\b[^>]*>#i', $window, $imgs ) ) {
		foreach ( $imgs[0] as $tag ) {
			if ( ! preg_match( '#\sfetchpriority=(["\'])high\1#i', $tag ) || preg_match( '#\sloading=(["\'])lazy\1#i', $tag ) || ! preg_match( '#\ssrc=(["\'])([^"\']+)\1#i', $tag, $src ) ) {
				continue;
			}
			if ( 0 === strpos( $src[2], 'data:' ) ) {
				continue;
			}
			$attrs = ' href="' . esc_attr( $src[2] ) . '"';
			if ( preg_match( '#\ssrcset=(["\'])([^"\']+)\1#i', $tag, $ss ) ) {
				$attrs .= ' imagesrcset="' . esc_attr( $ss[2] ) . '"';
				if ( preg_match( '#\ssizes=(["\'])([^"\']+)\1#i', $tag, $sz ) ) {
					$attrs .= ' imagesizes="' . esc_attr( $sz[2] ) . '"';
				}
			}
			break;
		}
	}
	if ( '' === $attrs && preg_match( '#\sstyle=(["\'])[^"\']*background-image:\s*url\((?:&quot;|&\#039;|["\'])?([^"\'()&]+)#i', $window, $bg ) ) {
		$attrs = ' href="' . esc_attr( html_entity_decode( $bg[2], ENT_QUOTES ) ) . '"';
	}
	if ( '' === $attrs ) {
		return $html;
	}
	$link = '<link rel="preload" as="image" fetchpriority="high"' . $attrs . ' />' . "\n";
	return substr( $html, 0, $head_end ) . $link . substr( $html, $head_end );
}

/** Option names for the background companion maker. */
define( 'AP_MEDIA_TODO_OPTION', 'ap_media_variant_todo' ); // Files still waiting for a WebP or phone copy (kept from 1.1 so GET /variants keeps working).
define( 'AP_MEDIA_SKIP_OPTION', 'ap_media_companion_skip' ); // Files the maker looked at and left alone, with the reason, so they are not queued again.
define( 'AP_MEDIA_CRON_HOOK', 'ap_media_make_companions' );

/**
 * Remembers a served image that still lacks a companion — its .webp copy (JPG/GIF, PNG over png_webp_min)
 * or its 828px phone copy — so the background maker can produce it. Saved once per request; files the maker
 * already decided to skip are not queued again. Also schedules the maker (WP-Cron, a minute from now) when
 * something new was queued; GET /ap-media/v1/variants lists the queue and POST /ap-media/v1/companions runs
 * the maker at once.
 *
 * @param string $rel Uploads-relative path of the served file (the original, or the .webp when that is what is served).
 */
function ap_media_note_companion( $rel ) {
	$rel = (string) $rel;
	if ( '' === $rel || false !== strpos( $rel, '..' ) ) {
		return;
	}
	$GLOBALS['ap_media_variant_todo'][ $rel ] = true;
	$hooks = &ap_media_cache( 'hooks' );
	if ( ! empty( $hooks['companion'] ) ) {
		return;
	}
	$hooks['companion'] = true;
	add_action(
		'shutdown',
		static function () {
			if ( empty( $GLOBALS['ap_media_variant_todo'] ) ) {
				return;
			}
			$s = ap_media_settings();
			$saved = get_option( AP_MEDIA_TODO_OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			$skip  = get_option( AP_MEDIA_SKIP_OPTION, array() );
			$skip  = is_array( $skip ) ? $skip : array();
			$new   = array_diff( array_keys( $GLOBALS['ap_media_variant_todo'] ), $saved, array_keys( $skip ) );
			// Only files that really lack something are queued, so a complete file never churns the queue.
			$new = array_values( array_filter( $new, 'ap_media_companions_missing' ) );
			if ( ! $new ) {
				return;
			}
			update_option( AP_MEDIA_TODO_OPTION, array_slice( array_merge( $saved, array_values( $new ) ), -5000 ), false );
			if ( ! empty( $s['companions'] ) && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( AP_MEDIA_CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 60, AP_MEDIA_CRON_HOOK );
			}
		}
	);
}

/**
 * 1.1 name, kept for anything that calls it.
 *
 * @param string $rel Uploads-relative path of the served file.
 */
function ap_media_note_variant( $rel ) {
	ap_media_note_companion( $rel );
}

/**
 * The companions one served file still needs: array of [ kind, companion rel ] for what is missing.
 *
 * - 'webp'  : <rel>.webp for a JPG/GIF, or a PNG at least png_webp_min bytes (ap_media_make_webp decides the rest).
 * - 'phone' : <served>.w828.webp when the served file (the .webp when it exists) is wider than 828 + 200 px.
 *
 * @param string $rel Uploads-relative path of the file as the page referenced it.
 * @return array<int, array{0:string,1:string}>
 */
function ap_media_companions_missing( $rel ) {
	$out  = array();
	$file = ap_media_local_file( $rel );
	if ( ! is_file( $file ) ) {
		return $out;
	}
	$s   = ap_media_settings();
	$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
	if ( in_array( $ext, array( 'jpg', 'jpeg', 'gif', 'png' ), true ) && ! file_exists( ap_media_local_file( ap_media_webp_rel( $rel ) ) ) ) {
		if ( 'png' !== $ext || filesize( $file ) >= (int) $s['png_webp_min'] ) {
			$out[] = array( 'webp', ap_media_webp_rel( $rel ) );
		}
	}
	if ( 'svg' === $ext ) {
		$raster = ap_media_svg_raster_rel( $rel );
		if ( ! file_exists( ap_media_local_file( $raster ) ) || ! file_exists( ap_media_local_file( $raster . '.json' ) ) ) {
			$out[] = array( 'svgraster', $raster );
		}
		return $out;
	}
	$served = ap_media_webp_choice( $rel );
	$served = '' !== $served ? $served : $rel;
	if ( preg_match( '#\.w\d+\.webp$#', $served ) ) {
		return $out;
	}
	$sext = strtolower( pathinfo( $served, PATHINFO_EXTENSION ) );
	if ( in_array( $sext, array( 'png', 'jpg', 'jpeg', 'webp' ), true ) ) {
		$info = @getimagesize( ap_media_local_file( $served ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$full = $info ? (int) $info[0] : 0;
		foreach ( ap_media_variant_widths() as $w ) {
			if ( $full >= $w + 200 && ! file_exists( ap_media_local_file( ap_media_variant_rel( $served, $w ) ) ) ) {
				$out[] = array( 'variant:' . $w, ap_media_variant_rel( $served, $w ) );
			}
		}
	}
	return $out;
}

/**
 * Makes the missing companions of up to $limit queued files. Runs from WP-Cron (AP_MEDIA_CRON_HOOK) and from
 * POST /ap-media/v1/companions. Each file is handled once: what could be made is made, what was left alone
 * is recorded with its reason so it is not queued again; a WebP made here is picked up by the next page view
 * and its own phone copy is queued then (the phone copy is cut from the file the page serves).
 *
 * @param int $limit Files to handle in this run.
 * @return array<string, string> rel → what happened.
 */
function ap_media_make_companions( $limit = 8 ) {
	$lock = 'ap_media_companions_lock';
	if ( get_transient( $lock ) ) {
		return array( '_' => 'another run is in progress' );
	}
	set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );
	$todo = get_option( AP_MEDIA_TODO_OPTION, array() );
	$todo = is_array( $todo ) ? array_values( array_unique( array_map( 'strval', $todo ) ) ) : array();
	$skip = get_option( AP_MEDIA_SKIP_OPTION, array() );
	$skip = is_array( $skip ) ? $skip : array();
	$out  = array();
	$done = array();
	foreach ( array_slice( $todo, 0, max( 1, (int) $limit ) ) as $rel ) {
		$done[] = $rel;
		try {
			$missing = ap_media_companions_missing( $rel );
			if ( ! $missing ) {
				$out[ $rel ]  = is_file( ap_media_local_file( $rel ) ) ? 'nothing missing' : 'no file';
				$skip[ $rel ] = $out[ $rel ];
				continue;
			}
			$notes = array();
			foreach ( $missing as $job ) {
				if ( 'webp' === $job[0] ) {
					$notes[] = 'webp: ' . ap_media_make_webp( $rel );
				} elseif ( 'svgraster' === $job[0] ) {
					$notes[] = 'svg raster: ' . ap_media_make_svg_raster( $rel );
				} else {
					$served  = ap_media_webp_choice( $rel );
					$notes[] = $job[0] . ': ' . ap_media_make_variant( '' !== $served ? $served : $rel, (int) substr( $job[0], 8 ) );
				}
			}
			$out[ $rel ] = implode( '; ', $notes );
			// Anything still missing after this run was declined by the maker (small PNG, animated GIF, no editor): leave it be.
			if ( ap_media_companions_missing( $rel ) ) {
				$skip[ $rel ] = $out[ $rel ];
			}
		} catch ( \Throwable $e ) {
			$out[ $rel ]  = 'error: ' . $e->getMessage();
			$skip[ $rel ] = $out[ $rel ];
		}
	}
	$left = array_values( array_diff( $todo, $done ) );
	update_option( AP_MEDIA_TODO_OPTION, $left, false );
	update_option( AP_MEDIA_SKIP_OPTION, array_slice( $skip, -5000, null, true ), false );
	delete_transient( $lock );
	if ( $left && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( AP_MEDIA_CRON_HOOK ) && ! empty( ap_media_settings()['companions'] ) ) {
		wp_schedule_single_event( time() + 60, AP_MEDIA_CRON_HOOK );
	}
	$out['_left'] = (string) count( $left );
	return $out;
}
add_action( AP_MEDIA_CRON_HOOK, 'ap_media_make_companions' );

/**
 * Makes the 828px WebP companion of one uploads file.
 *
 * @param string $rel Uploads-relative path.
 * @return string What happened.
 */
function ap_media_make_phone( $rel ) {
	return ap_media_make_variant( $rel, AP_MEDIA_PHONE_W );
}

/**
 * Makes the WebP copy of one uploads file at one width (see ap_media_variant_widths).
 *
 * @param string $rel   Uploads-relative path of the served file.
 * @param int    $width Target width in pixels.
 * @return string What happened.
 */
function ap_media_make_variant( $rel, $width ) {
	$width = (int) $width;
	if ( '' === $rel || false !== strpos( $rel, '..' ) || ! in_array( $width, ap_media_variant_widths(), true ) ) {
		return 'refused';
	}
	$src = ap_media_local_file( $rel );
	$dst = ap_media_local_file( ap_media_variant_rel( $rel, $width ) );
	if ( ! is_file( $src ) ) {
		return 'no file';
	}
	if ( file_exists( $dst ) ) {
		return 'exists';
	}
	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return 'editor: ' . $editor->get_error_message();
	}
	$size = $editor->get_size();
	if ( empty( $size['width'] ) || $size['width'] < $width + 200 ) {
		return 'small';
	}
	$editor->resize( $width, null, false );
	$editor->set_quality( 78 );
	$saved = $editor->save( $dst, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		return 'save: ' . $saved->get_error_message();
	}
	if ( ! empty( $saved['path'] ) && $saved['path'] !== $dst && file_exists( $saved['path'] ) ) {
		@rename( $saved['path'], $dst ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	return file_exists( $dst ) ? 'made ' . filesize( $src ) . '→' . filesize( $dst ) : 'not made';
}

/** Starts the output buffer on page views. */
add_action(
	'template_redirect',
	static function () {
		if ( defined( 'AP_MEDIA_DISABLE' ) && AP_MEDIA_DISABLE ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$s = ap_media_settings();
		if ( empty( $s['rewrite'] ) ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				try {
					return ap_media_rewrite_html( $html );
				} catch ( \Throwable $e ) {
					ap_media_log( 'rewrite: ' . $e->getMessage() );
					return $html;
				}
			}
		);
	},
	0
);

/**
 * Copies one live file to its local path; makes the WebP companion; registers it in the media library.
 *
 * @param string $url      Live address.
 * @param bool   $force    Re-copy even when the file exists.
 * @param bool   $register Add a media library entry.
 * @return array Result row.
 */
function ap_media_localize_one( $url, $force = false, $register = true ) {
	$row = array( 'url' => $url );
	$rel = ap_media_rel_path( $url );
	if ( '' === $rel ) {
		$row['status'] = 'refused';
		$row['why']    = 'not a live /wp-content/ file with an allowed type';
		return $row;
	}
	$row['rel'] = $rel;
	$dest       = ap_media_local_file( $rel );
	$ext        = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
	if ( file_exists( $dest ) && ! $force ) {
		$row['status'] = 'exists';
	} else {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$parts = wp_parse_url( $url );
		$fetch = 'https://www.assetpanda.com' . implode( '/', array_map( 'rawurlencode', explode( '/', rawurldecode( $parts['path'] ) ) ) );
		$tmp   = download_url( $fetch, 90 );
		if ( is_wp_error( $tmp ) ) {
			$row['status'] = 'error';
			$row['why']    = $tmp->get_error_message();
			return $row;
		}
		$size = (int) filesize( $tmp );
		if ( $size <= 0 || $size > ap_media_allowed_exts()[ $ext ] ) {
			wp_delete_file( $tmp );
			$row['status'] = 'error';
			$row['why']    = 'size ' . $size;
			return $row;
		}
		if ( 'svg' === $ext ) {
			$svg = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === stripos( $svg, '<svg' ) || preg_match( '#<script|\son[a-z]+\s*=|javascript:#i', $svg ) ) {
				wp_delete_file( $tmp );
				$row['status'] = 'error';
				$row['why']    = 'svg failed the safety check';
				return $row;
			}
		} elseif ( in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico' ), true ) ) {
			$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $info && 'avif' !== $ext ) {
				wp_delete_file( $tmp );
				$row['status'] = 'error';
				$row['why']    = 'not an image';
				return $row;
			}
		}
		wp_mkdir_p( dirname( $dest ) );
		if ( ! @rename( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @copy( $tmp, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				wp_delete_file( $tmp );
				$row['status'] = 'error';
				$row['why']    = 'could not write ' . $rel;
				return $row;
			}
			wp_delete_file( $tmp );
		}
		@chmod( $dest, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$row['status'] = 'copied';
		$row['bytes']  = $size;
	}
	$row['webp'] = ap_media_make_webp( $rel );
	if ( $register ) {
		$row['attachment'] = ap_media_register( $rel, $url );
	}
	return $row;
}

/**
 * Makes `<file>.webp` beside a JPG/GIF (always) or a heavy PNG (when the WebP is at least 20 % smaller).
 *
 * @param string $rel Uploads-relative path.
 * @return string What happened.
 */
function ap_media_make_webp( $rel ) {
	$ext = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'gif', 'png' ), true ) ) {
		return 'n/a';
	}
	$src  = ap_media_local_file( $rel );
	$webp = ap_media_local_file( ap_media_webp_rel( $rel ) );
	if ( file_exists( $webp ) ) {
		return 'exists';
	}
	$s = ap_media_settings();
	if ( 'png' === $ext && filesize( $src ) < (int) $s['png_webp_min'] ) {
		return 'small png kept';
	}
	if ( 'gif' === $ext ) {
		// Animated GIFs lose their animation in a single-frame WebP: keep them as they are.
		$raw = (string) file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( substr_count( $raw, "\x21\xF9\x04" ) > 1 ) {
			return 'animated gif kept';
		}
	}
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return 'no webp support';
	}
	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return 'editor: ' . $editor->get_error_message();
	}
	$editor->set_quality( (int) $s['webp_quality'] );
	$saved = $editor->save( $webp, 'image/webp' );
	if ( is_wp_error( $saved ) ) {
		return 'save: ' . $saved->get_error_message();
	}
	$saved_path = isset( $saved['path'] ) ? $saved['path'] : $webp;
	if ( $saved_path !== $webp && file_exists( $saved_path ) ) {
		@rename( $saved_path, $webp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	if ( 'png' === $ext && file_exists( $webp ) && filesize( $webp ) > 0.8 * filesize( $src ) ) {
		wp_delete_file( $webp );
		return 'png webp not smaller, kept png';
	}
	return file_exists( $webp ) ? 'made ' . filesize( $src ) . '→' . filesize( $webp ) : 'not made';
}

/**
 * A minimal media library entry (no generated sizes) for a localized file, once.
 *
 * @param string $rel    Uploads-relative path.
 * @param string $source Live address it came from.
 * @return int|string Attachment id or a note.
 */
function ap_media_register( $rel, $source ) {
	if ( 0 === strpos( $rel, 'ap-localized/' ) ) {
		return 'not registered (non-uploads source)';
	}
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_wp_attached_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $rel, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	$type = wp_check_filetype( basename( $rel ) );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'] ? $type['type'] : 'application/octet-stream',
			'post_title'     => sanitize_text_field( pathinfo( $rel, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
			'guid'           => ap_media_local_url_for_rel( $rel ),
		),
		ap_media_local_file( $rel ),
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		return 'error: ' . $id->get_error_message();
	}
	update_post_meta( $id, '_ap_media_source', esc_url_raw( $source ) );
	$info = @getimagesize( ap_media_local_file( $rel ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$meta = array( 'file' => $rel, 'filesize' => (int) filesize( ap_media_local_file( $rel ) ) );
	if ( $info ) {
		$meta['width']  = (int) $info[0];
		$meta['height'] = (int) $info[1];
	}
	wp_update_attachment_metadata( $id, $meta );
	return (int) $id;
}

add_action(
	'rest_api_init',
	static function () {
		$can = static function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route(
			'ap-media/v1',
			'/localize',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'args'                => array(
					'urls'     => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'string' ) ),
					'force'    => array( 'type' => 'boolean', 'default' => false ),
					'register' => array( 'type' => 'boolean', 'default' => true ),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					$urls = array_slice( array_values( array_unique( array_map( 'strval', (array) $req['urls'] ) ) ), 0, 40 );
					$out  = array();
					foreach ( $urls as $u ) {
						try {
							$out[] = ap_media_localize_one( $u, (bool) $req['force'], (bool) $req['register'] );
						} catch ( \Throwable $e ) {
							$out[] = array( 'url' => $u, 'status' => 'error', 'why' => $e->getMessage() );
						}
					}
					return rest_ensure_response( array( 'results' => $out ) );
				},
			)
		);
		register_rest_route(
			'ap-media/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $req ) {
					$out = array();
					foreach ( array_slice( (array) $req['urls'], 0, 500 ) as $u ) {
						$rel   = ap_media_rel_path( (string) $u );
						$out[] = array(
							'url'   => $u,
							'rel'   => $rel,
							'local' => '' !== $rel && file_exists( ap_media_local_file( $rel ) ),
							'webp'  => '' !== $rel && file_exists( ap_media_local_file( ap_media_webp_rel( $rel ) ) ),
							'serve' => ap_media_local_url( (string) $u ),
						);
					}
					return rest_ensure_response( array( 'results' => $out, 'settings' => ap_media_settings() ) );
				},
			)
		);
		// Companions: GET /variants lists served files still waiting for a WebP, phone copy or SVG raster; POST /variants
		// {rels} (≤ 15 per call) cuts phone copies of the given files directly (1.1 behaviour); POST /companions runs the
		// background maker now (what WP-Cron would do a minute after a page view queued something).
		register_rest_route(
			'ap-media/v1',
			'/companions',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'args'                => array(
					'limit' => array( 'type' => 'integer', 'default' => 8 ),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					return rest_ensure_response( array( 'results' => ap_media_make_companions( min( 40, max( 1, (int) $req['limit'] ) ) ) ) );
				},
			)
		);
		register_rest_route(
			'ap-media/v1',
			'/variants',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $can,
					'callback'            => static function () {
						$todo = get_option( AP_MEDIA_TODO_OPTION, array() );
						$left = array();
						foreach ( is_array( $todo ) ? $todo : array() as $rel ) {
							if ( ap_media_companions_missing( (string) $rel ) ) {
								$left[] = $rel;
							}
						}
						$skip = get_option( AP_MEDIA_SKIP_OPTION, array() );
						return rest_ensure_response( array( 'count' => count( $left ), 'rels' => $left, 'skipped' => is_array( $skip ) ? $skip : array(), 'next_run' => wp_next_scheduled( AP_MEDIA_CRON_HOOK ) ) );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $can,
					'callback'            => static function ( WP_REST_Request $req ) {
						$out = array();
						foreach ( array_slice( (array) $req['rels'], 0, 15 ) as $rel ) {
							try {
								$out[ (string) $rel ] = ap_media_make_phone( (string) $rel );
							} catch ( \Throwable $e ) {
								$out[ (string) $rel ] = 'error: ' . $e->getMessage();
							}
						}
						return rest_ensure_response( array( 'results' => $out ) );
					},
				),
			)
		);
		// Live files pages used without a local copy (see ap_media_note_miss); DELETE clears the list.
		register_rest_route(
			'ap-media/v1',
			'/misses',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $can,
					'callback'            => static function () {
						$saved = get_option( 'ap_media_misses', array() );
						$left  = array();
						foreach ( is_array( $saved ) ? $saved : array() as $u ) {
							$rel = ap_media_rel_path( $u );
							if ( '' !== $rel && ! file_exists( ap_media_local_file( $rel ) ) ) {
								$left[] = $u;
							}
						}
						return rest_ensure_response( array( 'count' => count( $left ), 'urls' => $left ) );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $can,
					'callback'            => static function () {
						delete_option( 'ap_media_misses' );
						return rest_ensure_response( array( 'cleared' => true ) );
					},
				),
			)
		);
		// Fallback when the server cannot fetch a file itself: the caller sends the bytes for a mapped path.
		register_rest_route(
			'ap-media/v1',
			'/put',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'args'                => array(
					'url' => array( 'type' => 'string', 'required' => true ),
					'b64' => array( 'type' => 'string', 'required' => true ),
				),
				'callback'            => static function ( WP_REST_Request $req ) {
					$url = (string) $req['url'];
					$rel = ap_media_rel_path( $url );
					if ( '' === $rel ) {
						return new WP_Error( 'ap_media_refused', 'not a live /wp-content/ file with an allowed type', array( 'status' => 400 ) );
					}
					$bytes = base64_decode( (string) $req['b64'], true );
					$ext   = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
					if ( false === $bytes || '' === $bytes || strlen( $bytes ) > ap_media_allowed_exts()[ $ext ] ) {
						return new WP_Error( 'ap_media_bad_data', 'empty, oversized or not base64', array( 'status' => 400 ) );
					}
					if ( 'svg' === $ext && ( false === stripos( $bytes, '<svg' ) || preg_match( '#<script|\son[a-z]+\s*=|javascript:#i', $bytes ) ) ) {
						return new WP_Error( 'ap_media_bad_svg', 'svg failed the safety check', array( 'status' => 400 ) );
					}
					$dest = ap_media_local_file( $rel );
					wp_mkdir_p( dirname( $dest ) );
					if ( false === file_put_contents( $dest, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						return new WP_Error( 'ap_media_write', 'could not write ' . $rel, array( 'status' => 500 ) );
					}
					if ( in_array( $ext, array( 'png', 'jpg', 'jpeg', 'gif', 'webp' ), true ) && false === @getimagesize( $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						wp_delete_file( $dest );
						return new WP_Error( 'ap_media_not_image', 'not an image', array( 'status' => 400 ) );
					}
					return rest_ensure_response(
						array(
							'url'        => $url,
							'rel'        => $rel,
							'status'     => 'put',
							'bytes'      => strlen( $bytes ),
							'webp'       => ap_media_make_webp( $rel ),
							'attachment' => ap_media_register( $rel, $url ),
						)
					);
				},
			)
		);
		register_rest_route(
			'ap-media/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'permission_callback' => $can,
				'callback'            => static function ( WP_REST_Request $req ) {
					$s = ap_media_settings();
					foreach ( array( 'rewrite', 'webp_swap', 'responsive', 'companions', 'preload' ) as $k ) {
						if ( null !== $req[ $k ] ) {
							$s[ $k ] = (bool) $req[ $k ];
						}
					}
					update_option( AP_MEDIA_OPTION, $s, false );
					return rest_ensure_response( $s );
				},
			)
		);
	}
);
