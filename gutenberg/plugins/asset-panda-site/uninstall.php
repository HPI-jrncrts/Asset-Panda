<?php
/**
 * Uninstall: removes this plugin's own options only.
 *
 * Yoast settings, site identity, the site icon and the imported logo stay as
 * applied: they are site configuration now, not plugin state.
 *
 * @package AssetPandaSite
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ap_site_settings' );
delete_option( 'ap_site_last_apply' );
