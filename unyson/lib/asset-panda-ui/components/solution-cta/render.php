<?php
/**
 * solution-cta — the solution pages' closing call to action.
 *
 * After the live .epx-fcta section: copy on the left, the product image pinned
 * to the right from 1001px and set under the copy below it. Since QA
 * 2026-10-29 the colour is a soft splash on the section itself; __card is a
 * layout box only (no fill). It reveals through a CSS scroll timeline, as on
 * live.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
$eyebrow = trim( (string) $props['eyebrow'] );
$body    = trim( (string) $props['body'] );
$image   = trim( (string) $props['image'] );
$has_cta = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
?>
<section class="ap-solution-cta ap-solution-cta--<?php echo esc_attr( $variant ); ?><?php echo 'splash' === ( $props['look'] ?? 'card' ) ? ' ap-solution-cta--splash' : ''; ?>">
	<div class="ap-solution-cta__wrap">
		<div class="ap-solution-cta__card ap-solution-cta__rev">
			<div class="ap-solution-cta__content">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-solution-cta__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-solution-cta__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php if ( '' !== $body ) : ?>
					<p class="ap-solution-cta__lede"><?php echo ap_ui_brandmarks( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
				<?php if ( $has_cta ) : ?>
					<a class="ap-solution-cta__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $image ) : ?>
				<img class="ap-solution-cta__media" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="488" height="452" loading="lazy" decoding="async" />
			<?php endif; ?>
		</div>
	</div>
</section>
