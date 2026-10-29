<?php
/**
 * article-cta — in-article call-to-action box.
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

$body    = trim( (string) $props['body'] );
$label   = trim( (string) $props['cta_label'] );
$url     = trim( (string) $props['cta_url'] );
$icon    = trim( (string) $props['icon'] );
$has_cta = '' !== $label && '' !== $url;
if ( '' === $icon ) {
	$icon = ap_ui_asset_url( 'article-cta', 'panda.svg' );
}
?>
<aside class="ap-article-cta">
	<img class="ap-article-cta__icon" src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $props['icon_alt'] ); ?>" width="40" height="44" loading="lazy" decoding="async" />
	<div class="ap-article-cta__copy">
		<p class="ap-article-cta__title"><?php echo ap_ui_brandmarks( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
		<?php if ( '' !== $body ) : ?>
			<p class="ap-article-cta__text"><?php echo ap_ui_brandmarks( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $has_cta ) : ?>
		<a class="ap-article-cta__btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endif; ?>
</aside>
