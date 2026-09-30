<?php
/**
 * platform-stack — capability cards with a numbered index.
 *
 * Matches the live homepage (dev page 7, #ap-platform-stack-v3), built from
 * Figma Platform (4331:24729). Each card carries a screenshot and an optional
 * hover preview (image_hover) that slides over it when the card fills with
 * its accent: on hover at desktop, and on the card being read on phones.
 * From 1280px the index sits in a sticky 282px rail and the cards stack as you
 * scroll (--i on each card sets its sticky offset); below that the index is a
 * plain numbered list above the cards.
 *
 * The index is a list of in-page anchors, so it works with no JavaScript;
 * behaviour.js only adds the "currently reading" state.
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

$accents = array( 'indigo', 'yellow', 'green', 'red', 'violet', 'ursa' );
$eyebrow = trim( (string) $props['eyebrow'] );
$intro   = trim( (string) $props['intro'] );
$kicker  = trim( (string) $props['kicker'] );
$reveal  = (bool) $props['reveal'];
$arrow   = ap_ui_asset_url( 'platform-stack', 'arrow.svg' );
$mark    = static function ( $text ) {
	return ap_ui_brandmarks( esc_html( (string) $text ) );
};

// Anchor ids must be unique across the page, not just this component.
$uid = 'ap-pstack-' . substr( md5( uniqid( '', true ) ), 0, 8 );

$accent_for = static function ( $item, $i ) use ( $accents ) {
	return ( isset( $item['accent'] ) && in_array( $item['accent'], $accents, true ) ) ? $item['accent'] : $accents[ $i % count( $accents ) ];
};
?>
<section class="ap-pstack" data-ap-pstack>
	<div class="ap-pstack__inner">
		<div class="ap-pstack__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-pstack__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-pstack__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="ap-pstack__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $kicker ) : ?>
				<p class="ap-pstack__kicker"><?php echo esc_html( $kicker ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-pstack__body">
			<nav class="ap-pstack__nav" aria-label="<?php echo esc_attr( '' !== trim( (string) $props['nav_title'] ) ? $props['nav_title'] : $title ); ?>">
				<ol class="ap-pstack__navlist">
					<?php foreach ( $items as $i => $item ) : ?>
						<li>
							<a class="ap-pstack__navlink"
								href="#<?php echo esc_attr( $uid . '-' . $i ); ?>"
								data-ap-pstack-link="<?php echo esc_attr( (string) $i ); ?>">
								<span class="ap-pstack__dot" aria-hidden="true"></span>
								<span class="ap-pstack__navtext"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?> <?php echo $mark( ! empty( $item['nav_label'] ) ? $item['nav_label'] : $item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>

			<ol class="ap-pstack__cards">
				<?php foreach ( $items as $i => $item ) : ?>
					<li class="ap-pstack__card ap-pstack__card--<?php echo esc_attr( $accent_for( $item, $i ) ); ?>"
						id="<?php echo esc_attr( $uid . '-' . $i ); ?>"
						data-ap-pstack-panel="<?php echo esc_attr( (string) $i ); ?>"
						style="--i: <?php echo (int) $i; ?>;"
						<?php echo $reveal ? ' data-ap-reveal' : ''; ?>
					>
						<div class="ap-pstack__copy">
							<h3 class="ap-pstack__card-title"><?php echo $mark( $item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></h3>
							<p class="ap-pstack__card-body"><?php echo $mark( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></p>
							<?php if ( ! empty( $item['link_label'] ) && ! empty( $item['link_url'] ) ) : ?>
								<a class="ap-pstack__link" href="<?php echo esc_url( $item['link_url'] ); ?>">
									<span><?php echo $mark( $item['link_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></span>
									<img src="<?php echo esc_url( $arrow ); ?>" alt="" width="24" height="24" />
								</a>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $item['image'] ) ) : ?>
							<div class="ap-pstack__shot">
								<div class="ap-pstack__screen">
									<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" loading="lazy" decoding="async" />
								</div>
								<?php if ( ! empty( $item['image_hover'] ) ) : ?>
									<div class="ap-pstack__preview" aria-hidden="true">
										<img src="<?php echo esc_url( $item['image_hover'] ); ?>" alt="" loading="lazy" decoding="async" />
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</div>
</section>
