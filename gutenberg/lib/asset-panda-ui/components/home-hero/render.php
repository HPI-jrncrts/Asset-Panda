<?php
/**
 * home-hero — the homepage hero.
 *
 * Matches the live #ap-home-hero-v2: eyebrow (the page H1), a two-line title,
 * lede, buttons and compliance badges beside the live Ursa demo, over a client
 * logo band (pinned to the bottom of the first screen on desktop, a marquee on
 * phones). The demo's host only lets assetpanda.com frame it, so anywhere else
 * the component shows posters of the demo at rest, one per layout. The class
 * names are live's, nested under the component root.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keep only sizing classes with the expected prefix.
$ap_cls = static function ( $cls, $prefix ) {
	$out = array();
	foreach ( preg_split( '/\s+/', trim( (string) $cls ) ) as $token ) {
		if ( 0 === strpos( $token, $prefix ) && preg_match( '/^[a-z0-9_-]+$/', $token ) ) {
			$out[] = $token;
		}
	}
	return implode( ' ', $out );
};

$badges = array();
foreach ( is_array( $props['badges'] ) ? $props['badges'] : array() as $b ) {
	if ( is_array( $b ) && '' !== trim( (string) ( $b['image'] ?? '' ) ) ) {
		$badges[] = array(
			'image' => trim( (string) $b['image'] ),
			'alt'   => (string) ( $b['alt'] ?? '' ),
			'cls'   => $ap_cls( $b['cls'] ?? '', 'ap-compliance-img-' ),
		);
	}
}

$logos = array();
foreach ( is_array( $props['logos'] ) ? $props['logos'] : array() as $l ) {
	if ( is_array( $l ) && '' !== trim( (string) ( $l['image'] ?? '' ) ) ) {
		$logos[] = array(
			'image' => trim( (string) $l['image'] ),
			'alt'   => (string) ( $l['alt'] ?? '' ),
			'cls'   => $ap_cls( $l['cls'] ?? '', 'ap-logo-' ),
			'w'     => isset( $l['w'] ) && is_numeric( $l['w'] ) ? (int) $l['w'] : 0,
			'h'     => isset( $l['h'] ) && is_numeric( $l['h'] ) ? (int) $l['h'] : 0,
		);
	}
}

$logos_label  = trim( (string) $props['logos_label'] );
$logos_accent = trim( (string) $props['logos_accent'] );
$band_html    = esc_html( $logos_label );
if ( '' !== $logos_accent && false !== strpos( $logos_label, $logos_accent ) ) {
	$escaped   = esc_html( $logos_accent );
	$band_html = str_replace( $escaped, '<strong>' . $escaped . '</strong>', $band_html );
}

$background = trim( (string) $props['background'] );
$demo_url   = trim( (string) $props['demo_url'] );
$poster     = trim( (string) $props['poster'] );

$ap_logo = static function ( $l, $dup ) {
	return '<img class="' . esc_attr( trim( 'ap-logo ' . $l['cls'] . ( $dup ? ' ap-logo-dup' : '' ) ) ) . '" src="' . esc_url( $l['image'] ) . '" alt="' . esc_attr( $dup ? '' : $l['alt'] ) . '"' . ( $l['w'] && $l['h'] ? ' width="' . $l['w'] . '" height="' . $l['h'] . '"' : '' ) . ( $dup ? ' aria-hidden="true"' : '' ) . ' decoding="async" />';
};
?>
<section class="ap-home-hero" data-ap-home-hero<?php echo '' !== $background ? ' style="' . esc_attr( '--ap-home-hero-bg:url("' . esc_url_raw( $background ) . '")' ) . '"' : ''; ?>>
	<div class="ap-hero-bg" aria-hidden="true">
		<span class="ap-bg-shape ap-bg-red"></span>
		<span class="ap-bg-shape ap-bg-indigo"></span>
		<span class="ap-bg-shape ap-bg-violet"></span>
		<span class="ap-bg-shape ap-bg-yellow"></span>
		<span class="ap-bg-shape ap-bg-green"></span>
		<span class="ap-bg-gray"></span>
		<span class="ap-bg-blur"></span>
	</div>
	<div class="ap-hero-inner">
		<div class="ap-hero-copy">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<h1 class="ap-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></h1>
			<?php endif; ?>
			<h2 class="ap-hero-title ap-title-desktop"><span class="ap-h1-line ap-h1-dark"><?php echo esc_html( $props['title'] ); ?></span><?php if ( '' !== trim( (string) $props['title_accent'] ) ) : ?> <span class="ap-h1-line ap-h1-gradient"><?php echo ap_ui_lines( $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></span><?php endif; ?></h2>
			<?php if ( '' !== trim( (string) $props['lede_strong'] ) || '' !== trim( (string) $props['lede'] ) ) : ?>
				<div class="ap-body-group">
					<?php if ( '' !== trim( (string) $props['lede_strong'] ) ) : ?>
						<p class="ap-copy ap-copy-strong"><?php echo esc_html( $props['lede_strong'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
						<p class="ap-copy"><?php echo ap_ui_lines( $props['lede'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="ap-hero-trust-group">
				<div class="ap-btn-row">
					<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
						<a class="ap-btn ap-btn-primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] ) ) : ?>
						<a class="ap-btn ap-btn-secondary" href="<?php echo esc_url( $props['secondary_url'], array( 'http', 'https', 'tel', 'mailto' ) ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $badges ) : ?>
					<div class="ap-compliance-badges" aria-label="<?php echo esc_attr( '' !== trim( (string) $props['compliance_label'] ) ? $props['compliance_label'] : 'Compliance badges' ); ?>">
						<?php if ( '' !== trim( (string) $props['compliance_label'] ) ) : ?>
							<div class="ap-compliance-label"><?php echo esc_html( $props['compliance_label'] ); ?></div>
						<?php endif; ?>
						<div class="ap-compliance-logos">
							<?php foreach ( $badges as $b ) : ?>
								<img class="<?php echo esc_attr( trim( 'ap-compliance-img ' . $b['cls'] ) ); ?>" src="<?php echo esc_url( $b['image'] ); ?>" alt="<?php echo esc_attr( $b['alt'] ); ?>" decoding="async" />
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( '' !== $demo_url || '' !== $poster ) : ?>
			<div class="ap-hero-visual-wrap">
				<div class="ap-hero-visual">
					<?php if ( '' !== $demo_url ) : ?>
						<iframe class="ap-ursa-frame" src="<?php echo esc_url( $demo_url ); ?>" title="<?php echo esc_attr( $props['demo_title'] ); ?>" loading="lazy" allow="camera"></iframe>
					<?php else : ?>
						<picture>
							<?php if ( '' !== trim( (string) $props['poster_phone'] ) ) : ?>
								<source media="(max-width: 767px)" srcset="<?php echo esc_url( $props['poster_phone'] ); ?>" />
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $props['poster_tablet'] ) ) : ?>
								<source media="(max-width: 1023px)" srcset="<?php echo esc_url( $props['poster_tablet'] ); ?>" />
							<?php endif; ?>
							<img class="ap-ursa-frame ap-ursa-poster" src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $props['poster_alt'] ); ?>" decoding="async" fetchpriority="high" />
						</picture>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $logos ) : ?>
		<div class="ap-client-logo-band" aria-label="Trusted client logos">
			<?php if ( '' !== $logos_label ) : ?>
				<h2 class="ap-client-logo-text"><?php echo $band_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only a fixed <strong> is added. ?></h2>
			<?php endif; ?>
			<div class="ap-client-logo-viewport">
				<div class="ap-client-logo-track">
					<?php
					foreach ( $logos as $l ) {
						echo $ap_logo( $l, false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
					}
					foreach ( $logos as $l ) {
						echo $ap_logo( $l, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper.
					}
					?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</section>
