<?php
/**
 * integration-more — "More integrations" band on the integration detail pages.
 *
 * Figma: Integrations Detail Page Template > Problem Section (7318:171385):
 * a section head (eyebrow, heading, lede) with an outline "View All
 * Integrations" button on its right, over a row of four cards. Each card pairs
 * the partner's logo with the Asset Panda PRO panda ("+" between them) above
 * the integration's name, and links to that integration's page.
 *
 * Cards use the stretched-link pattern: the name is the link (its ::after
 * covers the card), so each card is one tab stop announced by the integration's
 * name, and the logos stay decorative. Two blurred colour orbs (Figma's own
 * artwork, assets/) sit behind the cards as section pseudo-elements, so the
 * site's scroll reveal (motion.js) finds the head and the cards as the
 * section's content and staggers the cards in.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$name = trim( (string) ( $item['name'] ?? '' ) );
	if ( '' === $name ) {
		continue;
	}
	$items[] = array(
		'name' => $name,
		'url'  => trim( (string) ( $item['url'] ?? '' ) ),
		'icon' => trim( (string) ( $item['icon'] ?? '' ) ),
	);
	if ( 4 === count( $items ) ) {
		break;
	}
}

if ( empty( $items ) ) {
	return;
}

$eyebrow   = trim( (string) $props['eyebrow'] );
$title     = trim( (string) $props['title'] );
$lede      = trim( (string) $props['lede'] );
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$brand     = trim( (string) $props['brand_logo'] );
if ( '' === $brand ) {
	$brand = ap_ui_asset_url( 'integration-more', 'panda-pro.svg' );
}
$plus = ap_ui_asset_url( 'integration-more', 'plus.svg' );
?>
<section class="ap-integration-more">
	<div class="ap-integration-more__inner">
		<?php if ( '' !== $eyebrow || '' !== $title || '' !== $lede || ( '' !== $cta_label && '' !== $cta_url ) ) : ?>
			<div class="ap-integration-more__head">
				<div class="ap-integration-more__copy">
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="ap-integration-more__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $title ) : ?>
						<h2 class="ap-integration-more__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $lede ) : ?>
						<p class="ap-integration-more__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<a class="ap-integration-more__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<ul class="ap-integration-more__grid ap-integration-more__grid--n<?php echo esc_attr( (string) count( $items ) ); ?>" role="list">
			<?php foreach ( $items as $item ) : ?>
				<li class="ap-integration-more__item<?php echo '' !== $item['url'] ? ' ap-integration-more__item--link' : ''; ?>">
					<span class="ap-integration-more__logos" aria-hidden="true">
						<?php if ( '' !== $item['icon'] ) : ?>
							<img class="ap-integration-more__partner" src="<?php echo esc_url( $item['icon'] ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async" />
							<?php if ( '' !== $plus ) : ?>
								<img class="ap-integration-more__plus" src="<?php echo esc_url( $plus ); ?>" alt="" width="9" height="9" loading="lazy" decoding="async" />
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( '' !== $brand ) : ?>
							<img class="ap-integration-more__brand" src="<?php echo esc_url( $brand ); ?>" alt="" width="61" height="64" loading="lazy" decoding="async" />
						<?php endif; ?>
					</span>
					<h3 class="ap-integration-more__name">
						<?php if ( '' !== $item['url'] ) : ?>
							<a class="ap-integration-more__link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['name'] ); ?>
						<?php endif; ?>
					</h3>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
