<?php
/**
 * industries — sector cards on a dark indigo panel.
 *
 * Figma: Home Page > Industries (4331:24718). A 1200px indigo-900 panel
 * (radius 24, padding 64/40) floats over a decorative colour field; cards are
 * indigo-950 with a 232px photo fading into the card, a teal sector icon,
 * white title/body and an "Explore" link.
 *
 * Sector icons ship with the component (WordPress refuses SVG uploads), so an
 * item names its sector rather than uploading an icon.
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

// Nonprofit reuses the education glyph, exactly as the Figma card does.
$icons = array(
	'government'    => 'icon-government.svg',
	'manufacturing' => 'icon-manufacturing.svg',
	'healthcare'    => 'icon-healthcare.svg',
	'education'     => 'icon-education.svg',
	'construction'  => 'icon-construction.svg',
	'nonprofit'     => 'icon-education.svg',
);

$eyebrow = trim( (string) $props['eyebrow'] );
$reveal  = (bool) $props['reveal'];
$arrow   = ap_ui_asset_url( 'industries', 'arrow.svg' );

// Anchor ids for the phone chip list; unique across the page.
$uid = 'ap-ind-' . substr( md5( uniqid( '', true ) ), 0, 8 );
?>
<section class="ap-industries">
	<img class="ap-industries__decor" src="<?php echo esc_url( ap_ui_asset_url( 'industries', 'colorset.svg' ) ); ?>" alt="" aria-hidden="true" />
	<div class="ap-industries__box">
		<img class="ap-industries__orb ap-industries__orb--a" src="<?php echo esc_url( ap_ui_asset_url( 'industries', 'orb-a.svg' ) ); ?>" alt="" aria-hidden="true" />
		<img class="ap-industries__orb ap-industries__orb--b" src="<?php echo esc_url( ap_ui_asset_url( 'industries', 'orb-b.svg' ) ); ?>" alt="" aria-hidden="true" />

		<div class="ap-industries__inner">
			<div class="ap-industries__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-industries__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-industries__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
			</div>

			<?php if ( count( $items ) > 1 ) : ?>
				<nav class="ap-industries__chips" aria-label="<?php echo esc_attr( $title ); ?>">
					<ul>
						<?php
						foreach ( $items as $i => $item ) :
							$chip_icon = isset( $item['icon'], $icons[ $item['icon'] ] ) ? $icons[ $item['icon'] ] : '';
							?>
							<li>
								<a class="ap-industries__chip" href="#<?php echo esc_attr( $uid . '-' . $i ); ?>">
									<?php if ( '' !== $chip_icon ) : ?>
										<img src="<?php echo esc_url( ap_ui_asset_url( 'industries', $chip_icon ) ); ?>" alt="" width="24" height="24" />
									<?php endif; ?>
									<span><?php echo esc_html( $item['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<ul class="ap-industries__grid">
				<?php
				foreach ( $items as $i => $item ) :
					$icon     = isset( $item['icon'], $icons[ $item['icon'] ] ) ? $icons[ $item['icon'] ] : '';
					$has_link = ! empty( $item['link_label'] ) && ! empty( $item['link_url'] );
					?>
					<li class="ap-industries__card" id="<?php echo esc_attr( $uid . '-' . $i ); ?>"
						<?php if ( $reveal ) : ?>
							data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 3 ) + 1 ) ); ?>"
						<?php endif; ?>
					>
						<?php if ( ! empty( $item['image'] ) ) : ?>
							<div class="ap-industries__media">
								<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" loading="lazy" decoding="async" />
							</div>
						<?php endif; ?>
						<?php if ( '' !== $icon ) : ?>
							<img class="ap-industries__icon" src="<?php echo esc_url( ap_ui_asset_url( 'industries', $icon ) ); ?>" alt="" width="24" height="24" />
						<?php endif; ?>
						<div class="ap-industries__text">
							<h3 class="ap-industries__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
							<p class="ap-industries__card-body"><?php echo esc_html( $item['body'] ); ?></p>
						</div>
						<?php if ( $has_link ) : ?>
							<a class="ap-industries__link" href="<?php echo esc_url( $item['link_url'] ); ?>">
								<span><?php echo esc_html( $item['link_label'] ); ?></span>
								<img src="<?php echo esc_url( $arrow ); ?>" alt="" width="24" height="24" />
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
