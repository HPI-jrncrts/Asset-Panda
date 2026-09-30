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
?>
<section class="ap-trust-strip"<?php echo $props['reveal'] ? ' data-ap-reveal="fade"' : ''; ?>>
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
