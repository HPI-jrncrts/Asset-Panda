<?php
/**
 * login-splash — the panel the product's login page frames.
 *
 * login.assetpanda.com (and stage / prelive / admin) draw www's
 * /login-splash-N pages in an iframe beside the sign-in form. The frame is
 * 100vh + 125px tall and pulled 125px up, so the top 125px never shows; the
 * page is built for that: 92px of white, then a 100vh panel whose first item
 * is a tall spacer. This is the live theme's .login-splash sheet ported
 * (header and footer hidden, main padded 92px, first section 100vh, the
 * Bootstrap container steps 576 / 768 / 992 / 1340 and the centred
 * col-md-8 / col-md-6 column), so the content sits where it sits on live at
 * every frame size.
 *
 * Items are a flat list because the three live designs order the same few
 * pieces differently (header art, heading, copy, button, logo, CTA image).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( is_array( $item ) && isset( $item['type'] ) && is_string( $item['type'] ) ) {
		$items[] = $item;
	}
}
if ( empty( $items ) ) {
	return;
}

$bg       = trim( (string) $props['background'] );
$bg_w     = max( 0, (int) $props['background_width'] );
$bg_h     = max( 0, (int) $props['background_height'] );
$bg_color = preg_match( '/^#[0-9a-f]{3,8}$/i', trim( (string) $props['background_color'] ) ) ? trim( (string) $props['background_color'] ) : '#2a3147';
$column   = '6' === $props['column'] ? '6' : '8';

$dims = static function ( $item ) {
	$out = '';
	$w   = isset( $item['width'] ) ? (int) $item['width'] : 0;
	$h   = isset( $item['height'] ) ? (int) $item['height'] : 0;
	if ( $w > 0 && $h > 0 ) {
		$out = ' width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '"';
	}
	return $out;
};
?>
<div class="ap-login-splash">
	<section class="ap-login-splash__panel" style="<?php echo esc_attr( 'background-color:' . $bg_color ); ?>">
		<?php if ( '' !== $bg ) : ?>
			<div class="ap-login-splash__bg" aria-hidden="true">
				<img src="<?php echo esc_url( $bg ); ?>" alt=""<?php echo $bg_w > 0 && $bg_h > 0 ? ' width="' . esc_attr( (string) $bg_w ) . '" height="' . esc_attr( (string) $bg_h ) . '"' : ''; ?> decoding="async" fetchpriority="high" />
			</div>
		<?php endif; ?>
		<div class="ap-login-splash__container">
			<div class="ap-login-splash__col ap-login-splash__col--<?php echo esc_attr( $column ); ?>">
				<?php
				foreach ( $items as $item ) :
					switch ( $item['type'] ) {
						case 'space':
							$size = isset( $item['size'] ) ? max( 0, min( 400, (int) $item['size'] ) ) : 0;
							?>
							<div class="ap-login-splash__space" style="<?php echo esc_attr( 'height:' . $size . 'px' ); ?>"></div>
							<?php
							break;

						case 'image':
							$src = trim( (string) ( $item['image'] ?? '' ) );
							if ( '' === $src ) {
								break;
							}
							?>
							<div class="ap-login-splash__art<?php echo ! empty( $item['wide'] ) ? ' ap-login-splash__art--wide' : ''; ?>">
								<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( (string) ( $item['alt'] ?? '' ) ); ?>"<?php echo $dims( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> decoding="async" />
							</div>
							<?php
							break;

						case 'heading':
							$text = trim( (string) ( $item['text'] ?? '' ) );
							if ( '' === $text ) {
								break;
							}
							$tag = ( isset( $item['tag'] ) && 'h1' === $item['tag'] ) ? 'h1' : 'h2';
							?>
							<<?php echo tag_escape( $tag ); ?> class="ap-login-splash__<?php echo esc_attr( $tag ); ?>"><?php echo ap_ui_lines( $text ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo tag_escape( $tag ); ?>>
							<?php
							break;

						case 'text':
							$text = trim( (string) ( $item['text'] ?? '' ) );
							if ( '' === $text ) {
								break;
							}
							?>
							<p class="ap-login-splash__text<?php echo ! empty( $item['bold'] ) ? ' ap-login-splash__text--bold' : ''; ?>"><?php echo ap_ui_lines( $text ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
							<?php
							break;

						case 'button':
							$label = trim( (string) ( $item['label'] ?? '' ) );
							$url   = trim( (string) ( $item['url'] ?? '' ) );
							if ( '' === $label || '' === $url ) {
								break;
							}
							?>
							<div class="ap-login-splash__btn-row">
								<a class="ap-login-splash__btn" href="<?php echo esc_url( $url ); ?>"<?php echo ! empty( $item['new_tab'] ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $label ); ?></a>
							</div>
							<?php
							break;

						case 'cta':
							$url = trim( (string) ( $item['url'] ?? '' ) );
							$src = trim( (string) ( $item['image'] ?? '' ) );
							if ( '' === $url || '' === $src ) {
								break;
							}
							?>
							<span class="ap-login-splash__cta"><a href="<?php echo esc_url( $url ); ?>"><img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( (string) ( $item['alt'] ?? '' ) ); ?>"<?php echo $dims( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> decoding="async" /></a></span>
							<?php
							break;
					}
				endforeach;
				?>
			</div>
		</div>
	</section>
</div>
