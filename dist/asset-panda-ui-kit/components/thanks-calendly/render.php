<?php
/**
 * thanks-calendly — the booking card of the legacy thank-you pages.
 *
 * Live (bs-section--thank-you-calendar) shows, under the "We got you!" copy,
 * one of several hidden blocks: an event card (Asset Panda Pro logo, event
 * name, a clock with "30 min", a description) beside a Calendly inline
 * scheduler (event details hidden in Calendly, so the card supplies them).
 * A page script picks the block from sessionStorage (geoloc,
 * hsFormCurrentSolution). Here every track is rendered hidden with its rule
 * as data attributes; behaviour.js shows the matching one and mounts its
 * scheduler. Without JavaScript the default track's card shows with a link
 * to the scheduler on Calendly.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tracks = array();
foreach ( is_array( $props['tracks'] ) ? $props['tracks'] : array() as $track ) {
	if ( ! is_array( $track ) ) {
		continue;
	}
	$url = trim( (string) ( $track['url'] ?? '' ) );
	if ( ! preg_match( '#^https://calendly\.com/[A-Za-z0-9._~/-]+(\?[A-Za-z0-9._~=&%-]*)?$#', $url ) ) {
		continue;
	}
	$key    = trim( (string) ( $track['when_key'] ?? '' ) );
	$values = array();
	foreach ( is_array( $track['when_values'] ?? null ) ? $track['when_values'] : array() as $value ) {
		if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			$values[] = substr( trim( (string) $value ), 0, 80 );
		}
	}
	$tracks[] = array(
		'url'      => $url,
		'title'    => trim( (string) ( $track['event_title'] ?? '' ) ),
		'duration' => trim( (string) ( $track['duration'] ?? '' ) ),
		'body'     => trim( (string) ( $track['body'] ?? '' ) ),
		'key'      => preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $key ) ? $key : '',
		'values'   => $values,
	);
}
if ( empty( $tracks ) ) {
	return;
}

// The default track: the first without a rule (or the first of all).
$default = 0;
foreach ( $tracks as $i => $track ) {
	if ( '' === $track['key'] ) {
		$default = $i;
		break;
	}
}

$title  = trim( (string) $props['title'] );
$logo   = trim( (string) $props['logo'] );
$logo_w = max( 0, (int) $props['logo_width'] );
$logo_h = max( 0, (int) $props['logo_height'] );
$height = max( 400, min( 1200, (int) $props['height'] ) );
$class  = 'ap-thanks-calendly' . ( $props['hide_international'] ? ' ap-thanks-calendly--intl-hide' : '' );
?>
<section class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( '--ap-calendly-h:' . $height . 'px' ); ?>">
	<div class="ap-thanks-calendly__inner">
		<?php if ( '' !== $title ) : ?>
			<h2 class="ap-thanks-calendly__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>
		<?php endif; ?>
		<?php foreach ( $tracks as $i => $track ) : ?>
			<div class="ap-thanks-calendly__track" data-ap-calendly-track<?php echo $i === $default ? ' data-ap-calendly-default' : ' hidden'; ?><?php echo '' !== $track['key'] ? ' data-when-key="' . esc_attr( $track['key'] ) . '" data-when-values="' . esc_attr( wp_json_encode( $track['values'] ) ) . '"' : ''; ?>>
				<div class="ap-thanks-calendly__event">
					<?php if ( '' !== $logo ) : ?>
						<img class="ap-thanks-calendly__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( (string) $props['logo_alt'] ); ?>"<?php echo $logo_w > 0 && $logo_h > 0 ? ' width="' . esc_attr( (string) $logo_w ) . '" height="' . esc_attr( (string) $logo_h ) . '"' : ''; ?> decoding="async" />
					<?php endif; ?>
					<?php if ( '' !== $track['title'] ) : ?>
						<p class="ap-thanks-calendly__name"><?php echo esc_html( $track['title'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $track['duration'] ) : ?>
						<p class="ap-thanks-calendly__duration"><svg viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path d="M.5 5a4.5 4.5 0 1 0 9 0 4.5 4.5 0 1 0-9 0Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5 3.269V5l1.759 2.052" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path></svg><span><?php echo esc_html( $track['duration'] ); ?></span></p>
					<?php endif; ?>
					<?php if ( '' !== $track['body'] ) : ?>
						<p class="ap-thanks-calendly__body"><?php echo esc_html( $track['body'] ); ?></p>
					<?php endif; ?>
				</div>
				<div class="ap-thanks-calendly__cal" data-ap-form-skin data-ap-calendly="<?php echo esc_url( $track['url'] ); ?>">
					<a class="ap-thanks-calendly__fallback" href="<?php echo esc_url( $track['url'] ); ?>" target="_blank" rel="noopener">Open the scheduler</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
