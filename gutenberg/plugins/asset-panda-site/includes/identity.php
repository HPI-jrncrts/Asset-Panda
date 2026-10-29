<?php
/**
 * Site identity as live has it, applied once on request (never on activation).
 *
 * Live (www.assetpanda.com/wp-json/, 2026-10-16): name "Asset Panda",
 * description "Asset Management Software &amp; Asset Intelligence Platform",
 * gmt_offset "-5" with an empty timezone_string (the manual "UTC-5" choice
 * in Settings → General, no daylight saving), site_icon 0 (live's favicons
 * come from the old theme's files, which leave with it).
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options to set, in order (timezone_string before gmt_offset: WordPress
 * derives gmt_offset from timezone_string while that is set).
 *
 * @return array<string, string>
 */
function ap_site_identity_plan() {
	return (array) apply_filters(
		'ap_site_identity_plan',
		array(
			'blogname'        => 'Asset Panda',
			'blogdescription' => 'Asset Management Software & Asset Intelligence Platform',
			'timezone_string' => '',
			'gmt_offset'      => '-5',
		)
	);
}

/**
 * Applies site identity and the site icon. Idempotent.
 *
 * @param bool $dry_run    Report only.
 * @param bool $force_icon Replace a site icon someone else already chose.
 * @return array|WP_Error
 */
function ap_site_apply_identity( $dry_run = false, $force_icon = false ) {
	$report = ap_site_report( 'identity', $dry_run );

	foreach ( ap_site_identity_plan() as $name => $want ) {
		$target = (string) sanitize_option( $name, $want ); // The form WordPress stores (& becomes &amp;).
		$before = get_option( $name );
		if ( is_scalar( $before ) && (string) $before === $target ) {
			$report['unchanged'][ $name ] = $before;
			continue;
		}
		if ( $dry_run ) {
			$report['changed'][ $name ] = array(
				'from' => $before,
				'to'   => $target,
			);
			continue;
		}
		update_option( $name, $want );
		$after = get_option( $name );
		if ( is_scalar( $after ) && (string) $after === $target ) {
			$report['changed'][ $name ] = array(
				'from' => $before,
				'to'   => $after,
			);
		} else {
			$report['failed'][ $name ] = 'stored value is ' . wp_json_encode( $after );
		}
	}

	// Site icon: live's favicon, imported into the media library.
	$current = (int) get_option( 'site_icon' );
	$valid   = $current && 'attachment' === get_post_type( $current ) && get_attached_file( $current ) && file_exists( (string) get_attached_file( $current ) );
	$ours    = ap_site_find_asset( 'site-icon' );
	if ( $valid && $current === $ours ) {
		$report['unchanged']['site_icon'] = $current;
	} elseif ( $valid && ! $force_icon ) {
		$report['unchanged']['site_icon'] = $current;
		$report['notes'][]                = 'site_icon: kept the existing icon (attachment ' . $current . '); pass force_icon=true to replace it with live\'s favicon';
	} else {
		$asset = ap_site_ensure_asset( 'site-icon', $dry_run );
		if ( is_wp_error( $asset ) ) {
			$report['failed']['site_icon'] = $asset->get_error_message();
		} elseif ( $dry_run ) {
			$report['changed']['site_icon'] = array(
				'from' => $current,
				'to'   => $asset['id'] ? $asset['id'] : 'imported from ' . $asset['source'],
			);
		} else {
			update_option( 'site_icon', (int) $asset['id'] );
			$report['changed']['site_icon'] = array(
				'from' => $current,
				'to'   => (int) $asset['id'],
			);
			$report['site_icon'] = $asset;
		}
	}

	return ap_site_finish_report( $report );
}
