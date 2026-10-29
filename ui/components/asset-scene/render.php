<?php
/**
 * asset-scene — use-case cards around the assistant artwork.
 *
 * Matches the live .apx-bft on /asset-intelligence-platform/. Each card sits in
 * a named slot that the ported stylesheet places, so a page picks the slot
 * rather than supplying coordinates. The inner class names are the live ones.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="apx-g">' . $escaped . '</span>', $html );
	}
	return $html;
};

$slots = array( 'it', 'field', 'safety' );
$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && '' !== trim( (string) ( $i['title'] ?? '' ) );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$image  = trim( (string) $props['image'] );
// The link arrow, drawn inline in live's teal (fixed, decorative).
$arrow  = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.75 16L21.5 12.25L17.75 8.5M21.5 12.25H3.5" stroke="#00A991" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-asset-scene" aria-label="<?php echo esc_attr( wp_strip_all_tags( '' !== trim( (string) $props['eyebrow'] ) ? $props['eyebrow'] : $props['title'] ) ); ?>">
	<div class="ap-asset-scene__wrap">
		<div class="ap-asset-scene__head">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-asset-scene__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-asset-scene__title"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-asset-scene__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-asset-scene__stage">
			<?php if ( '' !== $image ) : ?>
				<?php
				/*
				 * One artwork at every width (QA 2026-10-29): narrow screens show the same file cropped to the orb,
				 * its connector lines and icon discs, and the panda, so the lines stay on tablet and phone. That is
				 * why mobile_image (live's line-less narrow artwork) is no longer drawn. `sizes` states the width the
				 * file is really drawn at (the crop is ~2.67× the box, ≤480px wide), so the media localizer's phone
				 * candidate (828px) is not picked for a crop it would blur.
				 */
				$ap_sizes = '(max-width: 1000px) min(267.5vw, 1284px), 1471px';
				?>
				<picture class="ap-asset-scene__art">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="1471" height="1118" sizes="<?php echo esc_attr( $ap_sizes ); ?>" loading="lazy" decoding="async" />
				</picture>
				<?php // A second copy, masked to the three icon discs, sits above the cards on wide screens (as live). ?>
				<img class="ap-asset-scene__icons" src="<?php echo esc_url( $image ); ?>" alt="" aria-hidden="true" width="1471" height="1118" sizes="<?php echo esc_attr( $ap_sizes ); ?>" loading="lazy" decoding="async" />
			<?php endif; ?>

			<?php foreach ( array_slice( $items, 0, 3 ) as $i => $item ) : ?>
				<?php $slot = in_array( $item['slot'] ?? '', $slots, true ) ? $item['slot'] : $slots[ $i ]; ?>
				<div class="ap-asset-scene__card ap-asset-scene__card--<?php echo esc_attr( $slot ); ?>">
					<div class="ap-asset-scene__ctext">
						<?php if ( ! empty( $item['kicker'] ) ) : ?>
							<p class="ap-asset-scene__k"><?php echo esc_html( $item['kicker'] ); ?></p>
						<?php endif; ?>
						<h3 class="ap-asset-scene__ct"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						<?php if ( ! empty( $item['body'] ) ) : ?>
							<p class="ap-asset-scene__cb"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $item['link_label'] ) && ! empty( $item['url'] ) ) : ?>
						<a class="ap-asset-scene__cta" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( '' !== trim( (string) $props['foot_title'] ) || '' !== trim( (string) $props['foot_body'] ) ) : ?>
			<div class="ap-asset-scene__foot">
				<?php if ( '' !== trim( (string) $props['foot_title'] ) ) : ?>
					<p class="ap-asset-scene__ft"><?php echo ap_ui_brandmarks( esc_html( $props['foot_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $props['foot_body'] ) ) : ?>
					<p class="ap-asset-scene__fb"><?php echo ap_ui_brandmarks( esc_html( $props['foot_body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
				<div class="ap-asset-scene__btns">
					<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
						<a class="apx-btn apx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] ) ) : ?>
						<a class="apx-btn apx-btn--ghost" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
