<?php
/**
 * integrations-hero — the Integrations hub hero.
 *
 * Figma: Desktop — Integrations > Hero Section (7310:158542). The copy column
 * (eyebrow, H1, lede, demo button) sits beside one aspect-locked art box
 * (546 x 438 design px, --u = 1/546 of its width) holding the orbit rings, the
 * logo bubbles, the app card with the panda and a few sparkles, so the whole
 * picture scales as one. Bubble placement arrives as numbers (centre x / y as a
 * % of the art box, diameter in design px) and every style attribute here is
 * built from numbers alone — author text never reaches a style attribute.
 *
 * The art is one image to assistive tech (role="img") labelled with the logo
 * names; its parts are decorative. The H1 copy is the LCP element: the rings are
 * a CSS mask (never an LCP candidate) and the only <img> layers are the small
 * logos and the panda. Nothing here carries data-ap-reveal (above the fold); the
 * art animates on load from style.css.
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

$num = static function ( $value, $min, $max ) {
	if ( ! is_numeric( $value ) ) {
		return null;
	}
	$value = (float) $value;
	return ( $value < $min || $value > $max ) ? null : round( $value, 3 );
};

$logos = array();
foreach ( is_array( $props['logos'] ) ? $props['logos'] : array() as $logo ) {
	if ( count( $logos ) >= 20 || ! is_array( $logo ) ) {
		continue;
	}
	$icon = trim( (string) ( $logo['icon'] ?? '' ) );
	$x    = $num( $logo['x'] ?? null, 0, 100 );
	$y    = $num( $logo['y'] ?? null, 0, 100 );
	$size = $num( $logo['size'] ?? null, 12, 160 );
	if ( '' === $icon || '' === esc_url( $icon ) || null === $x || null === $y || null === $size ) {
		continue;
	}
	$scale   = $num( $logo['scale'] ?? null, 0.2, 0.9 );
	$scale   = null === $scale ? 0.5 : $scale;
	$i       = count( $logos );
	$logos[] = array(
		'icon'  => $icon,
		'name'  => trim( (string) ( $logo['name'] ?? '' ) ),
		'px'    => max( 1, (int) round( $size * $scale ) ),
		// Numbers only: centre, diameter, logo share, stagger index, float period.
		'style' => sprintf( '--x:%s%%;--y:%s%%;--s:%s;--k:%s;--i:%d;--d:%ss', $x, $y, $size, $scale, $i, 6 + ( $i % 4 ) * 0.9 ),
	);
}

$names = array_values( array_filter( array_column( $logos, 'name' ) ) );
$label = trim( (string) $props['art_label'] );
if ( '' === $label ) {
	$label = $names ? 'Asset Panda connects with ' . implode( ', ', $names ) . '.' : 'Asset Panda integrations';
}

// The eleven sparkle dots of the artwork (Figma Ellipse 706-716): centre as a % of the art box.
$sparks = array(
	array( 40, 50.03 ),
	array( 50.2, 37.08 ),
	array( 53.92, 19.24 ),
	array( 22.75, 47.35 ),
	array( 28.63, 64.46 ),
	array( 66.08, 40.99 ),
	array( 59.8, 52.97 ),
	array( 70.78, 66.17 ),
	array( 80.78, 51.5 ),
	array( 47.65, 72.52 ),
	array( 34.12, 25.84 ),
);

$eyebrow    = trim( (string) $props['eyebrow'] );
$lede       = trim( (string) $props['lede'] );
$panda      = trim( (string) $props['panda'] );
$has_button = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
?>
<section class="ap-integrations-hero">
	<div class="ap-integrations-hero__inner">
		<div class="ap-integrations-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-integrations-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h1 class="ap-integrations-hero__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h1>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-integrations-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<?php if ( $has_button ) : ?>
				<div class="ap-integrations-hero__cta">
					<a class="ap-integrations-hero__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $props['cta_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
				</div>
			<?php endif; ?>
		</div>

		<div class="ap-integrations-hero__art" role="img" aria-label="<?php echo esc_attr( $label ); ?>">
			<span class="ap-integrations-hero__rings"></span>
			<span class="ap-integrations-hero__glow"></span>
			<?php foreach ( $logos as $logo ) : ?>
				<span class="ap-integrations-hero__bubble" style="<?php echo esc_attr( $logo['style'] ); ?>"><img src="<?php echo esc_url( $logo['icon'] ); ?>" alt="" width="<?php echo esc_attr( (string) $logo['px'] ); ?>" height="<?php echo esc_attr( (string) $logo['px'] ); ?>" decoding="async" /></span>
			<?php endforeach; ?>
			<span class="ap-integrations-hero__card"></span>
			<?php foreach ( $sparks as $n => $spark ) : ?>
				<span class="ap-integrations-hero__spark" style="<?php echo esc_attr( sprintf( '--x:%s%%;--y:%s%%;--i:%d', $spark[0], $spark[1], $n ) ); ?>"></span>
			<?php endforeach; ?>
			<?php if ( '' !== $panda ) : ?>
				<img class="ap-integrations-hero__panda" src="<?php echo esc_url( $panda ); ?>" alt="" width="157" height="114" decoding="async" />
			<?php endif; ?>
		</div>
	</div>
</section>
