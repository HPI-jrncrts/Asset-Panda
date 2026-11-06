<?php
/**
 * visual — one image as a design element (builder library, 2026-11).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image = trim( (string) $props['image'] );
if ( '' === $image ) {
	return;
}

$classes = array(
	'ap-visual',
	'ap-visual--' . $props['shape'],
	'ap-visual--size-' . $props['size'],
	'ap-visual--fit-' . $props['fit'],
	'ap-visual--align-' . $props['position'],
);
if ( 'none' !== $props['effect'] ) {
	$classes[] = 'ap-visual--fx-' . $props['effect'];
}
$caption = trim( (string) $props['caption'] );
$link    = trim( (string) $props['link_url'] );
?>
<figure class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<span class="ap-visual__frame">
		<?php if ( '' !== $link ) : ?>
			<a class="ap-visual__link" href="<?php echo esc_url( $link ); ?>">
		<?php endif; ?>
		<img class="ap-visual__img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['alt'] ); ?>" loading="lazy" decoding="async" />
		<?php if ( '' !== $link ) : ?>
			</a>
		<?php endif; ?>
	</span>
	<?php if ( '' !== $caption ) : ?>
		<figcaption class="ap-visual__caption"><?php echo esc_html( $caption ); ?></figcaption>
	<?php endif; ?>
</figure>
