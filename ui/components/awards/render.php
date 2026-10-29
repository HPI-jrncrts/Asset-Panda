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

/*
 * Industry hubs: live #ap-gov-awards (layout "hub") and #ap-eduhub-awards
 * (layout "eduhub"), each ported whole with its own class names. Every badge
 * keeps one size at every width (w, and for the hub h and radius), carried as
 * custom properties on the image; numbers only.
 */
if ( 'hub' === $props['layout'] || 'eduhub' === $props['layout'] ) :
	$ap_edu  = 'eduhub' === $props['layout'];
	$ap_size = static function ( $item ) {
		$w   = $item['w'] ?? null;
		$w   = is_array( $w ) ? ( $w['d'] ?? null ) : $w;
		$out = array();
		foreach ( array( '--aw-w' => $w, '--aw-h' => $item['h'] ?? null, '--aw-r' => $item['radius'] ?? null ) as $var => $value ) {
			if ( is_numeric( $value ) && $value >= 0 && $value <= 400 ) {
				$out[] = $var . ':' . round( (float) $value, 2 ) . 'px';
			}
		}
		return $out ? ' style="' . esc_attr( implode( ';', $out ) ) . '"' : '';
	};
	$ap_img  = static function ( $item, $is_copy, $class ) use ( $ap_size ) {
		return '<img' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $is_copy ? '' : $item['alt'] ) . '"' . $ap_size( $item ) . ( $is_copy ? ' aria-hidden="true"' : '' ) . ' loading="lazy" decoding="async" />';
	};
	$ap_link = static function ( $item, $is_copy, $inner, $class = '' ) {
		if ( empty( $item['url'] ) ) {
			return $inner;
		}
		return '<a class="' . esc_attr( trim( 'ap-awards__link ' . $class ) ) . '" href="' . esc_url( $item['url'] ) . '"' . ( $is_copy ? ' tabindex="-1" aria-hidden="true"' : '' ) . '>' . $inner . '</a>';
	};
	?>
<section class="ap-awards ap-awards--layout-<?php echo esc_attr( $props['layout'] ); ?>">
	<?php if ( '' !== $title ) : ?>
		<h2 class="ap-awards__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<?php if ( $ap_edu ) : ?>
	<div class="apw-viewport">
		<div class="apw-track">
			<?php foreach ( array( false, true ) as $is_copy ) : ?>
				<?php foreach ( $items as $item ) : ?>
					<div class="apw-item"<?php echo $is_copy ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_link( $item, $is_copy, $ap_img( $item, $is_copy, '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php else : ?>
	<div class="apg-aw-viewport">
		<div class="apg-aw-track">
			<?php foreach ( array( false, true ) as $is_copy ) : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php echo $ap_link( $item, $is_copy, $ap_img( $item, $is_copy, $is_copy ? 'apg-aw-dup' : '' ), $is_copy ? 'apg-aw-dup' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
</section>
	<?php
	return;
endif;
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
						<?php
						// Live sizes each badge by width, one value per step (desktop, 1279px,
						// 767px, 480px); numbers only.
						$ap_aw = array();
						foreach ( array( 'd', 't', 'm', 's' ) as $step ) {
							$value = is_array( $item['w'] ?? null ) ? ( $item['w'][ $step ] ?? null ) : null;
							if ( is_numeric( $value ) && $value > 0 && $value <= 400 ) {
								$ap_aw[] = '--aw-' . $step . ':' . round( (float) $value, 2 ) . 'px';
							}
						}
						?>
						<li class="ap-awards__item<?php echo $is_copy ? ' ap-awards__item--dup' : ''; ?>"<?php echo $ap_aw ? ' style="' . esc_attr( implode( ';', $ap_aw ) ) . '"' : ''; ?>
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
