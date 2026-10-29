<?php
/**
 * intel-dashboard — "Smarter readiness with Asset Intelligence".
 *
 * Matches the live `.epx-dash` section: heading and lede over a dashboard table
 * with floating image chips and mock tip cards, then checks and two buttons.
 * Four live families draw it from four sheets, picked by `variant`. Each tip
 * card is decoration with its own small layout (an ID list, a big number, an
 * assignee card), so its body arrives as markup sanitised by ap_ui_markup().
 * Placement classes are reduced to epx-* / sp-* tokens, and every geometry
 * value is built from numbers alone.
 *
 * The epx-drev classes are the live scroll-driven entrance: pure CSS, applied
 * only where animation-timeline is supported, so content never depends on JS.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant = (string) $props['variant'];

// Keep only design tokens from a class list: epx-* and sp-* names.
$ap_classes = static function ( $cls ) {
	$out = array();
	foreach ( preg_split( '/\s+/', trim( (string) $cls ) ) as $token ) {
		if ( preg_match( '/^(epx|sp)-[a-z0-9_-]+$/', $token ) ) {
			$out[] = $token;
		}
	}
	return implode( ' ', $out );
};

// The Ursa brand mark as each live family draws it.
$ap_gradient_mark = '<span class="epx-g" style="background:none !important"><span class="epx-g">Ursa</span><sup class="epx-sup">AI</sup></span>';
$ap_plain_family  = in_array( $variant, array( 'readiness', 'reunification' ), true );
$ap_mark          = static function ( $escaped, $in_lede ) use ( $ap_gradient_mark, $ap_plain_family ) {
	if ( $ap_plain_family ) {
		$mark = 'Ursa<sup class="epx-sup">AI</sup>';
		$mark = $in_lede ? '<strong>' . $mark . '</strong>' : $mark;
	} else {
		$mark = $ap_gradient_mark;
	}
	return preg_replace( '/\bUrsa\s?AI\b/', $mark, (string) $escaped );
};

$title      = trim( (string) $props['title'] );
$accent     = trim( (string) $props['title_accent'] );
$title_html = ap_ui_lines( $title );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped    = esc_html( $accent );
	$title_html = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $title_html );
}

$chips = array();
foreach ( is_array( $props['chips'] ) ? $props['chips'] : array() as $chip ) {
	if ( ! is_array( $chip ) || '' === trim( (string) ( $chip['image'] ?? '' ) ) ) {
		continue;
	}
	$chips[] = array(
		'cls'    => $ap_classes( $chip['cls'] ?? '' ),
		'image'  => trim( (string) $chip['image'] ),
		'framed' => ! empty( $chip['framed'] ),
		'boxed'  => ! empty( $chip['boxed'] ),
	);
}

$tips = array();
foreach ( is_array( $props['tips'] ) ? $props['tips'] : array() as $tip ) {
	if ( ! is_array( $tip ) || '' === trim( (string) ( $tip['markup'] ?? '' ) ) ) {
		continue;
	}
	$height = isset( $tip['height'] ) && is_numeric( $tip['height'] ) ? (float) $tip['height'] : 0;
	$tips[] = array(
		'cls'    => $ap_classes( $tip['cls'] ?? '' ),
		'height' => $height > 0 && $height < 2000 ? round( $height, 2 ) : 0,
		'markup' => (string) $tip['markup'],
	);
}

$checks = array_values(
	array_filter(
		array_map(
			static function ( $c ) {
				return trim( (string) $c );
			},
			is_array( $props['checks'] ) ? $props['checks'] : array()
		),
		static function ( $c ) {
			return '' !== $c;
		}
	)
);
$ctas   = array_values(
	array_filter(
		is_array( $props['ctas'] ) ? $props['ctas'] : array(),
		static function ( $c ) {
			return is_array( $c ) && '' !== trim( (string) ( $c['label'] ?? '' ) ) && '' !== trim( (string) ( $c['url'] ?? '' ) );
		}
	)
);

$check_svg = ap_ui_svg( $props['check_svg'] );
$vars      = array();
if ( '' !== trim( (string) $props['background'] ) ) {
	$vars[] = '--ap-intel-dashboard-bg:url("' . esc_url_raw( $props['background'] ) . '")';
}
if ( '' !== trim( (string) $props['background_mobile'] ) ) {
	$vars[] = '--ap-intel-dashboard-bg-m:url("' . esc_url_raw( $props['background_mobile'] ) . '")';
}
if ( (int) $props['check_width'] > 0 && (int) $props['check_width'] < 1200 ) {
	$vars[] = '--ap-intel-dashboard-check-w:' . (int) $props['check_width'] . 'px';
}
if ( 'solution' === $variant && (int) $props['head_width'] > 0 && (int) $props['head_width'] <= 1120 ) {
	$vars[] = '--ap-intel-dashboard-head-w:' . (int) $props['head_width'] . 'px';
}
$image        = trim( (string) $props['image'] );
$image_mobile = trim( (string) $props['image_mobile'] );

// Each sheet positions the chips against a different box: Emergency Preparedness
// inside the table stage, the other three against the whole section wrap.
// chips_anchor "dashboard" (solution only, opt-in) moves the solution chips into
// the stage too, inside a layer placed where the wrap origin sits relative to the
// stage in the design, so the sp-chip--N slots keep their coordinates.
$chips_in_stage = 'readiness' === $variant;
$chips_on_dash  = 'solution' === $variant && 'dashboard' === $props['chips_anchor'];
$ap_chips       = static function ( array $chips ) {
	ob_start();
	foreach ( $chips as $chip ) {
		$class = esc_attr( trim( 'epx-chip ' . $chip['cls'] ) );
		$img   = '<img src="' . esc_url( $chip['image'] ) . '" alt="" loading="lazy" decoding="async" />';
		if ( $chip['framed'] ) {
			echo '<span class="' . $class . '" aria-hidden="true"><span class="sp-chip__in">' . $img . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		} elseif ( $chip['boxed'] ) {
			echo '<span class="' . $class . '" aria-hidden="true">' . $img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		} else {
			echo '<img class="' . $class . '" src="' . esc_url( $chip['image'] ) . '" alt="" aria-hidden="true" loading="lazy" decoding="async" />'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		}
	}
	return ob_get_clean();
};
?>
<section class="ap-intel-dashboard ap-intel-dashboard--<?php echo esc_attr( $variant ); ?>"<?php echo $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : ''; ?>>
	<div class="epx-dash">
		<div class="epx-dash__orbs" aria-hidden="true"></div>
		<div class="epx-dash__wrap">
			<?php echo $chips_in_stage || $chips_on_dash ? '' : $ap_chips( $chips ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>
			<div class="epx-dash__head epx-drev">
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="epx-dash__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 class="epx-dash__h2"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and a fixed span are added. ?></h2>
				<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
					<p class="epx-dash__lede"><?php echo $ap_mark( esc_html( $props['lede'] ), true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only the fixed brand mark is added. ?></p>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $image || $tips || ( ( $chips_in_stage || $chips_on_dash ) && $chips ) ) : ?>
				<div class="epx-dash__stagebox">
					<div class="epx-dash__stage">
						<?php if ( '' !== $image ) : ?>
							<picture>
								<?php if ( '' !== $image_mobile ) : ?>
									<source media="(max-width:1080px)" srcset="<?php echo esc_url( $image_mobile ); ?>" />
								<?php endif; ?>
								<img class="epx-dash__table epx-drev" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="eager" fetchpriority="low" decoding="async" />
							</picture>
						<?php endif; ?>

						<?php echo $chips_in_stage ? $ap_chips( $chips ) : ( $chips_on_dash && $chips ? '<div class="epx-dash__chips" aria-hidden="true">' . $ap_chips( $chips ) . '</div>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts. ?>

						<?php foreach ( $tips as $tip ) : ?>
							<div class="<?php echo esc_attr( trim( 'epx-tip ' . $tip['cls'] ) ); ?>"<?php echo $tip['height'] ? ' style="' . esc_attr( 'height:' . $tip['height'] . 'px' ) . '"' : ''; ?>><?php echo ap_ui_markup( $tip['markup'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_markup(). ?></div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $checks ) : ?>
				<ul class="epx-dash__checks">
					<?php foreach ( $checks as $i => $check ) : ?>
						<li class="epx-drev<?php echo $i ? ' epx-drev--d' . (int) min( $i, 4 ) : ''; ?>"><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?><span class="epx-dash__checktext"><?php echo $ap_mark( esc_html( $check ), false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only the fixed brand mark is added. ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $ctas ) : ?>
				<div class="epx-dash__ctas epx-drev">
					<?php foreach ( $ctas as $i => $cta ) : ?>
						<a class="epx-btn <?php echo 0 === $i ? 'epx-btn--primary' : 'epx-btn--outline'; ?>" href="<?php echo esc_url( $cta['url'] ); ?>"><span><?php echo $ap_mark( esc_html( $cta['label'] ), false ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only the fixed brand mark is added. ?></span></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
