<?php
/**
 * ursa-band — the Ursa AI data-control band.
 *
 * Ursa AI frame 7261:114286: a 1120px dark indigo card (indigo 600 to indigo 950,
 * soft indigo glow) holding the Ursa orb, a heading with a paragraph, and the demo
 * button bottom-right. The orb and the teal glow are the kit's (assets/); every
 * string comes from props.
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
$body      = trim( (string) $props['body'] );
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
?>
<section class="ap-ursa-band<?php echo $props['joined'] ? ' ap-ursa-band--joined' : ''; ?>">
	<div class="ap-ursa-band__wrap">
		<div class="ap-ursa-band__card">
			<img class="ap-ursa-band__glow" src="<?php echo esc_url( ap_ui_asset_url( 'ursa-band', 'glow-green.svg' ) ); ?>" alt="" width="729" height="639" loading="lazy" decoding="async" />
			<span class="ap-ursa-band__orb" aria-hidden="true"><img src="<?php echo esc_url( ap_ui_asset_url( 'ursa-band', 'orb.svg' ) ); ?>" alt="" width="191" height="199" loading="lazy" decoding="async" /></span>
			<div class="ap-ursa-band__content">
				<div class="ap-ursa-band__copy">
					<h2 class="ap-ursa-band__title"><?php echo ap_ui_brandmarks( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h2>
					<?php if ( '' !== $body ) : ?>
						<p class="ap-ursa-band__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<a class="ap-ursa-band__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
