<?php
/**
 * compare-hero — hero of the Compare hub (/compare/).
 *
 * Figma: Desktop - Compare > Hero Section (7333:173311), 1440 x 797. A centred
 * 928px column (eyebrow, H1, lede, one button) over the 2026 colour wash, then
 * a wide illustration — the panda arm-wrestling a shadowy rival — whose top
 * tucks 36px under the button row and whose foot fades into white, so the hero
 * runs straight into the white directory section below.
 *
 * The wash is the Figma "Decorative / Background Container" rendered once to a
 * small WebP (assets/hero-bg.webp) instead of ten 600px-blurred SVG orbs. The
 * white fade is Figma's "Decorative / Gradient Overlay" (289px, clear to white
 * at 70%), drawn over the illustration and under the copy.
 *
 * The illustration arrives already cropped to the slice Figma shows (the frame
 * clips the 1090 x 727 artwork to 1090 x 389). It is the hero's main image, so
 * it loads eagerly with fetchpriority="high"; its alt is empty unless the
 * editor gives one — the copy carries the message.
 *
 * NOTE: first-screen section — no data-ap-reveal (motion.js is a footer
 * script). The copy and the art rise in on load from style.css instead.
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

$eyebrow  = trim( (string) $props['eyebrow'] );
$lede     = trim( (string) $props['lede'] );
$cta_href = esc_url( trim( (string) $props['cta_url'] ) );
$has_cta  = '' !== trim( (string) $props['cta_label'] ) && '' !== $cta_href;
$image    = esc_url( trim( (string) $props['image'] ) );
$img_w    = max( 1, (int) $props['image_w'] );
$img_h    = max( 1, (int) $props['image_h'] );
// Opt-in 2026-10-29 layout: compact geometry and a glow behind the art (absent = the wide hero, unchanged).
$compact  = 'compact' === $props['layout'];
$glow     = $compact ? esc_url( trim( (string) $props['glow'] ) ) : '';
?>
<section class="ap-compare-hero<?php echo '' === $image ? ' ap-compare-hero--no-art' : ''; ?><?php echo $compact ? ' ap-compare-hero--compact' : ''; ?>">
	<div class="ap-compare-hero__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-compare-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>

		<h1 class="ap-compare-hero__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(); brandmarks adds a fixed span. ?></h1>

		<?php if ( '' !== $lede ) : ?>
			<p class="ap-compare-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(); brandmarks adds a fixed span. ?></p>
		<?php endif; ?>

		<?php if ( $has_cta ) : ?>
			<div class="ap-compare-hero__actions">
				<a class="ap-compare-hero__btn" href="<?php echo $cta_href; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() above. ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $image ) : ?>
		<div class="ap-compare-hero__art">
			<?php echo '' !== $glow ? '<img class="ap-compare-hero__glow" src="' . $glow . '" alt="" width="1443" height="563" loading="eager" decoding="async" aria-hidden="true" />' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- $glow is esc_url()'d above. Same line as the art so the wide layout's bytes stay as they were. ?><img class="ap-compare-hero__img" src="<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() above. ?>" alt="<?php echo esc_attr( trim( (string) $props['image_alt'] ) ); ?>" width="<?php echo esc_attr( (string) $img_w ); ?>" height="<?php echo esc_attr( (string) $img_h ); ?>" fetchpriority="high" decoding="async" />
		</div>
	<?php endif; ?>
</section>
