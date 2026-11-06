<?php
/**
 * Plugin Name:       AP Builder
 * Description:       The Asset Panda page builder's library, docked under the block editor on every page, post, resource and press release: visual elements (site art and the Media Library, with upload), animations, styled containers, every section design used on the site, whole-page layouts from the Template Library, an AI helper that builds sections from those designs through the site's AI connector (Settings → Connectors), and (1.1.0) the Video Library: YouTube videos added once, synced from the channel, and shown where you choose (Video Tutorials page, integration pages, featured on any page). Editor only: nothing loads on a visitor's page except the small animation sheet on pages that use an animation.
 * Version:           1.1.4
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Asset Panda
 * License:           GPL-2.0-or-later
 * Text Domain:       ap-builder
 *
 * Works with the Asset Panda UI Kit (blocks, Visual and Container, the
 * ap-anim-* styles) and, for layouts, the AP Template Library; without either
 * it still loads, and the cabinets that need them say so.
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_BUILDER_VERSION', '1.1.4' );
define( 'AP_BUILDER_FILE', __FILE__ );
define( 'AP_BUILDER_DIR', __DIR__ );
define( 'AP_BUILDER_REST_NS', 'ap-builder/v1' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

foreach ( array( 'catalog', 'animations', 'ai', 'library', 'videos', 'video-block', 'video-sync' ) as $ap_builder_include ) {
	if ( ! is_readable( AP_BUILDER_DIR . '/includes/' . $ap_builder_include . '.php' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $ap_builder_include ) {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p><strong>AP Builder:</strong> includes/' . esc_html( $ap_builder_include ) . '.php is missing; the plugin is idle. Re-upload the zip.</p></div>';
				}
			}
		);
		return;
	}
}
foreach ( array( 'catalog', 'animations', 'ai', 'library', 'videos', 'video-block', 'video-sync' ) as $ap_builder_include ) {
	require_once AP_BUILDER_DIR . '/includes/' . $ap_builder_include . '.php';
}
unset( $ap_builder_include );
