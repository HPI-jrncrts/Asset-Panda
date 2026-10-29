<?php
/**
 * solution-hero — the hero on the September 2026 solution pages.
 *
 * Matches the live .epx-hero on /solutions/{asset-management, inventory-
 * management, fixed-asset-tracking, it-asset-tracking, tool-tracking,
 * equipment-management}/: the copy column beside a visual box (sized per page)
 * holding a cut-out photo and floating mock UI cards, with a chip strip below.
 *
 * The six pages share one stylesheet but each draws different mock cards
 * (a request bubble, an assistant answer, a record, a status chip…). They are
 * decoration, so each card's body arrives as markup and is sanitised by
 * ap_ui_markup(); every geometry value is built from numbers alone. Inner class
 * names are the live ones, so this stays diffable against the ported sheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item ) {
	$svg = ap_ui_svg( $item['icon_svg'] ?? '' );
	if ( '' !== $svg ) {
		return $svg;
	}
	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	return '' !== $icon ? '<img src="' . esc_url( $icon ) . '" alt="" loading="lazy" decoding="async" />' : '';
};

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $html );
	}
	return $html;
};

// "left:103px;top:0px;width:318.5px" from whichever keys hold numbers.
$ap_px = static function ( $geo, array $map ) {
	$out = array();
	foreach ( $map as $key => $property ) {
		if ( is_array( $geo ) && isset( $geo[ $key ] ) && is_numeric( $geo[ $key ] ) ) {
			$out[] = $property . ':' . round( (float) $geo[ $key ], 2 ) . 'px';
		}
	}
	return implode( ';', $out );
};

$slots  = array( 'c1', 'c2', 'c3', 'f1', 'f2', 'f3' );
$cards  = array_values(
	array_filter(
		is_array( $props['cards'] ) ? $props['cards'] : array(),
		static function ( $c ) {
			return is_array( $c ) && '' !== trim( (string) ( $c['markup'] ?? '' ) );
		}
	)
);
$badges = array_values(
	array_filter(
		is_array( $props['badges'] ) ? $props['badges'] : array(),
		static function ( $b ) {
			return is_array( $b ) && ! empty( $b['image'] );
		}
	)
);
$feats  = array_values(
	array_filter(
		is_array( $props['features'] ) ? $props['features'] : array(),
		static function ( $f ) {
			return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) );
		}
	)
);
$bg     = trim( (string) $props['background'] );

// The visual box differs per page (live sizes it to each page's photo), and so
// does the phone scale table that shrinks it. Both reach CSS as custom
// properties built from numbers alone; anything left out keeps the stylesheet's
// asset-management values. Scale steps exist only at the breakpoints the
// stylesheet declares, so other keys are ignored.
$ap_style  = array();
if ( '' !== $bg ) {
	$ap_style[] = '--ap-solution-bg:url("' . esc_url_raw( $bg ) . '")';
}
$ap_visual = is_array( $props['visual'] ) ? $props['visual'] : array();
foreach ( array( 'x' => '--ap-sh-vx', 'y' => '--ap-sh-vy', 'w' => '--ap-sh-vw', 'h' => '--ap-sh-vh', 'right' => '--ap-sh-vr' ) as $key => $var ) {
	if ( isset( $ap_visual[ $key ] ) && is_numeric( $ap_visual[ $key ] ) && abs( (float) $ap_visual[ $key ] ) <= 2000 ) {
		$ap_style[] = $var . ':' . round( (float) $ap_visual[ $key ], 2 ) . 'px';
	}
}
$ap_scale = isset( $ap_visual['scale'] ) && is_array( $ap_visual['scale'] ) ? $ap_visual['scale'] : array();
foreach ( array( 754, 736, 717, 667, 640, 614, 480, 430, 400, 360 ) as $bp ) {
	$v = $ap_scale[ (string) $bp ] ?? null;
	if ( is_numeric( $v ) && (float) $v > 0 && (float) $v <= 2 ) {
		$ap_style[] = '--ap-sh-vs-' . $bp . ':' . round( (float) $v, 3 );
	}
}
?>
<section class="ap-solution-hero"<?php echo $ap_style ? ' style="' . esc_attr( implode( ';', $ap_style ) ) . '"' : ''; ?>>
	<div class="ap-solution-hero__orbs" aria-hidden="true"></div>
	<div class="ap-solution-hero__stage">
		<div class="ap-solution-hero__vwrap" aria-hidden="true">
			<div class="ap-solution-hero__visual">
				<?php if ( '' !== trim( (string) $props['photo'] ) ) : ?>
					<img class="ap-solution-hero__photo" src="<?php echo esc_url( $props['photo'] ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" decoding="async" fetchpriority="high" style="<?php echo esc_attr( $ap_px( $props['photo_box'], array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ) ); ?>" />
				<?php endif; ?>
				<?php foreach ( array_slice( $cards, 0, 6 ) as $i => $card ) : ?>
					<?php
					$slot  = in_array( $card['slot'] ?? '', $slots, true ) ? $card['slot'] : $slots[ $i ];
					$flo   = implode( ' ', array_filter( preg_split( '/\s+/', (string) ( $card['flo_class'] ?? '' ) ), static function ( $c ) { return (bool) preg_match( '/^[a-z][a-z0-9_-]*$/i', $c ); } ) );
					$class = 'epx-hv epx-hv--' . $slot . ( ! empty( $card['below'] ) ? ' epx-hv--below' : '' );
					?>
					<div class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $ap_px( $card, array( 'x' => 'left', 'y' => 'top', 'w' => 'width' ) ) ); ?>">
						<div class="epx-hv__rev">
							<div class="<?php echo esc_attr( trim( 'epx-hv__flo ' . $flo ) ); ?>"><?php echo ap_ui_markup( $card['markup'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_markup(). ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="ap-solution-hero__veil" aria-hidden="true"></div>
		<div class="ap-solution-hero__content">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-solution-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="ap-solution-hero__h1"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-solution-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<div class="ap-solution-hero__cta">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="epx-btn epx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $badges ) : ?>
					<span class="ap-solution-hero__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php
							// Live sizes some marks off their natural size (55x70 for a 48x60 file).
							$ap_dims = '';
							foreach ( array( 'w' => 'width', 'h' => 'height' ) as $key => $attr ) {
								if ( isset( $badge[ $key ] ) && is_numeric( $badge[ $key ] ) && (int) $badge[ $key ] > 0 && (int) $badge[ $key ] <= 400 ) {
									$ap_dims .= ' ' . $attr . '="' . (int) $badge[ $key ] . '"';
								}
							}
							?>
							<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>"<?php echo $ap_dims; // phpcs:ignore WordPress.Security.EscapeOutput -- integers only. ?> decoding="async" />
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $feats ) : ?>
			<div class="ap-solution-hero__ticker">
				<div class="ap-solution-hero__strip">
					<?php foreach ( array( false, true ) as $dup ) : ?>
						<?php foreach ( $feats as $feat ) : ?>
							<span class="ap-solution-hero__pill<?php echo $dup ? ' ap-solution-hero__pill--dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_icon( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised or escaped in the helper. ?><?php echo esc_html( $feat['label'] ); ?></span>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
