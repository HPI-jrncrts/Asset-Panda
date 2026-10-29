<?php
/**
 * demo-stats — stats band under the split booking hero (live #ap-demo-stats).
 *
 * Each value renders twice: the visible copy behaviour.js counts up from 0,
 * and a hidden ghost of the final value that holds the width while it counts,
 * so nothing moves. Without JS the final values simply show.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $item ) {
			return is_array( $item ) && '' !== trim( (string) ( $item['value'] ?? '' ) );
		}
	)
);

if ( empty( $ap_items ) ) {
	return;
}
?>
<section class="ap-demo-stats ap-d26-sec" aria-label="<?php echo esc_attr( $props['label'] ); ?>">
	<ul class="ap-d26-v1-stats" data-ap-stats>
		<?php foreach ( $ap_items as $ap_item ) : ?>
			<li><span class="ap-d26-stat-num"><span class="ap-d26-stat-val" data-ap-count><?php echo esc_html( $ap_item['value'] ); ?></span><span class="ap-d26-stat-ghost" aria-hidden="true"><?php echo esc_html( $ap_item['value'] ); ?></span></span><span class="ap-d26-stat-label"><?php echo esc_html( $ap_item['label'] ?? '' ); ?></span></li>
		<?php endforeach; ?>
	</ul>
</section>
