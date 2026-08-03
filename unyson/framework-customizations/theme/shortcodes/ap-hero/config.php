<?php
/**
 * AP Hero shortcode — Unyson page-builder registration (adapter).
 *
 * Registers the [ap-hero] shortcode in the Unyson builder. This file only
 * declares how the shortcode appears in the builder UI. The option->prop
 * mapping is in options.php; the render is in views/view.php. No markup here.
 *
 * @package AssetPanda\Unyson
 */

if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'AP Hero', 'unyson' ),
	'description' => __( 'Hero banner rendered by the shared Asset Panda UI kit.', 'unyson' ),
	'tab'         => __( 'Asset Panda', 'unyson' ),
	'popup_size'  => 'small',
);

return $cfg;
