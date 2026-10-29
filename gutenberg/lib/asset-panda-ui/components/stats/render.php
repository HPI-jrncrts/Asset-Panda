<?php
/**
 * stats — a grid of headline statistics.
 *
 * Mirrors the live homepage stats band (#ap-stats-v3) element for element, so
 * the ported stylesheet applies class for class.
 *
 * Presentation only. Props arrive validated by ap_render_component() against
 * component.json; everything dynamic is escaped at the point of output.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items   = is_array( $props['items'] ) ? $props['items'] : array();
$columns = (int) $props['columns'];
$theme   = $props['theme'];
$reveal  = (bool) $props['reveal'];

// Drop malformed entries rather than rendering an empty cell. The contract
// declares `items` as an array; it cannot describe the shape of each entry, so
// the component is responsible for that check.
$items = array_values(
	array_filter(
		$items,
		static function ( $item ) {
			return is_array( $item )
				&& isset( $item['value'], $item['label'] )
				&& '' !== trim( (string) $item['value'] )
				&& '' !== trim( (string) $item['label'] );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$classes = array(
	'ap-stats',
	'ap-stats--cols-' . $columns,
	'ap-stats--' . $theme,
);
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<div class="ap-stats-inner">
		<div class="ap-stats-grid">
			<?php foreach ( $items as $i => $item ) : ?>
				<div class="ap-stat"<?php if ( $reveal ) : ?> data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % $columns ) + 1 ) ); ?>"<?php endif; ?>>
					<div class="ap-stat-number"><?php echo esc_html( $item['value'] ); ?></div>
					<div class="ap-stat-label"><?php echo esc_html( $item['label'] ); ?></div>
					<?php if ( ! empty( $item['description'] ) ) : ?>
						<div class="ap-stat-desc"><?php echo esc_html( $item['description'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
