<?php
/**
 * demo-footer — compact footer of the Get a Demo pages (live #ap-demo-footer).
 *
 * Renders where the block sits; behaviour.js then moves it into the global
 * footer's .ap-final-footer-inner (keyed off data-ap-demo-footer), as live
 * does, so it sits on that footer's decorative background.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_list = static function ( $items, $key ) {
	return array_values(
		array_filter(
			is_array( $items ) ? $items : array(),
			static function ( $item ) use ( $key ) {
				return is_array( $item ) && '' !== trim( (string) ( $item[ $key ] ?? '' ) );
			}
		)
	);
};

$ap_px = static function ( $value ) {
	return is_numeric( $value ) && (float) $value > 0 ? (int) round( (float) $value ) : 0;
};

$ap_apps    = $ap_list( $props['apps'], 'image' );
$ap_links   = $ap_list( $props['links'], 'url' );
$ap_social  = $ap_list( $props['social'], 'url' );
$ap_address = trim( (string) $props['address'] );
$ap_copy    = trim( (string) $props['copyright'] );
$ap_pin     = ap_ui_asset_url( 'demo-footer', 'icon-map-pin.svg' );
?>
<section class="ap-demo-footer ap-d26-sec" aria-label="<?php echo esc_attr( $props['logo_alt'] ); ?>" data-ap-demo-footer>
	<div class="ap-d26-foot">
		<div class="ap-d26-foot-left">
			<a class="ap-d26-foot-logo" href="<?php echo esc_url( $props['logo_url'] ); ?>" aria-label="<?php echo esc_attr( trim( (string) $props['logo_alt'] ) . ' home' ); ?>"><img src="<?php echo esc_url( $props['logo'] ); ?>" alt="<?php echo esc_attr( $props['logo_alt'] ); ?>" width="140" height="54" loading="lazy" decoding="async"></a>
			<?php if ( '' !== $ap_copy ) : ?>
				<p class="ap-d26-foot-copy"><?php echo esc_html( $ap_copy ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ap-d26-foot-right">
			<?php if ( $ap_apps ) : ?>
				<div class="ap-d26-foot-apps">
					<?php foreach ( $ap_apps as $ap_app ) : ?>
						<?php
						$ap_w = $ap_px( $ap_app['w'] ?? null );
						$ap_h = $ap_px( $ap_app['h'] ?? null );
						?>
						<a href="<?php echo esc_url( $ap_app['url'] ?? '' ); ?>" aria-label="<?php echo esc_attr( $ap_app['alt'] ?? '' ); ?>"><img src="<?php echo esc_url( $ap_app['image'] ); ?>" alt="<?php echo esc_attr( $ap_app['alt'] ?? '' ); ?>"<?php echo $ap_w ? ' width="' . $ap_w . '"' : ''; ?><?php echo $ap_h ? ' height="' . $ap_h . '"' : ''; ?> loading="lazy" decoding="async"></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="ap-d26-foot-legal">
				<?php if ( '' !== $ap_address ) : ?>
					<span class="ap-d26-foot-addr"><img src="<?php echo esc_url( $ap_pin ); ?>" alt=""><?php echo esc_html( $ap_address ); ?></span>
				<?php endif; ?>
				<?php foreach ( $ap_links as $ap_link ) : ?>
					<a href="<?php echo esc_url( $ap_link['url'] ); ?>"><?php echo esc_html( $ap_link['label'] ?? '' ); ?></a>
				<?php endforeach; ?>
				<?php if ( $ap_social ) : ?>
					<span class="ap-d26-foot-social">
						<?php foreach ( $ap_social as $ap_item ) : ?>
							<a href="<?php echo esc_url( $ap_item['url'] ); ?>" aria-label="<?php echo esc_attr( $ap_item['label'] ?? '' ); ?>"><img src="<?php echo esc_url( $ap_item['image'] ?? '' ); ?>" alt=""></a>
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
