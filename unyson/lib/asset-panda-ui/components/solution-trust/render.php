<?php
/**
 * solution-trust — the solution pages' customer logo strip.
 *
 * Matches the live .epx-trust section: the label (its end in the brand
 * gradient) beside the logo row; below 901px the label sits above a masked
 * marquee, so every logo is written twice (the copies are hidden from
 * assistive tech and shown only while scrolling).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['image'] ?? '' ) ) ) {
		continue;
	}
	$items[] = array(
		'image' => trim( (string) $item['image'] ),
		'alt'   => (string) ( $item['alt'] ?? '' ),
		'cover' => 'cover' === ( $item['fit'] ?? '' ),
	);
}

if ( empty( $items ) ) {
	return;
}

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
$label   = trim( (string) $props['label'] );
$accent  = trim( (string) $props['accent'] );
$dims    = 'readiness' === $variant ? ' width="110" height="80"' : ' width="120" height="60"';

// The accent is the end of the label: escape first, then wrap that slice.
$label_html = esc_html( $label );
if ( '' !== $accent && false !== strpos( $label, $accent ) ) {
	$escaped    = esc_html( $accent );
	$label_html = str_replace( $escaped, '<span class="ap-solution-trust__g">' . $escaped . '</span>', $label_html );
}
?>
<section class="ap-solution-trust ap-solution-trust--<?php echo esc_attr( $variant ); ?>">
	<div class="ap-solution-trust__wrap">
		<?php if ( '' !== $label ) : ?>
			<p class="ap-solution-trust__label ap-solution-trust__rev"><?php echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed span is added. ?></p>
		<?php endif; ?>
		<div class="ap-solution-trust__ticker">
			<div class="ap-solution-trust__grid">
				<?php foreach ( $items as $i => $item ) : ?>
					<img class="ap-solution-trust__rev ap-solution-trust__rev--d<?php echo esc_attr( (string) min( $i + 1, 5 ) ); ?><?php echo $item['cover'] ? ' ap-solution-trust__cover' : ''; ?>" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>"<?php echo $dims; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed integers. ?> loading="lazy" decoding="async" />
				<?php endforeach; ?>
				<?php foreach ( $items as $item ) : ?>
					<img class="ap-solution-trust__dup<?php echo $item['cover'] ? ' ap-solution-trust__cover' : ''; ?>" src="<?php echo esc_url( $item['image'] ); ?>" alt="" aria-hidden="true"<?php echo $dims; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed integers. ?> loading="lazy" decoding="async" />
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
