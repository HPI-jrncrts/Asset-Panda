<?php
/**
 * message-hero — a centred message page (the branded 404).
 *
 * Live's /404-not-found/ shows the question-mark panda, "404", "Oops…Page Not
 * Found!", one line of copy and a Go To Home button. The new site serves a
 * real 404 status with this component in the 404 template, so the code and
 * the heading form a single h1 ("404 Oops…Page Not Found!").
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

$code  = trim( (string) $props['code'] );
$body  = trim( (string) $props['body'] );
$image = trim( (string) $props['image'] );
$img_w = max( 0, (int) $props['image_width'] );
$img_h = max( 0, (int) $props['image_height'] );
$bg    = trim( (string) $props['background'] );

$links = array();
foreach ( is_array( $props['links'] ) ? $props['links'] : array() as $link ) {
	if ( ! is_array( $link ) ) {
		continue;
	}
	$label = trim( (string) ( $link['label'] ?? '' ) );
	$url   = trim( (string) ( $link['url'] ?? '' ) );
	if ( '' === $label || '' === $url ) {
		continue;
	}
	$style   = (string) ( $link['style'] ?? '' );
	$links[] = array(
		'label' => $label,
		'url'   => $url,
		'style' => in_array( $style, array( 'primary', 'secondary', 'text' ), true ) ? $style : 'primary',
	);
}
?>
<section class="ap-message-hero"<?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-message-hero-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
	<div class="ap-message-hero__inner">
		<?php if ( '' !== $image ) : ?>
			<img class="ap-message-hero__art" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> decoding="async" />
		<?php endif; ?>
		<h1 class="ap-message-hero__heading">
			<?php if ( '' !== $code ) : ?>
				<span class="ap-message-hero__code"><?php echo esc_html( $code ); ?></span>
			<?php endif; ?>
			<span class="ap-message-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></span>
		</h1>
		<?php if ( '' !== $body ) : ?>
			<p class="ap-message-hero__body"><?php echo ap_ui_lines( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
		<?php endif; ?>
		<?php if ( $links ) : ?>
			<div class="ap-message-hero__links">
				<?php foreach ( $links as $link ) : ?>
					<a class="ap-message-hero__link ap-message-hero__link--<?php echo esc_attr( $link['style'] ); ?>" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
