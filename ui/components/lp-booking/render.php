<?php
/**
 * lp-booking — a booking page: heading, then the event card beside a scheduler.
 *
 * The live calendly-booking-page-… pages hold one grey panel with the Asset
 * Panda Pro logo, the event name, a clock with "30 min" and a description in
 * a narrow column beside an inline scheduler: Calendly on some pages (event
 * details hidden in Calendly, so the card supplies them), Cal.com's inline
 * embed on others (month view in the site palette). Here the same card sits
 * on the soft colour wash under the page heading. behaviour.js mounts the
 * scheduler when the card nears the viewport; without JavaScript the card
 * links to the scheduler on Calendly or Cal.com.
 *
 * @var array $props Validated props.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$event = trim( (string) $props['event_title'] );
if ( '' === $event ) {
	return;
}

$provider = 'cal' === $props['provider'] ? 'cal' : 'calendly';
$sched    = '';
$open_url = '';
$cal_ns   = '';
if ( 'calendly' === $provider ) {
	$url = trim( (string) $props['calendly_url'] );
	if ( preg_match( '#^https://calendly\.com/[A-Za-z0-9._~/-]+(\?[A-Za-z0-9._~=&%-]*)?$#', $url ) ) {
		$sched    = $url;
		$open_url = $url;
	}
} else {
	$link = trim( (string) $props['cal_link'], " \t\n\r\0\x0B/" );
	if ( preg_match( '#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $link ) && false === strpos( $link, '..' ) ) {
		$sched    = $link;
		$open_url = 'https://cal.com/' . $link;
		$cal_ns   = trim( (string) $props['cal_namespace'] );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,63}$/', $cal_ns ) ) {
			$parts  = explode( '/', strtolower( $link ) );
			$cal_ns = preg_replace( '/[^a-z0-9-]/', '-', (string) end( $parts ) );
		}
	}
}

$eyebrow  = trim( (string) $props['eyebrow'] );
$title    = trim( (string) $props['title'] );
$lede     = trim( (string) $props['lede'] );
$bg       = trim( (string) $props['background'] );
$logo     = trim( (string) $props['logo'] );
$logo_w   = max( 0, (int) $props['logo_width'] );
$logo_h   = max( 0, (int) $props['logo_height'] );
$duration = trim( (string) $props['duration'] );
$body     = trim( (string) $props['body'] );
$height   = max( 480, min( 1200, (int) $props['height'] ) );
$email    = sanitize_email( trim( (string) $props['contact_email'] ) );
$phone    = trim( (string) $props['contact_phone'] );
$tel      = preg_replace( '/[^0-9+]/', '', $phone );
$name_tag = '' === $title ? 'h1' : 'h2';

$classes = 'ap-lp-booking' . ( $props['hide_international'] ? ' ap-lp-booking--intl-hide' : '' );
$style   = '--ap-lp-booking-h:' . $height . 'px' . ( '' !== $bg ? ';--ap-lp-booking-bg:url("' . esc_url_raw( $bg ) . '")' : '' );
?>
<section class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $style ); ?>">
	<div class="ap-lp-booking__inner">
		<?php if ( '' !== $title || '' !== $eyebrow || '' !== $lede ) : ?>
			<div class="ap-lp-booking__head">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-lp-booking__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h1 class="ap-lp-booking__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h1>
				<?php endif; ?>
				<?php if ( '' !== $lede ) : ?>
					<p class="ap-lp-booking__lede"><?php echo ap_ui_lines( $lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="ap-lp-booking__card<?php echo '' === $sched ? ' ap-lp-booking__card--solo' : ''; ?>">
			<div class="ap-lp-booking__event">
				<?php if ( '' !== $logo ) : ?>
					<img class="ap-lp-booking__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( (string) $props['logo_alt'] ); ?>"<?php echo $logo_w > 0 && $logo_h > 0 ? ' width="' . esc_attr( (string) $logo_w ) . '" height="' . esc_attr( (string) $logo_h ) . '"' : ''; ?> decoding="async" />
				<?php endif; ?>
				<<?php echo tag_escape( $name_tag ); ?> class="ap-lp-booking__name"><?php echo esc_html( $event ); ?></<?php echo tag_escape( $name_tag ); ?>>
				<?php if ( '' !== $duration ) : ?>
					<p class="ap-lp-booking__duration"><svg viewBox="0 0 10 10" width="20" height="20" aria-hidden="true" focusable="false"><path d="M.5 5a4.5 4.5 0 1 0 9 0 4.5 4.5 0 1 0-9 0Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5 3.269V5l1.759 2.052" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path></svg><span><?php echo esc_html( $duration ); ?></span></p>
				<?php endif; ?>
				<?php if ( '' !== $body ) : ?>
					<p class="ap-lp-booking__body"><?php echo esc_html( $body ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $sched ) : ?>
				<div class="ap-lp-booking__sched ap-lp-booking__sched--<?php echo esc_attr( $provider ); ?>"
					data-ap-form-skin
					data-ap-lp-booking="<?php echo esc_attr( $provider ); ?>"
					data-ap-lp-booking-src="<?php echo 'calendly' === $provider ? esc_url( $sched ) : esc_attr( $sched ); ?>"
					<?php if ( '' !== $cal_ns ) : ?>data-ap-lp-booking-ns="<?php echo esc_attr( $cal_ns ); ?>"<?php endif; ?>
				>
					<a class="ap-lp-booking__fallback" href="<?php echo esc_url( $open_url ); ?>" target="_blank" rel="noopener">Open the scheduler</a>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $email || ( '' !== $phone && '' !== $tel ) ) : ?>
			<p class="ap-lp-booking__contact">
				Can&rsquo;t find a time that works?
				<?php if ( '' !== $email ) : ?>
					Email <a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a><?php endif; ?><?php if ( '' !== $phone && '' !== $tel ) : ?><?php echo '' !== $email ? ' or call ' : ' Call '; ?><a href="<?php echo esc_url( 'tel:' . $tel ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>.
			</p>
		<?php endif; ?>
	</div>
</section>
