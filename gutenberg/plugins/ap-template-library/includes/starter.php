<?php
/**
 * Builder v1 (2026-11): start a new resource from a template without leaving
 * the block editor.
 *
 * WordPress offers "starter" patterns (patterns for core/post-content) when a
 * new, empty post opens. Each enabled template becomes one, for the post type
 * it creates (pages too, since 1.2.0), holding its reference's blocks: the
 * same templates and the same published references the Library uses. Pick
 * one, then click any text or image to change it. The Library's own screen
 * (fill in boxes, "Send to Draft") stays.
 *
 * Patterns are read over REST (and REST preloading on the editor screen), so
 * they are registered on rest_api_init only: a visitor's page never resolves
 * a reference for this.
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the starter patterns, once per request. */
function ap_tl_register_starters() {
	static $done = false;
	if ( $done || ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$done = true;

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'ap-templates',
			array(
				'label'       => __( 'Asset Panda: templates', 'ap-template-library' ),
				'description' => __( 'Whole layouts from real published pages of the site, to start a new one from.', 'ap-template-library' ),
			)
		);
	}

	foreach ( ap_tl_templates() as $key => $template ) {
		try {
			$context = ap_tl_template_context( $template );
		} catch ( \Throwable $e ) {
			continue;
		}
		if ( 'ok' !== $context['status'] || ! $context['post'] ) {
			continue;
		}
		$content = (string) $context['post']->post_content;
		if ( '' === trim( $content ) || ! has_blocks( $content ) ) {
			continue;
		}
		register_block_pattern(
			'ap-template-library/start-' . sanitize_key( $key ),
			array(
				/* translators: %s: template name, e.g. "Case study". */
				'title'         => sprintf( __( 'Start from: %s', 'ap-template-library' ), $template['label'] ),
				'description'   => (string) $template['description'],
				'content'       => $content,
				'blockTypes'    => array( 'core/post-content' ),
				'postTypes'     => array( $context['post_type'] ),
				'categories'    => array( 'ap-templates' ),
				'viewportWidth' => 1200,
			)
		);
	}
}
add_action( 'rest_api_init', 'ap_tl_register_starters' );

/**
 * The theme's stock starter patterns (Twenty Twenty-Five's "Event RSVP",
 * "Coming soon", "Link in bio"…) are not the site's design: they leave the
 * new-page chooser, so it offers the site's own templates only. They stay
 * available in the block inserter.
 */
function ap_tl_drop_theme_starters() {
	if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
		return;
	}
	$registry = WP_Block_Patterns_Registry::get_instance();
	$prefixes = array_unique( array( get_template() . '/', get_stylesheet() . '/' ) );
	foreach ( $registry->get_all_registered() as $pattern ) {
		$name  = isset( $pattern['name'] ) ? (string) $pattern['name'] : '';
		$types = isset( $pattern['blockTypes'] ) ? (array) $pattern['blockTypes'] : array();
		if ( ! in_array( 'core/post-content', $types, true ) ) {
			continue;
		}
		$theme_owned = false;
		foreach ( $prefixes as $prefix ) {
			$theme_owned = $theme_owned || 0 === strpos( $name, $prefix );
		}
		if ( ! $theme_owned ) {
			continue;
		}
		$props = $pattern;
		unset( $props['name'] );
		$props['blockTypes'] = array_values( array_diff( $types, array( 'core/post-content' ) ) );
		if ( ! $props['blockTypes'] ) {
			unset( $props['blockTypes'] );
		}
		$registry->unregister( $name );
		register_block_pattern( $name, $props );
	}
}
add_action( 'rest_api_init', 'ap_tl_drop_theme_starters', 20 );
