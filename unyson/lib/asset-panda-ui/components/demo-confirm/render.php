<?php
/**
 * demo-confirm — booking-confirmed hero (live #ap-ty-hero on /demo-confirmed/).
 *
 * The check badge and the back chevron are the live page's own icons, shipped
 * in assets/.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// esc_url_raw() strips quotes, spaces and backslashes, so the URL cannot leave url("…").
$ap_bg    = trim( (string) $props['background'] );
$ap_style = '' !== $ap_bg ? 'background-image:url("' . esc_url_raw( $ap_bg ) . '")' : '';
$ap_sub   = trim( (string) $props['subtitle'] );
$ap_body  = trim( (string) $props['body'] );
$ap_back  = trim( (string) $props['back_url'] );
$ap_label = trim( (string) $props['back_label'] );
?>
<section class="ap-demo-confirm ap-d26-sec"<?php echo '' !== $ap_style ? ' style="' . esc_attr( $ap_style ) . '"' : ''; ?>>
	<div class="ap-d26-fade" aria-hidden="true"></div>
	<div class="ap-ty-icon"><img src="<?php echo esc_url( ap_ui_asset_url( 'demo-confirm', 'icon-check.svg' ) ); ?>" alt="" width="40" height="40"></div>
	<div class="ap-ty-copy">
		<h1><?php echo esc_html( $props['title'] ); ?></h1>
		<?php if ( '' !== $ap_sub ) : ?>
			<p class="ap-ty-sub"><?php echo esc_html( $ap_sub ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $ap_body ) : ?>
			<p class="ap-ty-body"><?php echo esc_html( $ap_body ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $ap_back && '' !== $ap_label ) : ?>
			<a class="ap-ty-back" href="<?php echo esc_url( $ap_back ); ?>"><img src="<?php echo esc_url( ap_ui_asset_url( 'demo-confirm', 'icon-chevron-left.svg' ) ); ?>" alt="" width="24" height="24"><span><?php echo esc_html( $ap_label ); ?></span></a>
		<?php endif; ?>
	</div>
</section>
