<?php
/**
 * hub-hero — the hero on the industry hub pages.
 *
 * Matches the live #ap-gov-hero: an aspect-locked stage (1440:640) with the
 * backdrop, up to three photos placed as percentages, a white veil, the copy,
 * and a feature strip that becomes a marquee on phones. On phones the photos
 * give way to a single composite image (mobile_background).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// "left:12.5%;top:40%" from whichever geometry keys hold numbers.
$ap_pct = static function ( $geo, array $map ) {
	$out = array();
	foreach ( $map as $key => $property ) {
		if ( is_array( $geo ) && isset( $geo[ $key ] ) && is_numeric( $geo[ $key ] ) ) {
			$out[] = $property . ':' . round( (float) $geo[ $key ], 3 ) . '%';
		}
	}
	return implode( ';', $out );
};

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent, $class ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="' . esc_attr( $class ) . '">' . $escaped . '</span>', $html );
	}
	return $html;
};

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item, $class = '' ) {
	$svg = ap_ui_svg( $item['icon_svg'] ?? '' );
	if ( '' !== $svg ) {
		return '<span class="' . esc_attr( trim( 'ap-inline-icon ' . $class ) ) . '" aria-hidden="true">' . $svg . '</span>';
	}
	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	return '' !== $icon ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $icon ) . '" alt="" loading="lazy" decoding="async" />' : '';
};

$title   = trim( (string) $props['title'] );
$photos  = array_values( array_filter( is_array( $props['photos'] ) ? $props['photos'] : array(), static function ( $p ) { return is_array( $p ) && ! empty( $p['image'] ); } ) );
$badges  = array_values( array_filter( is_array( $props['badges'] ) ? $props['badges'] : array(), static function ( $b ) { return is_array( $b ) && ! empty( $b['image'] ); } ) );
$feats   = array_values( array_filter( is_array( $props['features'] ) ? $props['features'] : array(), static function ( $f ) { return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) ); } ) );
$mobile  = trim( (string) $props['mobile_background'] );
$vars    = array();
if ( '' !== $mobile ) {
	$vars[] = '--apg-mbg:url("' . esc_url_raw( $mobile ) . '")';
}
// Only "1440/560"-shaped ratios; anything else keeps the stylesheet default.
if ( preg_match( '#^\s*(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)\s*$#', (string) $props['ratio'], $m ) ) {
	$vars[] = '--apg-ratio:' . $m[1] . '/' . $m[2];
}
if ( is_numeric( $props['veil_height'] ) && $props['veil_height'] > 0 ) {
	$vars[] = '--apg-veil:' . round( (float) $props['veil_height'], 3 ) . '%';
}
if ( is_numeric( $props['content_top'] ) && $props['content_top'] > 0 ) {
	$vars[] = '--apg-ctop:' . round( (float) $props['content_top'], 3 ) . '%';
}
$root_st = implode( ';', $vars );
?>
<section class="ap-hub-hero"<?php echo '' !== $root_st ? ' style="' . esc_attr( $root_st ) . '"' : ''; ?>>
	<div class="apg-hero-stage">
		<div class="apg-hero-visual">
			<?php if ( '' !== trim( (string) $props['background'] ) ) : ?>
				<img class="apg-hero-bg" src="<?php echo esc_url( $props['background'] ); ?>" alt="" decoding="async" fetchpriority="high" />
			<?php endif; ?>
			<?php foreach ( array_slice( $photos, 0, 3 ) as $i => $photo ) : ?>
				<?php $box = $ap_pct( $photo, array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ); ?>
				<?php if ( isset( $photo['iw'] ) && is_numeric( $photo['iw'] ) ) : ?>
					<div class="apg-hero-p3w" style="<?php echo esc_attr( $box ); ?>">
						<img src="<?php echo esc_url( $photo['image'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ?? '' ); ?>" style="<?php echo esc_attr( $ap_pct( $photo, array( 'ix' => 'left', 'iy' => 'top', 'iw' => 'width', 'ih' => 'height' ) ) ); ?>" decoding="async" />
					</div>
				<?php else : ?>
					<img class="apg-hero-photo apg-hero-p<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" src="<?php echo esc_url( $photo['image'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ?? '' ); ?>" style="<?php echo esc_attr( $box ); ?>" decoding="async" />
				<?php endif; ?>
			<?php endforeach; ?>
			<div class="apg-hero-veil"></div>
		</div>

		<div class="apg-hero-in">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="apg-h1"><?php echo $ap_accent( $title, $props['title_accent'], 'apg-h1-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="apg-hero-body"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
			<div class="apg-cta-row">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="apg-btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $badges ) : ?>
					<div class="apg-badges">
						<?php foreach ( $badges as $badge ) : ?>
							<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" />
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<?php if ( $feats ) : ?>
		<div class="apg-feats<?php echo $props['features_band'] ? ' apg-feats--band' : ''; ?>">
				<div class="apg-feats-track">
					<?php foreach ( array( false, true ) as $dup ) : ?>
						<?php foreach ( $feats as $feat ) : ?>
							<div class="apg-feat<?php echo $dup ? ' apg-feat-dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>>
								<?php echo $ap_icon( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
								<span><?php echo esc_html( $feat['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</section>
