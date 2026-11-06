<?php
/**
 * container — a styled box that holds other blocks (builder library, 2026-11).
 *
 * The box only: its inner blocks are rendered by the editor's block, which
 * puts them where <!--ap-slot--> is (gutenberg/blocks/container/render.php).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$classes = array(
	'ap-container',
	'ap-container--' . $props['look'],
	'ap-container--w-' . $props['width'],
	'ap-container--pad-' . $props['padding'],
	'ap-container--align-' . $props['text_align'],
	'ap-container--radius-' . $props['radius'],
);
$bg    = trim( (string) $props['background_image'] );
$style = '';
if ( '' !== $bg ) {
	$classes[] = 'ap-container--has-bg';
	$classes[] = 'ap-container--overlay-' . $props['overlay'];
	$style     = '--ap-container-bg:url("' . esc_url( $bg ) . '")';
}
if ( 'none' !== $props['decoration'] ) {
	$classes[] = 'ap-container--deco-' . $props['decoration'];
}
if ( 'panda' === $props['decoration'] ) {
	$style .= ( '' !== $style ? ';' : '' ) . '--ap-container-panda:url("' . esc_url( ap_ui_asset_url( 'container', 'panda.svg' ) ) . '")';
}
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<?php if ( 'none' !== $props['decoration'] ) : ?>
		<span class="ap-container__deco" aria-hidden="true"></span>
	<?php endif; ?>
	<div class="ap-container__inner"><!--ap-slot--></div>
</section>
