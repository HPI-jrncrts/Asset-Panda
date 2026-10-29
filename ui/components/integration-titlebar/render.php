<?php
/**
 * integration-titlebar — the sticky title bar of an integration detail page.
 *
 * Figma 7333:171865: "Appears only when the entire H1 is no longer in the
 * view port." The bar repeats the hero's logos and H1 so readers keep their
 * bearings down a long page. It is a visual echo of the H1, so it is hidden
 * from assistive technology and holds nothing focusable; behaviour.js shows it
 * once the watched heading has scrolled out under the site header. Without
 * JavaScript it never appears.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$logo  = trim( (string) $props['logo'] );
$brand = trim( (string) $props['brand_logo'] );
if ( '' === $brand ) {
	$brand = ap_ui_asset_url( 'integration-titlebar', 'panda-pro.svg' );
}
$plus  = ap_ui_asset_url( 'integration-titlebar', 'plus.svg' );
$watch = trim( (string) $props['watch'] );
?>
<div class="ap-integration-titlebar" data-ap-titlebar data-watch="<?php echo esc_attr( $watch ); ?>" aria-hidden="true">
	<div class="ap-integration-titlebar__in">
		<?php if ( '' !== $logo ) : ?>
			<span class="ap-integration-titlebar__logos">
				<img class="ap-integration-titlebar__logo" src="<?php echo esc_url( $logo ); ?>" alt="" width="33" height="33" loading="lazy" decoding="async" />
				<?php if ( '' !== $plus ) : ?>
					<img class="ap-integration-titlebar__plus" src="<?php echo esc_url( $plus ); ?>" alt="" width="5" height="5" loading="lazy" decoding="async" />
				<?php endif; ?>
				<?php if ( '' !== $brand ) : ?>
					<img class="ap-integration-titlebar__brand" src="<?php echo esc_url( $brand ); ?>" alt="" width="31" height="33" loading="lazy" decoding="async" />
				<?php endif; ?>
			</span>
		<?php endif; ?>
		<span class="ap-integration-titlebar__text">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<span class="ap-integration-titlebar__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></span>
			<?php endif; ?>
			<span class="ap-integration-titlebar__title"><?php echo esc_html( $title ); ?></span>
		</span>
	</div>
</div>
