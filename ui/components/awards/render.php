<?php
/**
 * awards — trust/badge strip.
 *
 * Matches the live homepage (dev page 7, #ap-badges-row-v3): one centred row
 * of badges on desktop; on phones a continuous marquee. The list renders twice
 * for the loop — the second copy is aria-hidden, its links are out of the tab
 * order, and it is only shown on phones.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && ! empty( $i['image'] ) && isset( $i['alt'] );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$title = trim( (string) $props['title'] );
?>
<section class="ap-awards ap-awards--<?php echo esc_attr( $props['theme'] ); ?> ap-awards--layout-<?php echo esc_attr( $props['layout'] ); ?>">
	<div class="ap-awards__inner">
		<?php if ( '' !== $title ) : ?>
			<h2 class="ap-awards__title"><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<div class="ap-awards__viewport">
			<ul class="ap-awards__grid">
				<?php foreach ( array( false, true ) as $is_copy ) : ?>
					<?php foreach ( $items as $i => $item ) : ?>
						<li class="ap-awards__item<?php echo $is_copy ? ' ap-awards__item--dup' : ''; ?>"
							<?php if ( $is_copy ) : ?>
								aria-hidden="true"
							<?php elseif ( $props['reveal'] ) : ?>
								data-ap-reveal="fade" data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 6 ) + 1 ) ); ?>"
							<?php endif; ?>
						>
							<?php if ( ! empty( $item['url'] ) ) : ?>
								<a class="ap-awards__link" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $is_copy ? ' tabindex="-1"' : ''; ?>>
							<?php endif; ?>
							<img
								class="ap-awards__img"
								src="<?php echo esc_url( $item['image'] ); ?>"
								alt="<?php echo esc_attr( $is_copy ? '' : $item['alt'] ); ?>"
								loading="lazy"
								decoding="async"
							/>
							<?php if ( ! empty( $item['url'] ) ) : ?>
								</a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
