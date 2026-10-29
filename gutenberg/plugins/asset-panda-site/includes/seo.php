<?php
/**
 * Yoast SEO parity with live, applied once on request (never on activation),
 * plus the sitemap exclusions that the apply switches on.
 *
 * Values come from live's rendered output (www.assetpanda.com, Yoast 28.3,
 * 2026-10-16): the Organization node of the schema graph, og/twitter tags,
 * verification metas, observed title formats and archive behaviour.
 * Everything is written through WPSEO_Options::set(), so Yoast's own
 * validation and option watchers run (enabling llms.txt makes Yoast write the
 * file). Per-page and per-term SEO (titles, descriptions, noindex) is not
 * touched; that belongs to the copy job and the content plugin.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Yoast present with the option API this file uses. */
function ap_site_yoast_ready() {
	return class_exists( 'WPSEO_Options' ) && method_exists( 'WPSEO_Options', 'set' ) && method_exists( 'WPSEO_Options', 'get' );
}

/** Yoast version string, or ''. */
function ap_site_yoast_version() {
	return defined( 'WPSEO_VERSION' ) ? (string) WPSEO_VERSION : '';
}

/**
 * The settings to apply, in order. Entries with 'post_type' or 'taxonomy'
 * only apply once that type is registered (the content plugin registers
 * resource, newsroom and resource-types).
 *
 * @return array<int, array<string, mixed>>
 */
function ap_site_seo_plan() {
	$title = '%%title%% %%page%% %%sep%% %%sitename%%';
	$plan  = array(
		// Verification (live's head: google-site-verification, msvalidate.01).
		array( 'key' => 'googleverify', 'value' => '5R0O3t1WlUReAiV67NmZm-TZ5XPgkrvXas6D0RFgYDM' ),
		array( 'key' => 'msverify', 'value' => '71FFA2A80364137CC63DA962747E39F9' ),
		// llms.txt (live serves Yoast's generated /llms.txt, automatic page selection).
		array( 'key' => 'enable_llms_txt', 'value' => true ),

		// Social: article:publisher, twitter:site and the Organization sameAs list.
		array( 'key' => 'opengraph', 'value' => true ),
		array( 'key' => 'twitter', 'value' => true ),
		array( 'key' => 'facebook_site', 'value' => 'https://business.facebook.com/AssetPanda/' ),
		array( 'key' => 'twitter_site', 'value' => 'assetpanda' ),
		array(
			'key'   => 'other_social_urls',
			'value' => array(
				'https://www.instagram.com/assetpanda/',
				'https://www.linkedin.com/company/asset-panda/',
				'https://www.youtube.com/AssetPanda',
			),
		),

		// Knowledge graph: Organization "Asset Panda" (the logo is added by the apply).
		array( 'key' => 'company_or_person', 'value' => 'company' ),
		array( 'key' => 'company_name', 'value' => 'Asset Panda' ),

		// Titles: "Page - Asset Panda" (separator "-", Yoast's default formats).
		array( 'key' => 'separator', 'value' => 'sc-dash' ),
		array( 'key' => 'title-page', 'value' => $title, 'post_type' => 'page' ),
		array( 'key' => 'title-post', 'value' => $title, 'post_type' => 'post' ),
		array( 'key' => 'title-resource', 'value' => $title, 'post_type' => 'resource' ),
		array( 'key' => 'title-newsroom', 'value' => $title, 'post_type' => 'newsroom' ),
		// Category archives on live read "Resource Category: Equipment" on every page.
		array( 'key' => 'title-tax-category', 'value' => 'Resource Category: %%term_title%%', 'taxonomy' => 'category' ),

		// Indexable: resources, press releases, and the Resource Type listings
		// (/resource-center/<type>/, which replace live's listing pages).
		array( 'key' => 'noindex-resource', 'value' => false, 'post_type' => 'resource' ),
		array( 'key' => 'noindex-newsroom', 'value' => false, 'post_type' => 'newsroom' ),
		array( 'key' => 'noindex-tax-resource-types', 'value' => false, 'taxonomy' => 'resource-types' ),

		// Author archives redirect to the homepage on live (/author/admin/ → 301 /).
		array( 'key' => 'disable-author', 'value' => true ),
	);
	return (array) apply_filters( 'ap_site_seo_plan', $plan );
}

