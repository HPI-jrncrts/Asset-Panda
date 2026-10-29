<?php
/**
 * media-band — centred copy and a button over a wide image or a video.
 *
 * Live's form pages close with rounded off-white bands: "Manage your assets
 * your way" (copy, a demo button and the product video reel), "Build with
 * Ursa AI" (copy over a banner) and "Ready to kick off your 7-day free
 * trial?" (heading and button). One component covers all three.
 *
 * The video is a YouTube link around the poster. behaviour.js turns a click
 * into an in-place player (youtube-nocookie, autoplay); without JavaScript the
 * link simply opens the video on YouTube.
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

$tone      = 'white' === $props['tone'] ? 'white' : 'soft';
$layout    = 'split' === $props['layout'] ? 'split' : 'stack';
$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$title_tag = 'h3' === $props['title_tag'] ? 'h3' : 'h2';
$eyebrow   = trim( (string) $props['eyebrow'] );
$subtitle  = trim( (string) $props['subtitle'] );
$body      = trim( (string) $props['body'] );
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$image     = trim( (string) $props['image'] );
$img_w     = max( 0, (int) $props['image_width'] );
$img_h     = max( 0, (int) $props['image_height'] );
$dims      = $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : '';

// Only an 11-character YouTube id survives; anything else drops the video.
$video_id = '';
if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?(?:.*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', (string) $props['video'], $m ) ) {
	$video_id = $m[1];
}
$theater     = '' !== $video_id && 'theater' === $props['video_look'] ? ' ap-media-band--theater' : '';
$video_title = trim( (string) $props['video_title'] );
if ( '' === $video_title ) {
	$video_title = 'Play video';
}
?>
<section class="ap-media-band ap-media-band--<?php echo esc_attr( $tone ); ?> ap-media-band--<?php echo esc_attr( $layout ); ?><?php echo esc_attr( $theater ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-media-band__inner">
		<div class="ap-media-band__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-media-band__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<<?php echo tag_escape( $title_tag ); ?> class="ap-media-band__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; fixed markup added. ?></<?php echo tag_escape( $title_tag ); ?>>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="ap-media-band__sub"><?php echo ap_ui_brandmarks( ap_ui_lines( $subtitle ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; fixed markup added. ?></p>
			<?php endif; ?>
			<?php if ( '' !== $body ) : ?>
				<p class="ap-media-band__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; fixed markup added. ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
				<a class="ap-media-band__btn" href="<?php echo esc_url( $cta_url ); ?>" data-ap-hover="lift"><?php echo esc_html( $cta_label ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $video_id ) : ?>
			<div class="ap-media-band__media ap-media-band__media--video">
				<a class="ap-media-band__play" href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $video_id ); ?>" target="_blank" rel="noopener" data-ap-video="<?php echo esc_attr( $video_id ); ?>" aria-label="<?php echo esc_attr( $video_title ); ?>">
					<?php if ( '' !== $image ) : ?>
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $dims; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?> loading="lazy" decoding="async" />
					<?php endif; ?>
					<span class="ap-media-band__icon" aria-hidden="true"><svg viewBox="0 0 68 48" focusable="false"><path d="M66.5 7.7c-.8-2.9-3-5.2-5.9-6C55.3.2 34 .2 34 .2S12.7.2 7.4 1.7c-2.9.8-5.1 3.1-5.9 6C0 13 0 24 0 24s0 11 1.5 16.3c.8 2.9 3 5.2 5.9 6C12.7 47.8 34 47.8 34 47.8s21.3 0 26.6-1.5c2.9-.8 5.1-3.1 5.9-6C68 35 68 24 68 24s0-11-1.5-16.3z" fill="#e61c55"></path><path d="M45 24 27 14v20" fill="#fff"></path></svg></span>
				</a>
			</div>
		<?php elseif ( '' !== $image ) : ?>
			<div class="ap-media-band__media">
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $dims; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?> loading="lazy" decoding="async" />
			</div>
		<?php endif; ?>
	</div>
</section>
