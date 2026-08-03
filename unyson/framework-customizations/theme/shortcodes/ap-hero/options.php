<?php
/**
 * AP Hero shortcode — Unyson options (adapter schema).
 *
 * Declares the builder fields the editor fills in. Option ids mirror the kit's
 * hero contract (ui/components/hero/component.json) so the mapping in view.php
 * stays one-to-one. Option names are the shortcode's concern; prop names are
 * the kit's — view.php adapts between them. No markup here.
 *
 * @package AssetPanda\Unyson
 */

if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$options = array(
	'eyebrow'   => array(
		'type'  => 'text',
		'label' => __( 'Eyebrow', 'unyson' ),
		'desc'  => __( 'Small label rendered above the heading.', 'unyson' ),
		'value' => '',
	),
	'title'     => array(
		'type'  => 'text',
		'label' => __( 'Title', 'unyson' ),
		'desc'  => __( 'Main heading text (required).', 'unyson' ),
		'value' => '',
	),
	'subtitle'  => array(
		'type'  => 'textarea',
		'label' => __( 'Subtitle', 'unyson' ),
		'desc'  => __( 'Supporting copy shown under the heading.', 'unyson' ),
		'value' => '',
	),
	'cta_label' => array(
		'type'  => 'text',
		'label' => __( 'CTA label', 'unyson' ),
		'desc'  => __( 'Button text. The button renders only when both label and URL are set.', 'unyson' ),
		'value' => '',
	),
	'cta_url'   => array(
		'type'  => 'text',
		'label' => __( 'CTA URL', 'unyson' ),
		'desc'  => __( 'Button destination URL.', 'unyson' ),
		'value' => '',
	),
	'alignment' => array(
		'type'    => 'select',
		'label'   => __( 'Alignment', 'unyson' ),
		'value'   => 'center',
		'choices' => array(
			'center' => __( 'Center', 'unyson' ),
			'left'   => __( 'Left', 'unyson' ),
		),
	),
	'theme'     => array(
		'type'    => 'select',
		'label'   => __( 'Theme', 'unyson' ),
		'value'   => 'light',
		'choices' => array(
			'light' => __( 'Light', 'unyson' ),
			'dark'  => __( 'Dark', 'unyson' ),
			'brand' => __( 'Brand', 'unyson' ),
		),
	),
);

return $options;
