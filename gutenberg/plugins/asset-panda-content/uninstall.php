<?php
/**
 * Uninstall: remove this plugin's own options only.
 *
 * Resources, press releases, pages, their terms and their meta are content:
 * they stay in the database (reinstalling the plugin brings them back at the
 * same URLs). Yoast's options written by the archive-seo endpoint belong to
 * Yoast and stay too.
 *
 * @package AssetPandaContent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'ap_content_rewrite_ver', 'ap_content_terms_ver', 'ap_content_last_error', 'ap_content_search_ver', 'ap_content_industry_seed', 'ap_content_industry_seed_report' ) as $ap_content_option ) {
	delete_option( $ap_content_option );
}
foreach ( array( 'ap_content_search_index', 'ap_content_listing_counts', 'ap_content_industry_seeding' ) as $ap_content_transient ) {
	delete_transient( $ap_content_transient );
}
delete_option( 'rewrite_rules' );