/**
 * Fresh read of one Yoast setting (Yoast caches option values per request).
 *
 * @param string $key Setting key.
 * @return mixed Null when not stored.
 */
function ap_site_yoast_get( $key ) {
	if ( method_exists( 'WPSEO_Options', 'clear_cache' ) ) {
		WPSEO_Options::clear_cache();
	}
	return WPSEO_Options::get( $key, null );
}

/**
 * Whether a stored value already equals the wanted one.
 *
 * @param mixed $have Stored.
 * @param mixed $want Wanted.
 * @return bool
 */
function ap_site_same( $have, $want ) {
	if ( null === $have ) {
		return false;
	}
	if ( is_bool( $want ) ) {
		return is_bool( $have ) ? $have === $want : ( (bool) $have === $want && ! is_array( $have ) );
	}
	if ( is_int( $want ) ) {
		return is_numeric( $have ) && (int) $have === $want;
	}
	if ( is_array( $want ) ) {
		if ( ! is_array( $have ) ) {
			return false;
		}
		if ( isset( $want['url'], $want['width'], $want['height'] ) ) {
			// Image meta: the parts Yoast prints.
			foreach ( array( 'url', 'width', 'height', 'id' ) as $k ) {
				if ( ! isset( $have[ $k ] ) || (string) $have[ $k ] !== (string) $want[ $k ] ) {
					return false;
				}
			}
			return true;
		}
		return array_values( array_map( 'ap_site_loose', $have ) ) === array_values( array_map( 'ap_site_loose', $want ) );
	}
	return is_scalar( $have ) && ap_site_loose( $have ) === ap_site_loose( $want );
}

/**
 * A text value as Yoast's validation may hand it back: trimmed, without a
 * leading @ (Twitter handles) or a trailing slash (URLs). Comparing in this
 * form keeps a repeat apply from treating Yoast's own clean-up as a change.
 *
 * @param mixed $value Value.
 * @return string
 */
function ap_site_loose( $value ) {
	if ( ! is_scalar( $value ) ) {
		return 'json:' . wp_json_encode( $value );
	}
	return rtrim( ltrim( trim( (string) $value ), '@' ), '/' );
}

/**
 * Applies one Yoast setting and records the outcome.
 *
 * @param string $key     Setting key.
 * @param mixed  $want    Wanted value.
 * @param bool   $dry_run Report only.
 * @param array  $report  Report (by reference).
 */
function ap_site_yoast_apply_one( $key, $want, $dry_run, array &$report ) {
	$before = ap_site_yoast_get( $key );
	if ( ap_site_same( $before, $want ) ) {
		$report['unchanged'][ $key ] = $before;
		return;
	}
	if ( $dry_run ) {
		$report['changed'][ $key ] = array(
			'from' => $before,
			'to'   => $want,
		);
		return;
	}
	WPSEO_Options::set( $key, $want );
	$after = ap_site_yoast_get( $key );
	if ( null === $after ) {
		$report['failed'][ $key ] = 'Yoast did not store it (unknown key for this Yoast version)';
		return;
	}
	if ( null !== $before && ap_site_same( $after, $before ) ) {
		// Nothing moved although the value differs from the wanted one: Yoast's
		// validation rejected it. Reported as failed on every run until fixed.
		$report['failed'][ $key ] = 'Yoast kept ' . wp_json_encode( $after ) . ' and rejected ' . wp_json_encode( $want );
		return;
	}
	$report['changed'][ $key ] = array(
		'from' => $before,
		'to'   => $after,
	);
	if ( ! ap_site_same( $after, $want ) ) {
		$report['notes'][] = $key . ': stored as ' . wp_json_encode( $after ) . ' after Yoast validation';
	}
}

/**
 * Yoast's image meta for the logo, in the shape Yoast stores itself
 * (WPSEO_Image_Utils::get_data). Built here because WordPress cannot size an
 * SVG, so Yoast's own lookup would come back without a URL.
 *
 * @param int $id Attachment id.
 * @return array|false
 */
function ap_site_logo_meta( $id ) {
	$meta = wp_get_attachment_metadata( $id );
	$url  = wp_get_attachment_url( $id );
	if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) || ! $url ) {
		return false;
	}
	return array(
		'width'    => (int) $meta['width'],
		'height'   => (int) $meta['height'],
		'filesize' => isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0,
		'url'      => $url,
		'path'     => (string) get_attached_file( $id ),
		'size'     => 'full',
		'id'       => (int) $id,
		'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		'pixels'   => (int) $meta['width'] * (int) $meta['height'],
		'type'     => (string) get_post_mime_type( $id ),
	);
}

