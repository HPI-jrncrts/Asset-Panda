<?php
/**
 * Brings live's brand files into the media library (used by the apply actions).
 *
 * Each file is fetched from its live address first; if that fails or the file
 * does not validate, the identical copy bundled in assets/ is used. Imported
 * attachments carry the post meta _ap_site_asset = <key>, so a second apply
 * reuses them instead of importing again.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The files. Bundled copies were fetched from the live addresses on 2026-10-16
 * (sha256 noted) and are byte-identical.
 *
 * @return array<string, array<string, mixed>>
 */
function ap_site_asset_defs() {
	return array(
		// Live's favicon set (e25-base-theme), largest size. sha256 55ebd5a0…df80.
		'site-icon' => array(
			'sources'  => array( 'https://www.assetpanda.com/wp-content/themes/e25-base-theme/src/images/favicon/android-chrome-512x512.png' ),
			'bundled'  => 'assets/site-icon-512.png',
			'filename' => 'asset-panda-site-icon.png',
			'mime'     => 'image/png',
			'title'    => 'Asset Panda site icon',
			'alt'      => 'Asset Panda',
			'min_size' => 512,
		),
		// Yoast's Organization logo on live (attachment 15293, 109×41). sha256 5a1bb334…e4cd.
		'org-logo'  => array(
			'sources'  => array( 'https://www.assetpanda.com/wp-content/uploads/2026/08/Logo-Asset-Panda.svg' ),
			'bundled'  => 'assets/logo-asset-panda.svg',
			'filename' => 'Logo-Asset-Panda.svg',
			'mime'     => 'image/svg+xml',
			'title'    => 'Logo Asset Panda',
			'alt'      => 'Asset Panda',
		),
	);
}

/**
 * An existing attachment imported earlier for this key, with its file present.
 *
 * @param string $key Asset key.
 * @return int Attachment id or 0.
 */
