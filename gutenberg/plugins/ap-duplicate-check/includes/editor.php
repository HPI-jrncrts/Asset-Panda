<?php
/**
 * Loads the editor scripts on the covered post types: the duplicate warning
 * (editor.js) and the "send changes to the live page" panel and window (push.js).
 *
 * @package APDuplicateCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( (string) $screen->post_type, ap_dupcheck_types(), true ) ) {
			return;
		}
		$ver = static function ( $file ) {
			$path = AP_DUPCHECK_DIR . '/assets/' . $file;
			return AP_DUPCHECK_VERSION . '.' . ( is_readable( $path ) ? filemtime( $path ) : 0 );
		};
		$post = get_post();

		// push.js first: the warning's "Send my changes…" action calls into it.
		wp_enqueue_style( 'ap-duplicate-check-push', plugins_url( 'assets/push.css', AP_DUPCHECK_FILE ), array( 'wp-components' ), $ver( 'push.css' ) );
		wp_enqueue_script(
			'ap-duplicate-check-push',
			plugins_url( 'assets/push.js', AP_DUPCHECK_FILE ),
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-editor', 'wp-edit-post' ),
			$ver( 'push.js' ),
			true
		);
		wp_add_inline_script(
			'ap-duplicate-check-push',
			'window.AP_DUPCHECK = ' . wp_json_encode(
				array(
					'target' => ( $post && 'publish' !== $post->post_status ) ? ap_dupcheck_target_info( ap_dupcheck_target_of( $post->ID ) ) : null,
				)
			) . ';',
			'before'
		);
		wp_enqueue_script(
			'ap-duplicate-check',
			plugins_url( 'assets/editor.js', AP_DUPCHECK_FILE ),
			array( 'wp-data', 'wp-api-fetch', 'wp-notices', 'wp-editor', 'wp-dom-ready', 'ap-duplicate-check-push' ),
			$ver( 'editor.js' ),
			true
		);
	}
);
