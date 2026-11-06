<?php
/**
 * The library panel: a meta box ("Asset Panda Library") in the block
 * editor's bottom pane, under the page being edited, on every post type that
 * uses the block editor. assets/library.js draws the cabinets into it; the
 * box itself is only a mount point and saves nothing.
 *
 * @package APBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types that get the library: those edited in the block editor and shown in wp-admin. */
function ap_builder_post_types() {
	$out = array();
	foreach ( get_post_types( array( 'show_ui' => true ), 'names' ) as $type ) {
		if ( 'attachment' !== $type && function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $type ) ) {
			$out[] = $type;
		}
	}
	return $out;
}

add_action(
	'add_meta_boxes',
	static function ( $post_type ) {
		if ( ! current_user_can( 'edit_posts' ) || ! in_array( $post_type, ap_builder_post_types(), true ) ) {
			return;
		}
		add_meta_box(
			'ap-builder-library',
			__( 'Asset Panda Library', 'ap-builder' ),
			static function () {
				echo '<div id="ap-builder-library-root" class="ap-builder"><p class="ap-builder__loading">' . esc_html__( 'Loading the library…', 'ap-builder' ) . '</p></div>';
			},
			null,
			'normal',
			'high',
			array( '__back_compat_meta_box' => false )
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base || ! in_array( (string) $screen->post_type, ap_builder_post_types(), true ) ) {
			return; // The Site Editor and widgets have no meta box area.
		}
		$js  = AP_BUILDER_DIR . '/assets/library.js';
		$css = AP_BUILDER_DIR . '/assets/library.css';
		if ( ! is_readable( $js ) ) {
			return;
		}
		wp_enqueue_style( 'ap-builder-library', plugins_url( 'assets/library.css', AP_BUILDER_FILE ), array( 'wp-components' ), AP_BUILDER_VERSION . '.' . ( is_readable( $css ) ? filemtime( $css ) : 0 ) );
		wp_enqueue_script(
			'ap-builder-library',
			plugins_url( 'assets/library.js', AP_BUILDER_FILE ),
			array( 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-api-fetch', 'wp-i18n', 'wp-compose', 'wp-notices', 'wp-dom-ready', 'wp-server-side-render' ),
			AP_BUILDER_VERSION . '.' . filemtime( $js ),
			true
		);
		$animations = array();
		foreach ( ap_builder_animations() as $class => $meta ) {
			$animations[] = array(
				'class' => $class,
				'label' => $meta[0],
				'group' => $meta[1],
				'note'  => $meta[2],
			);
		}
		wp_add_inline_script(
			'ap-builder-library',
			'window.AP_BUILDER = ' . wp_json_encode(
				array(
					'ns'         => AP_BUILDER_REST_NS,
					'postType'   => (string) $screen->post_type,
					'kit'        => defined( 'AP_UI_URL' ) ? untrailingslashit( AP_UI_URL ) : '',
					'hasKit'     => function_exists( 'ap_render_component' ),
					'animations' => $animations,
					'connectors' => admin_url( 'options-connectors.php' ),
					'canUpload'  => current_user_can( 'upload_files' ),
				)
			) . ';',
			'before'
		);
	}
);
