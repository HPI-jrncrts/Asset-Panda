<?php
/**
 * integration-hero — hero of an integration detail page.
 *
 * Figma: Integrations Detail Page Template > Hero Section (7318:166961). A
 * centred column (eyebrow, a frosted card pairing the partner logo with the
 * Asset Panda PRO mark, the H1, one button) over the 2026 colour wash. The
 * wash is the Figma background container rendered once to a small WebP
 * (assets/hero-bg.webp) instead of ten 600px-blurred SVG orbs and a 4K photo;
 * a white fade at the foot lets it run into the white section below.
 *
 * The logo pair repeats the H1 ("Slack + Asset Panda PRO"), so it is
 * decoration unless the editor gives the partner logo alt text; then both
 * logos carry alt and the pair reads "Slack + Asset Panda PRO".
 *
 * NOTE: first-screen section — no data-ap-reveal (motion.js is a footer
 * script). The copy rises in on load from style.css instead.
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

$eyebrow    = trim( (string) $props['eyebrow'] );
$logo       = trim( (string) $props['logo'] );
$logo_alt   = trim( (string) $props['logo_alt'] );
$brand_logo = trim( (string) $props['brand_logo'] );
$brand_alt  = '' !== $logo_alt ? trim( (string) $props['brand_logo_alt'] ) : '';
$has_cta    = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );

if ( '' === $brand_logo ) {
	$brand_logo = ap_ui_asset_url( 'integration-hero', 'panda-pro.svg' );
}
?>
<section class="ap-integration-hero">
	<div class="ap-integration-hero__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-integration-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>

		<div class="ap-integration-hero__card"<?php echo '' === $logo_alt ? ' aria-hidden="true"' : ''; ?>>
			<?php if ( '' !== $logo ) : ?>
				<img class="ap-integration-hero__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" width="64" height="64" decoding="async" />
				<img class="ap-integration-hero__plus" src="<?php echo esc_url( ap_ui_asset_url( 'integration-hero', 'plus.svg' ) ); ?>" alt="<?php echo '' !== $logo_alt ? '+' : ''; ?>" width="9" height="9" decoding="async" />
			<?php endif; ?>
			<?php if ( '' !== $brand_logo ) : ?>
				<img class="ap-integration-hero__brand" src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_alt ); ?>" width="61" height="64" decoding="async" />
			<?php endif; ?>
		</div>

		<h1 class="ap-integration-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h1>

		<?php if ( $has_cta ) : ?>
			<div class="ap-integration-hero__actions">
				<a class="ap-integration-hero__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>
