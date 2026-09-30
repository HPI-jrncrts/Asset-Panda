<?php
/**
 * value-props — benefit cards with calls to action.
 *
 * Figma: Home Page > Why Teams Choose Asset Panda (4331:24830). Heading left,
 * buttons bottom-right; 2x2 cards, each with a 1px accent border, a faint
 * accent-to-white wash and a matching check-circle; a centred closing line.
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

$accents = array( 'yellow', 'red', 'green', 'indigo' );

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
?>
<section class="ap-vprops">
	<div class="ap-vprops__inner">
		<div class="ap-vprops__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<div class="ap-vprops__headings">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-vprops__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-vprops__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
				<?php if ( '' !== $intro ) : ?>
					<p class="ap-vprops__intro"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $has_primary || $has_secondary ) : ?>
				<div class="ap-vprops__actions">
					<?php if ( $has_primary ) : ?>
						<a class="ap-vprops__btn ap-vprops__btn--primary" href="<?php echo esc_url( $props['primary_url'] ); ?>"><?php echo esc_html( $props['primary_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( $has_secondary ) : ?>
						<a class="ap-vprops__btn ap-vprops__btn--outline" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $items ) ) : ?>
			<ul class="ap-vprops__grid">
				<?php
				foreach ( $items as $i => $item ) :
					$accent = isset( $item['accent'] ) && in_array( $item['accent'], $accents, true ) ? $item['accent'] : $accents[ $i % count( $accents ) ];
					?>
					<li class="ap-vprops__card ap-vprops__card--<?php echo esc_attr( $accent ); ?>"
						<?php if ( $reveal ) : ?>
							data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 2 ) + 1 ) ); ?>"
						<?php endif; ?>
					>
						<img class="ap-vprops__check" src="<?php echo esc_url( ap_ui_asset_url( 'value-props', 'check-' . $accent . '.svg' ) ); ?>" alt="" width="24" height="24" />
						<h3 class="ap-vprops__card-title"><?php echo ap_ui_lines( $item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h3>
						<p class="ap-vprops__card-body"><?php echo esc_html( $item['body'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $closing ) : ?>
			<p class="ap-vprops__closing"><?php echo esc_html( $closing ); ?></p>
		<?php endif; ?>
	</div>
</section>