function ap_site_find_asset( $key ) {
	$ids = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'any',
			'numberposts'      => 5,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'meta_key'         => '_ap_site_asset', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'suppress_filters' => true,
		)
	);
	foreach ( (array) $ids as $id ) {
		$file = get_attached_file( (int) $id );
		if ( $file && file_exists( $file ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Checks a PNG: real PNG, square, at least min_size.
 *
 * @param string $bits File contents.
 * @param array  $def  Asset definition.
 * @return array{0:int,1:int}|false Width and height.
 */
function ap_site_check_png( $bits, array $def ) {
	if ( ! is_string( $bits ) || '' === $bits || "\x89PNG" !== substr( $bits, 0, 4 ) ) {
		return false;
	}
	$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bits ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! is_array( $info ) || IMAGETYPE_PNG !== $info[2] || $info[0] !== $info[1] ) {
		return false;
	}
	$min = isset( $def['min_size'] ) ? (int) $def['min_size'] : 0;
	if ( $info[0] < $min ) {
		return false;
	}
	return array( (int) $info[0], (int) $info[1] );
}

/**
 * Checks an SVG: an <svg> root, no script, event handlers, embedded HTML or
 * external references. Returns its width and height (attributes or viewBox).
 *
 * @param string $bits File contents.
 * @return array{0:int,1:int}|false
 */
function ap_site_check_svg( $bits ) {
	if ( ! is_string( $bits ) || '' === $bits || strlen( $bits ) > 512000 ) {
		return false;
	}
	if ( ! preg_match( '/^\s*(?:<\?xml[^>]*>\s*)?(?:<!--.*?-->\s*)*<svg\b([^>]*)>/is', $bits, $m ) ) {
		return false;
	}
	$unsafe = '/<\s*script|<\s*foreignObject|<\s*iframe|<\s*embed|<\s*object|\son[a-z]+\s*=|javascript:|(?:xlink:)?href\s*=\s*["\']\s*(?:https?:|data:|\/\/)|<!ENTITY/i';
	if ( preg_match( $unsafe, $bits ) ) {
		return false;
	}
	$root = $m[1];
	$w    = preg_match( '/\swidth\s*=\s*["\']\s*([0-9.]+)\s*(?:px)?\s*["\']/i', $root, $wm ) ? (float) $wm[1] : 0;
	$h    = preg_match( '/\sheight\s*=\s*["\']\s*([0-9.]+)\s*(?:px)?\s*["\']/i', $root, $hm ) ? (float) $hm[1] : 0;
	if ( ( ! $w || ! $h ) && preg_match( '/\sviewBox\s*=\s*["\']\s*[-0-9.]+[\s,]+[-0-9.]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)\s*["\']/i', $root, $vb ) ) {
		$w = (float) $vb[1];
		$h = (float) $vb[2];
	}
	if ( $w <= 0 || $h <= 0 ) {
		return false;
	}
	return array( (int) round( $w ), (int) round( $h ) );
}

/**
 * Validates file contents for a definition.
 *
 * @param string $bits Contents.
 * @param array  $def  Definition.
 * @return array{0:int,1:int}|false
 */
function ap_site_check_asset( $bits, array $def ) {
	return 'image/svg+xml' === $def['mime'] ? ap_site_check_svg( $bits ) : ap_site_check_png( $bits, $def );
}

/**
 * Fetches the file: live address first, then the bundled copy.
 *
 * @param array $def Definition.
 * @return array{bits: string, source: string, size: array}|WP_Error
 */
function ap_site_fetch_asset( array $def ) {
	$tried   = array();
	$sources = (array) apply_filters( 'ap_site_asset_sources', $def['sources'], $def );
	foreach ( $sources as $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'user-agent'  => 'Mozilla/5.0 (compatible; AssetPandaSite/' . AP_SITE_VERSION . '; +https://www.assetpanda.com/)',
			)
		);
		if ( is_wp_error( $response ) ) {
			$tried[] = $url . ': ' . $response->get_error_message();
			continue;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$bits = (string) wp_remote_retrieve_body( $response );
		$size = 200 === $code ? ap_site_check_asset( $bits, $def ) : false;
		if ( $size ) {
			return array(
				'bits'   => $bits,
				'source' => $url,
				'size'   => $size,
			);
		}
		$tried[] = $url . ': HTTP ' . $code . ( 200 === $code ? ', file did not validate' : '' );
	}
	$path = AP_SITE_DIR . '/' . $def['bundled'];
	$bits = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$size = ap_site_check_asset( $bits, $def );
	if ( $size ) {
		return array(
			'bits'   => $bits,
			'source' => 'bundled copy (' . $def['bundled'] . ')' . ( $tried ? '; live fetch failed: ' . implode( '; ', $tried ) : '' ),
			'size'   => $size,
		);
	}
	return new WP_Error( 'ap_site_asset_fetch', 'Could not get ' . $def['filename'] . ' from live or the bundled copy.' );
}

/**
 * Imports the file as an attachment.
 *
 * Written with wp_upload_bits() rather than the media uploader so the SVG
 * logo does not require SVG uploads to be enabled site-wide: the extension is
 * allowed only for this one write, after ap_site_check_svg() vetted the file.
 *
 * @param string $key  Asset key.
 * @param array  $def  Definition.
 * @param array  $file Result of ap_site_fetch_asset().
 * @return int|WP_Error Attachment id.
 */
function ap_site_insert_asset( $key, array $def, array $file ) {
	$ext   = strtolower( pathinfo( $def['filename'], PATHINFO_EXTENSION ) );
	$mimes = static function ( $list ) use ( $ext, $def ) {
		$list         = is_array( $list ) ? $list : array();
		$list[ $ext ] = $def['mime'];
		return $list;
	};
	add_filter( 'upload_mimes', $mimes, 99 );
	$upload = wp_upload_bits( $def['filename'], null, $file['bits'] );
	remove_filter( 'upload_mimes', $mimes, 99 );
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'ap_site_asset_write', 'Could not write ' . $def['filename'] . ': ' . $upload['error'] );
	}

	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $def['mime'],
			'post_title'     => $def['title'],
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload['url'],
		),
		$upload['file'],
		0,
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		wp_delete_file( $upload['file'] );
		return is_wp_error( $id ) ? $id : new WP_Error( 'ap_site_asset_insert', 'Could not create the attachment.' );
	}
	update_post_meta( $id, '_ap_site_asset', $key );
	update_post_meta( $id, '_ap_site_asset_source', $file['source'] );
	update_post_meta( $id, '_ap_site_asset_sha256', hash( 'sha256', $file['bits'] ) );
	if ( ! empty( $def['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $def['alt'] );
	}

	if ( 'image/svg+xml' === $def['mime'] ) {
		// WordPress cannot measure an SVG; store what the file declares, as live's attachment does.
		wp_update_attachment_metadata(
			$id,
			array(
				'width'    => $file['size'][0],
				'height'   => $file['size'][1],
				'file'     => _wp_relative_upload_path( $upload['file'] ),
				'filesize' => strlen( $file['bits'] ),
				'sizes'    => array(),
			)
		);
		return (int) $id;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$site_icon = null;
	if ( 'site-icon' === $key ) {
		// The sizes WordPress makes when an icon is chosen in the Customizer (32, 180, 192, 270).
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
		$site_icon = new WP_Site_Icon();
		update_post_meta( $id, '_wp_attachment_context', 'site-icon' );
		add_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
	}
	$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
	if ( $site_icon ) {
		remove_filter( 'intermediate_image_sizes_advanced', array( $site_icon, 'additional_sizes' ) );
	}
	if ( is_array( $meta ) && $meta ) {
		wp_update_attachment_metadata( $id, $meta );
	}
	return (int) $id;
}

/**
 * The attachment for a key: reused when already imported, imported otherwise.
 *
 * @param string $key     Asset key.
 * @param bool   $dry_run Report only.
 * @return array{id: int, url: string, status: string, source: string}|WP_Error
 */
function ap_site_ensure_asset( $key, $dry_run ) {
	$defs = ap_site_asset_defs();
	if ( ! isset( $defs[ $key ] ) ) {
		return new WP_Error( 'ap_site_asset_unknown', 'Unknown asset ' . $key );
	}
	$existing = ap_site_find_asset( $key );
	if ( $existing ) {
		return array(
			'id'     => $existing,
			'url'    => (string) wp_get_attachment_url( $existing ),
			'status' => 'existing',
			'source' => (string) get_post_meta( $existing, '_ap_site_asset_source', true ),
		);
	}
	if ( $dry_run ) {
		return array(
			'id'     => 0,
			'url'    => '',
			'status' => 'would import',
			'source' => implode( ', ', $defs[ $key ]['sources'] ) . ' (or the bundled copy)',
		);
	}
	$file = ap_site_fetch_asset( $defs[ $key ] );
	if ( is_wp_error( $file ) ) {
		return $file;
	}
	$id = ap_site_insert_asset( $key, $defs[ $key ], $file );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	return array(
		'id'     => $id,
		'url'    => (string) wp_get_attachment_url( $id ),
		'status' => 'imported',
		'source' => $file['source'],
	);
}
