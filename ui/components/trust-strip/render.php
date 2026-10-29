<?php
/**
 * trust-strip — label plus a row of trust badges.
 *
 * Matches the live `.ap-trust` strip (every vertical, hub and solution page).
 * On phones the row becomes a marquee: the list renders twice, the second copy
 * aria-hidden and out of the tab order, and the track slides half its width so
 * the loop is seamless.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $item ) {
			return is_array( $item ) && ! empty( $item['image'] );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$label   = trim( (string) $props['label'] );
$accent  = trim( (string) $props['accent'] );
$marquee = (bool) $props['marquee'];

// The accent is a slice of the label, so it can only ever be the author's own
// words: escape first, then wrap the matching slice.
$label_html = ap_ui_brandmarks( esc_html( $label ) );
if ( '' !== $accent && '' !== $label && false !== strpos( $label, $accent ) ) {
	$escaped_accent = ap_ui_brandmarks( esc_html( $accent ) );
	$label_html     = str_replace(
		$escaped_accent,
		'<span class="ap-trust-strip__accent">' . $escaped_accent . '</span>',
		$label_html
	);
}

$copies = $marquee ? array( false, true ) : array( false );

/*
 * Industry hubs: live #ap-gov-trust (layout "hub") and #ap-eduhub-trust (layout
 * "eduhub"), each ported whole with its own class names so the sheets stay
 * diffable against live. The Education label keeps the author's line break.
 */
if ( 'hub' === $props['layout'] || 'eduhub' === $props['layout'] ) :
	$ap_edu   = 'eduhub' === $props['layout'];
	$ap_label = $ap_edu ? ap_ui_brandmarks( ap_ui_lines( $label ) ) : ap_ui_brandmarks( esc_html( $label ) );
	if ( '' !== $accent && false !== strpos( $label, $accent ) ) {
		$ap_slice = ap_ui_brandmarks( esc_html( $accent ) );
		$ap_label = str_replace( $ap_slice, '<span class="' . ( $ap_edu ? 'apt-label-grad' : 'apg-trust-grad' ) . '">' . $ap_slice . '</span>', $ap_label );
	}
	$ap_img = static function ( $item, $is_copy, $class = '' ) {
		return '<img' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $is_copy ? '' : ( $item['alt'] ?? '' ) ) . '" loading="lazy" decoding="async" />';
	};
	?>
<section class="ap-trust-strip ap-trust-strip--<?php echo esc_attr( $props['layout'] ); ?>"<?php echo $props['reveal'] ? ' data-ap-reveal="fade"' : ''; ?>>
	<?php if ( $ap_edu ) : ?>
	<div class="apt-container">
		<div class="apt-inner">
			<?php if ( '' !== $label ) : ?>
				<p class="apt-label"><?php echo $ap_label; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed span is added. ?></p>
			<?php endif; ?>
			<ul class="apt-badges<?php echo $marquee ? '' : ' apt-badges--still'; ?>" role="list">
				<?php foreach ( $copies as $is_copy ) : ?>
					<?php foreach ( $items as $item ) : ?>
						<li class="apt-badge<?php echo $is_copy ? ' apt-dup' : ''; ?>"<?php echo $is_copy ? ' aria-hidden="true"' : ''; ?>>
							<?php if ( ! empty( $item['url'] ) ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $is_copy ? ' tabindex="-1"' : ''; ?>><?php echo $ap_img( $item, $is_copy ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></a>
							<?php else : ?>
								<?php echo $ap_img( $item, $is_copy ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<?php else : ?>
	<div class="apg-trust-in">
		<?php if ( '' !== $label ) : ?>
			<p class="apg-trust-label"><?php echo $ap_label; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed span is added. ?></p>
		<?php endif; ?>
		<div class="apg-trust-logos">
			<div class="apg-trust-track<?php echo $marquee ? '' : ' apg-trust-track--still'; ?>">
				<?php foreach ( $copies as $is_copy ) : ?>
					<?php foreach ( $items as $item ) : ?>
						<?php if ( ! empty( $item['url'] ) ) : ?>
							<a class="apg-tr-link<?php echo $is_copy ? ' apg-tr-dup' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $is_copy ? ' tabindex="-1" aria-hidden="true"' : ''; ?>><?php echo $ap_img( $item, $is_copy, $is_copy ? 'apg-tr-dup' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></a>
						<?php else : ?>
							<?php echo $is_copy ? str_replace( '<img', '<img aria-hidden="true"', $ap_img( $item, true, 'apg-tr-dup' ) ) : $ap_img( $item, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>
</section>
	<?php
	return;
endif;
$ap_ts_vars = array();
if ( (int) $props['label_width'] >= 200 && (int) $props['label_width'] <= 1200 ) {
	$ap_ts_vars[] = '--ts-label-w:' . (int) $props['label_width'] . 'px';
}
if ( (int) $props['gap'] >= 8 && (int) $props['gap'] <= 120 ) {
	$ap_ts_vars[] = '--ts-gap:' . (int) $props['gap'] . 'px';
}
?>
<section class="ap-trust-strip<?php echo 'inline' !== $props['layout'] ? ' ap-trust-strip--' . esc_attr( $props['layout'] ) : ''; ?>"<?php echo $props['reveal'] ? ' data-ap-reveal="fade"' : ''; ?><?php echo $ap_ts_vars ? ' style="' . esc_attr( implode( ';', $ap_ts_vars ) ) . '"' : ''; ?>>
	<div class="ap-trust-strip__inner">
		<?php if ( '' !== $label ) : ?>
			<p class="ap-trust-strip__label"><?php echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed span is added. ?></p>
		<?php endif; ?>
		<ul class="ap-trust-strip__badges<?php echo $marquee ? ' ap-trust-strip__badges--marquee' : ''; ?>">
			<?php foreach ( $copies as $is_copy ) : ?>
				<?php foreach ( $items as $item ) : ?>
					<li class="ap-trust-strip__badge<?php echo $is_copy ? ' ap-trust-strip__badge--dup' : ''; ?>"<?php echo $is_copy ? ' aria-hidden="true"' : ''; ?>>
						<?php if ( ! empty( $item['url'] ) ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $is_copy ? ' tabindex="-1"' : ''; ?>>
						<?php endif; ?>
						<img
							src="<?php echo esc_url( $item['image'] ); ?>"
							alt="<?php echo esc_attr( $is_copy ? '' : ( $item['alt'] ?? '' ) ); ?>"
							<?php
							// A logo drawn at its own size (stacked layout) carries w/h in px.
							$ap_w = isset( $item['w'] ) && is_numeric( $item['w'] ) && $item['w'] > 0 ? (int) $item['w'] : 0;
							$ap_h = isset( $item['h'] ) && is_numeric( $item['h'] ) && $item['h'] > 0 ? (int) $item['h'] : 0;
							echo $ap_w && $ap_h ? 'width="' . $ap_w . '" height="' . $ap_h . '" style="' . esc_attr( 'width:' . $ap_w . 'px;height:' . $ap_h . 'px' ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- integers and an escaped style.
							?>
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
</section>
