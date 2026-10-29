<?php
/**
 * industries — sector cards on a dark indigo panel.
 *
 * Mirrors the live homepage "Design to adapt" section
 * (#ap-sector-intelligence-v4) element for element, so the ported stylesheet
 * applies class for class: a blurred colour field behind a rounded indigo
 * panel that widens to the browser edges on hover, a heading, a sticky row of
 * sector chips on phones, and a grid of whole-card links, each with a photo
 * fading into the card, a sector icon, copy and an "Explore" line.
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

$icons = array(
	'government'    => 'icon-government.svg',
	'manufacturing' => 'icon-manufacturing.svg',
	'healthcare'    => 'icon-healthcare.svg',
	'education'     => 'icon-education.svg',
	'construction'  => 'icon-construction.svg',
	'nonprofit'     => 'icon-nonprofit.svg',
);

$eyebrow    = trim( (string) $props['eyebrow'] );
$tabs_label = trim( (string) $props['tabs_label'] );
$reveal     = (bool) $props['reveal'];

// Anchor ids for the phone chip row: an item's own `anchor`, else one unique
// to this render.
// Each card also carries its sector as a class (ap-sector-government …), as on
// live; only the known sector keys ever reach it.
$uid       = 'ap-ind-' . substr( md5( uniqid( '', true ) ), 0, 8 );
$anchors   = array();
$icon_urls = array();
$sectors   = array();
foreach ( $items as $i => $item ) {
	$own             = isset( $item['anchor'] ) && is_string( $item['anchor'] ) ? sanitize_html_class( trim( $item['anchor'] ) ) : '';
	$anchors[ $i ]   = '' !== $own ? $own : $uid . '-' . $i;
	$sectors[ $i ]   = isset( $item['icon'] ) && is_string( $item['icon'] ) && isset( $icons[ $item['icon'] ] ) ? $item['icon'] : '';
	$icon_urls[ $i ] = '' !== $sectors[ $i ] ? ap_ui_asset_url( 'industries', $icons[ $sectors[ $i ] ] ) : '';
}
?>
<section class="ap-industries">
	<div class="ap-sector-bg" aria-hidden="true">
		<svg class="ap-sector-bg-svg" viewBox="0 0 1440 1207" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
			<defs>
				<filter id="<?php echo esc_attr( $uid ); ?>-aurora" x="-35%" y="-35%" width="170%" height="170%" color-interpolation-filters="sRGB">
					<feGaussianBlur stdDeviation="120"></feGaussianBlur>
				</filter>
			</defs>
			<rect width="1440" height="1207" fill="#ffffff"></rect>
			<g filter="url(#<?php echo esc_attr( $uid ); ?>-aurora)">
				<circle cx="214.5" cy="663.5" r="426.5" fill="#5044E1"></circle>
				<circle cx="1150.5" cy="622.5" r="518.5" fill="#5044E1"></circle>
				<circle cx="546" cy="956" r="523" fill="#F3BF51"></circle>
				<circle cx="484.5" cy="737.5" r="426.5" fill="#00A991"></circle>
				<circle cx="1442.5" cy="1002.5" r="426.5" fill="#00A991"></circle>
				<ellipse cx="684.5" cy="1206" rx="980.5" ry="438" fill="#ffffff"></ellipse>
				<ellipse cx="751.5" cy="-49" rx="980.5" ry="438" fill="#F9FAFB"></ellipse>
			</g>
			<rect width="1440" height="1207" fill="#ffffff" opacity="0.15"></rect>
		</svg>
	</div>
	<div class="ap-sector-box">
		<div class="ap-sector-box-bg" aria-hidden="true"></div>
		<div class="ap-sector-inner">
			<div class="ap-sector-heading"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
				<?php if ( '' !== $eyebrow ) : ?>
					<p><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
			</div>

			<?php if ( count( $items ) > 1 ) : ?>
				<nav class="ap-sector-tabs" aria-label="<?php echo esc_attr( '' !== $tabs_label ? $tabs_label : $title ); ?>">
					<?php foreach ( $items as $i => $item ) : ?>
						<a class="ap-sector-tab" href="#<?php echo esc_attr( $anchors[ $i ] ); ?>">
							<?php if ( '' !== $icon_urls[ $i ] ) : ?>
								<img src="<?php echo esc_url( $icon_urls[ $i ] ); ?>" alt="" aria-hidden="true" width="20" height="20" />
							<?php endif; ?>
							<span><?php echo esc_html( $item['title'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<div class="ap-sector-grid">
				<?php
				foreach ( $items as $i => $item ) :
					$link_url   = isset( $item['link_url'] ) ? trim( (string) $item['link_url'] ) : '';
					$link_label = isset( $item['link_label'] ) ? trim( (string) $item['link_label'] ) : '';
					$tag        = '' !== $link_url ? 'a' : 'div';
					?>
					<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed 'a' or 'div'. ?> class="ap-sector-card<?php echo '' !== $sectors[ $i ] ? ' ap-sector-' . esc_attr( $sectors[ $i ] ) : ''; ?>" id="<?php echo esc_attr( $anchors[ $i ] ); ?>"<?php if ( 'a' === $tag ) : ?> href="<?php echo esc_url( $link_url ); ?>"<?php endif; ?><?php if ( $reveal ) : ?> data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 3 ) + 1 ) ); ?>"<?php endif; ?>>
						<span class="ap-card-image" aria-hidden="true">
							<?php if ( ! empty( $item['image'] ) && is_string( $item['image'] ) ) : ?>
								<img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy" decoding="async" />
							<?php endif; ?>
						</span>
						<span class="ap-card-base-overlay" aria-hidden="true"></span>
						<span class="ap-card-hover-overlay" aria-hidden="true"></span>
						<span class="ap-card-bamboo" aria-hidden="true"></span>
						<div class="ap-sector-content">
							<?php if ( '' !== $icon_urls[ $i ] ) : ?>
								<div class="ap-sector-icon" aria-hidden="true">
									<img src="<?php echo esc_url( $icon_urls[ $i ] ); ?>" alt="" width="24" height="24" />
								</div>
							<?php endif; ?>
							<div class="ap-sector-text">
								<h3><?php echo esc_html( $item['title'] ); ?></h3>
								<p><?php echo esc_html( $item['body'] ); ?></p>
							</div>
							<?php if ( '' !== $link_label ) : ?>
								<span class="ap-sector-link"><?php echo esc_html( $link_label ); ?> <strong aria-hidden="true">&rarr;</strong></span>
							<?php endif; ?>
						</div>
					</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed 'a' or 'div'. ?>>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
