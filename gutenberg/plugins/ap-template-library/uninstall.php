<?php
/**
 * Uninstall: removes this plugin's own data only — the settings option, every user's
 * saved builder progress, and the "created from template" marker on drafts.
 * Pages and resources created with the plugin stay as they are.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ap_tl_settings' );
delete_metadata( 'user', 0, 'ap_tl_progress', '', true );
delete_metadata( 'post', 0, '_ap_tl_template', '', true );
