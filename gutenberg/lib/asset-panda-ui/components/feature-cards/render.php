<?php
/**
 * feature-cards — heading + capability checklist.
 *
 * Figma: Home Page > Intelligence In Action (4331:24627). 355px heading column,
 * 64px gutter, then a 2-column list: 40px check icon + H5 title + 18px body.
 * On phones behaviour.js turns the list into a one-open accordion (Figma Home
 * Mobile, 4333:40892); without JavaScript every item simply stays open.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && ! empty( $i['title'] ) && ! empty( $i['body'] );
		}
	)
);

if ( '' === $title || empty( $items ) ) {
	return;
}

$eyebrow = trim( (string) $props['eyebrow'] );
$accent  = trim( (string) $props['title_accent'] );
$intro   = trim( (string) $props['intro'] );
$reveal  = (bool) $props['reveal'];
$check   = ap_ui_asset_url( 'feature-cards', 'check.svg' );

// Ids for the phone accordion's aria-controls; unique across the page.
$uid = 'ap-fcards-' . substr( md5( uniqid( '', true ) ), 0, 8 );
?>
<section class="ap-fcards" data-ap-fcards>
	<div class="ap-fcards__inner">
		<div class="ap-fcards__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-fcards__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-fcards__title">
				<?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?>
				<?php if ( '' !== $accent ) : ?>
					<span class="ap-fcards__accent"><?php echo esc_html( $accent ); ?></span>
				<?php endif; ?>
			</h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="ap-fcards__intro"><?php echo ap_ui_lines( $intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
			<?php endif; ?>
		</div>

		<ul class="ap-fcards__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<li class="ap-fcards__item"
					<?php if ( $reveal ) : ?>
						data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 2 ) + 1 ) ); ?>"
					<?php endif; ?>
				>
					<img class="ap-fcards__icon" src="<?php echo esc_url( $check ); ?>" alt="" width="40" height="40" />
					<div class="ap-fcards__text">
						<h3 class="ap-fcards__item-title"><?php echo esc_html( $item['title'] ); ?></h3>
						<p class="ap-fcards__item-body" id="<?php echo esc_attr( $uid . '-' . $i ); ?>"><?php echo esc_html( $item['body'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
