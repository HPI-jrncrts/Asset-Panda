<?php
/**
 * thanks-hero — demo thank-you hero with the inline Cal.com booking calendar.
 *
 * Matches live #ap-demo-thank-you, the first section of the seven 2026 demo
 * thank-you pages, and (variant "trial") the restyled hero of
 * /thanks-guided-trial-pro/: a success tick, heading, gradient subheading and
 * paragraph over a soft colour wash, then the booking calendar. behaviour.js
 * mounts the calendar from the data-ap-cal attributes; without JavaScript the
 * section still reads as a complete thank-you message.
 *
 * Live's 100vw full-bleed wrapper is left out — the block is already full
 * width.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant   = in_array( $props['variant'], array( 'demo', 'trial' ), true ) ? $props['variant'] : 'demo';
$title_tag = 'h2' === $props['title_tag'] ? 'h2' : 'h1';
$subtitle  = trim( (string) $props['subtitle'] );
$body      = trim( (string) $props['body'] );
$bg        = trim( (string) $props['background'] );

/*
 * The booking link and namespace reach the browser as data attributes, so
 * only path-safe characters pass. A bad link drops the calendar; a bad
 * namespace falls back to the site's demo namespace (the GTM booking listener
 * reads it from Cal's messages).
 */
$cal_link = trim( (string) $props['cal_link'], " \t\n\r\0\x0B/" );
if ( ! preg_match( '#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $cal_link ) || false !== strpos( $cal_link, '..' ) ) {
	$cal_link = '';
}
$cal_ns = trim( (string) $props['cal_namespace'] );
if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $cal_ns ) ) {
	$cal_ns = 'asset-panda-demo';
}

// **bold** runs become <strong>. The text is escaped first, so the fixed tag
// is the only markup that can appear.
$body_html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html( $body ) );
if ( ! is_string( $body_html ) ) {
	$body_html = esc_html( $body );
}
?>
<section class="ap-thanks-hero ap-thanks-hero--<?php echo esc_attr( $variant ); ?>"<?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-thanks-hero-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
	<div class="ap-thanks-hero__inner">
		<div class="ap-thanks-hero__icon" aria-hidden="true">
			<svg viewBox="0 0 40 40" fill="none" focusable="false"><path d="M10 20.5L16.5 27L30.5 13" stroke="currentColor" stroke-width="3.25" stroke-linecap="round" stroke-linejoin="round"></path></svg>
		</div>
		<div class="ap-thanks-hero__copy">
			<<?php echo tag_escape( $title_tag ); ?> class="ap-thanks-hero__title"><?php echo ap_ui_lines( $props['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo tag_escape( $title_tag ); ?>>
			<?php if ( '' !== $subtitle ) : ?>
				<h2 class="ap-thanks-hero__sub"><?php echo ap_ui_lines( $subtitle ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>
			<?php endif; ?>
			<?php if ( '' !== $body ) : ?>
				<p class="ap-thanks-hero__body"><?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed <strong> is added. ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $cal_link ) : ?>
			<div class="ap-thanks-hero__cal-wrap">
				<div class="ap-thanks-hero__cal" data-ap-cal="<?php echo esc_attr( $cal_link ); ?>" data-ap-cal-ns="<?php echo esc_attr( $cal_ns ); ?>"></div>
			</div>
		<?php endif; ?>
	</div>
</section>