/**
 * A fresh report structure.
 *
 * @param string $action  Action name.
 * @param bool   $dry_run Report only.
 * @return array
 */
function ap_site_report( $action, $dry_run ) {
	return array(
		'ok'        => true,
		'action'    => $action,
		'dry_run'   => (bool) $dry_run,
		'host'      => ap_site_request_host(),
		'changed'   => array(),
		'unchanged' => array(),
		'failed'    => array(),
		'notes'     => array(),
	);
}

/**
 * Finishes a report and remembers it for the settings page.
 *
 * @param array $report Report.
 * @return array
 */
function ap_site_finish_report( array $report ) {
	$report['ok']      = empty( $report['failed'] );
	$report['summary'] = sprintf(
		'%s%d changed, %d unchanged, %d failed',
		$report['dry_run'] ? 'dry run: ' : '',
		count( $report['changed'] ),
		count( $report['unchanged'] ),
		count( $report['failed'] )
	);
	if ( ! $report['dry_run'] ) {
		$last                      = get_option( AP_SITE_LAST_APPLY, array() );
		$last                      = is_array( $last ) ? $last : array();
		$user                      = wp_get_current_user();
		$last[ $report['action'] ] = array(
			'time'    => gmdate( 'c' ),
			'user'    => $user && $user->exists() ? $user->user_login : '',
			'summary' => $report['summary'],
			'report'  => $report,
		);
		update_option( AP_SITE_LAST_APPLY, $last, false );
	}
	return $report;
}

/**
 * Applies the Yoast settings. Idempotent: a second run reports everything
 * unchanged and writes nothing.
 *
 * @param bool $dry_run       Report only.
 * @param bool $refresh_llms  Regenerate llms.txt (off → on) when it is already on,
 *                            e.g. after the domain search-replace.
 * @return array|WP_Error
 */
function ap_site_apply_seo( $dry_run = false, $refresh_llms = false ) {
	if ( ! ap_site_yoast_ready() ) {
		return new WP_Error( 'ap_site_no_yoast', 'Yoast SEO is not active (WPSEO_Options not found). Activate Yoast SEO, then apply again.', array( 'status' => 409 ) );
	}
	$report                  = ap_site_report( 'seo', $dry_run );
	$report['yoast_version'] = ap_site_yoast_version();

	foreach ( ap_site_seo_plan() as $item ) {
		if ( ! is_array( $item ) || empty( $item['key'] ) || ! array_key_exists( 'value', $item ) ) {
			continue;
		}
		if ( ! empty( $item['post_type'] ) && ! post_type_exists( $item['post_type'] ) ) {
			$report['failed'][ $item['key'] ] = 'post type ' . $item['post_type'] . ' is not registered yet (activate the content plugin, then apply again)';
			continue;
		}
		if ( ! empty( $item['taxonomy'] ) && ! taxonomy_exists( $item['taxonomy'] ) ) {
			$report['failed'][ $item['key'] ] = 'taxonomy ' . $item['taxonomy'] . ' is not registered yet (activate the content plugin, then apply again)';
			continue;
		}
		ap_site_yoast_apply_one( $item['key'], $item['value'], $dry_run, $report );
	}

	// Organization logo: live's SVG, imported into the media library.
	$logo = ap_site_ensure_asset( 'org-logo', $dry_run );
	if ( is_wp_error( $logo ) ) {
		$report['failed']['company_logo'] = $logo->get_error_message();
	} elseif ( ! $logo['id'] ) {
		$report['changed']['company_logo'] = array(
			'from' => ap_site_yoast_get( 'company_logo' ),
			'to'   => 'imported from ' . $logo['source'],
		);
	} else {
		$report['logo'] = $logo;
		ap_site_yoast_apply_one( 'company_logo', $logo['url'], $dry_run, $report );
		ap_site_yoast_apply_one( 'company_logo_id', (int) $logo['id'], $dry_run, $report );
		$meta = ap_site_logo_meta( $logo['id'] );
		if ( $meta ) {
			ap_site_yoast_apply_one( 'company_logo_meta', $meta, $dry_run, $report );
		} else {
			$report['failed']['company_logo_meta'] = 'the logo attachment has no width/height';
		}
	}

	// Regenerate llms.txt on request (Yoast rewrites it when the switch goes off → on).
	if ( $refresh_llms && true === ap_site_yoast_get( 'enable_llms_txt' ) && ! isset( $report['changed']['enable_llms_txt'] ) ) {
		if ( ! $dry_run ) {
			WPSEO_Options::set( 'enable_llms_txt', false );
			WPSEO_Options::set( 'enable_llms_txt', true );
		}
		$report['notes'][] = 'llms.txt ' . ( $dry_run ? 'would be' : 'was' ) . ' regenerated';
	}

	// Sitemap exclusions (thank-you, login splash, test and placeholder pages).
	if ( ap_site_setting( 'sitemap_exclude' ) ) {
		$report['unchanged']['ap_site:sitemap_exclude'] = true;
	} else {
		if ( ! $dry_run ) {
			ap_site_update_settings( array( 'sitemap_exclude' => true ) );
		}
		$report['changed']['ap_site:sitemap_exclude'] = array(
			'from' => false,
			'to'   => true,
		);
	}
	$report['sitemap_excluded_pages'] = array_values( ap_site_sitemap_excluded_pages() );

	// Old Resource Type addresses (/resource-category/<term>/) are not archives here.
	$tax = get_taxonomy( 'resource-types' );
	if ( $tax ) {
		$slug              = is_array( $tax->rewrite ) && isset( $tax->rewrite['slug'] ) ? (string) $tax->rewrite['slug'] : '';
		$report['notes'][] = 'resource-types archives live at /' . $slug . '/<term>/' . ( 'resource-category' === $slug ? ' (WARNING: the old live base; these were noindex on live)' : '; /resource-category/<term>/ is not an archive base (the content plugin redirects it)' );
	}
	if ( ! get_option( 'blog_public' ) ) {
		$report['notes'][] = 'Search engine visibility is off (blog_public = 0): Yoast prints noindex everywhere until it is switched on at go-live.';
	}

	return ap_site_finish_report( $report );
}

