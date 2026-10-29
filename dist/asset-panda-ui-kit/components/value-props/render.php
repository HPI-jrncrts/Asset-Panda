<?php
/**
 * value-props — benefit cards with calls to action.
 *
 * Mirrors the live homepage "Built for the field" section (#ap-built-field-v2)
 * element for element, so the ported stylesheet applies class for class:
 * heading left and buttons bottom-right, cards two to a row, each with a 1px
 * accent border, a faint accent-to-white wash and a check badge that fills on
 * hover, then a centred closing line.
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

// Prop accent => live card class.
$accents = array(
	'yellow' => 'gold',
	'red'    => 'pink',
	'green'  => 'green',
	'indigo' => 'purple',
);
$order   = array_keys( $accents );

$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && ! empty( $i['title'] ) && ! empty( $i['body'] );
		}
	)
);

$eyebrow = trim( (string) $props['eyebrow'] );
$intro   = trim( (string) $props['intro'] );
$closing = trim( (string) $props['closing'] );
$reveal  = (bool) $props['reveal'];

$has_primary   = '' !== trim( (string) $props['primary_label'] ) && '' !== trim( (string) $props['primary_url'] );
$has_secondary = '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] );

// Live lays the cards out two to a row.
$rows = array_chunk( $items, 2, true );
?>
<section class="ap-vprops">
	<div class="ap-built-inner">
		<div class="ap-built-head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<div class="ap-built-copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
				<?php if ( '' !== $intro ) : ?>
					<p class="ap-copy"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $has_primary || $has_secondary ) : ?>
				<div class="ap-actions">
					<?php if ( $has_primary ) : ?>
						<a class="ap-btn ap-btn-primary" href="<?php echo esc_url( $props['primary_url'] ); ?>"><?php echo esc_html( $props['primary_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( $has_secondary ) : ?>
						<a class="ap-btn ap-btn-outline" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $rows ) ) : ?>
			<div class="ap-cards">
				<?php foreach ( $rows as $row ) : ?>
					<div class="ap-row">
						<?php
						$k = 0;
						foreach ( $row as $i => $item ) :
							$accent = isset( $item['accent'] ) && is_string( $item['accent'] ) && isset( $accents[ $item['accent'] ] ) ? $item['accent'] : $order[ $i % count( $order ) ];
							++$k;
							?>
							<article class="ap-card ap-card-<?php echo esc_attr( $accents[ $accent ] ); ?>"<?php if ( $reveal ) : ?> data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) $k ); ?>"<?php endif; ?>>
								<span class="ap-check" aria-hidden="true">&#10003;</span>
								<h3><?php echo ap_ui_lines( $item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h3>
								<p><?php echo esc_html( $item['body'] ); ?></p>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $closing ) : ?>
			<p class="ap-footnote"><?php echo esc_html( $closing ); ?></p>
		<?php endif; ?>
	</div>
</section>
