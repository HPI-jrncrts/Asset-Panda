<?php
/**
 * stats — a grid of headline statistics.
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
	<div class="ap-stats__inner">
		<ul class="ap-stats__grid">
			<?php foreach ( $items as $i => $item ) : ?>
				<li
					class="ap-stats__item"
					<?php if ( $reveal ) : ?>
						data-ap-reveal
						data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % $columns ) + 1 ) ); ?>"
					<?php endif; ?>
				>
					<p class="ap-stats__value"><?php echo esc_html( $item['value'] ); ?></p>
					<p class="ap-stats__label"><?php echo esc_html( $item['label'] ); ?></p>
					<?php if ( ! empty( $item['description'] ) ) : ?>
						<p class="ap-stats__desc"><?php echo esc_html( $item['description'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