/* ── Sitemap exclusions ───────────────────────────────────────────────── */

/**
 * Pages whose path matches the exclusion patterns: id => path.
 *
 * One query for all pages, paths built from slugs and parents (no per-page
 * lookups), cached until any post changes.
 *
 * @param array|null $patterns Patterns; the setting when null.
 * @return array<int, string>
 */
function ap_site_sitemap_excluded_pages( $patterns = null ) {
	global $wpdb;
	$patterns  = is_array( $patterns ) ? $patterns : (array) ap_site_setting( 'sitemap_exclude_patterns' );
	$cache_key = 'sitemap_excluded:' . md5( wp_json_encode( $patterns ) . '|' . wp_cache_get_last_changed( 'posts' ) );
	$cached    = wp_cache_get( $cache_key, 'ap_site' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$rows = $wpdb->get_results( "SELECT ID, post_name, post_parent, post_status FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash', 'auto-draft', 'inherit')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$pages = array();
	foreach ( (array) $rows as $row ) {
		$pages[ (int) $row->ID ] = $row;
	}
	$out = array();
	foreach ( $pages as $id => $row ) {
		if ( 'publish' !== $row->post_status ) {
			continue;
		}
		$parts = array();
		$cur   = $row;
		$guard = 0;
		while ( $cur && $guard++ < 20 ) {
			array_unshift( $parts, urldecode( (string) $cur->post_name ) );
			$parent = (int) $cur->post_parent;
			$cur    = ( $parent && isset( $pages[ $parent ] ) ) ? $pages[ $parent ] : null;
		}
		$path = '/' . implode( '/', $parts ) . '/';
		if ( ap_site_path_matches( $path, $patterns ) ) {
			$out[ $id ] = $path;
		}
	}
	wp_cache_set( $cache_key, $out, 'ap_site', HOUR_IN_SECONDS );
	return $out;
}

add_filter(
	'wpseo_exclude_from_sitemap_by_post_ids',
	static function ( $ids ) {
		return ap_site_guard(
			static function () use ( $ids ) {
				$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
				if ( ! ap_site_setting( 'sitemap_exclude' ) ) {
					return $ids;
				}
				return array_values( array_unique( array_merge( $ids, array_keys( ap_site_sitemap_excluded_pages() ) ) ) );
			},
			$ids
		);
	}
);
