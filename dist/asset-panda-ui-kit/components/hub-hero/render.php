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
	return '' !== $icon ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $icon ) . '" alt="" width="24" height="24" decoding="async" />' : '';
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
// Phone visual box (live sets it per page on .apg-hero-visual below 768px).
if ( preg_match( '#^\s*(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)\s*$#', (string) $props['mobile_ratio'], $m ) && '393/378' !== $m[1] . '/' . $m[2] ) {
	$vars[] = '--apg-mratio:' . $m[1] . '/' . $m[2];
}
// The photo group's bounds on the 1440x640 art canvas, in canvas px (unitless):
// the stylesheet right-anchors the art by them on laptops and scales the group to
// the width under the copy on tablets and phones. Numbers only.
$ap_x0 = 1440;
$ap_x1 = 0;
$ap_y0 = 640;
foreach ( array_slice( $photos, 0, 3 ) as $photo ) {
	if ( ! isset( $photo['x'], $photo['y'], $photo['w'] ) || ! is_numeric( $photo['x'] ) || ! is_numeric( $photo['y'] ) || ! is_numeric( $photo['w'] ) ) {
		continue;
	}
	$ap_x0 = min( $ap_x0, (float) $photo['x'] * 14.4 );
	$ap_x1 = max( $ap_x1, ( (float) $photo['x'] + (float) $photo['w'] ) * 14.4 );
	$ap_y0 = min( $ap_y0, (float) $photo['y'] * 6.4 );
}
if ( $ap_x1 > $ap_x0 + 40 ) {
	$ap_x0  = max( 0, $ap_x0 - 16 );
	$ap_x1  = min( 1440, $ap_x1 + 16 );
	$ap_y0  = max( 0, min( 600, $ap_y0 - 8 ) );
	$vars[] = '--apg-x0:' . round( $ap_x0 );
	$vars[] = '--apg-x1:' . round( $ap_x1 );
	$vars[] = '--apg-y0:' . round( $ap_y0 );
	$vars[] = '--apg-rw:' . round( $ap_x1 - $ap_x0 );
}
$root_st = implode( ';', $vars );

// The backdrop is the first-screen image: ask for it before the stylesheet is parsed.
if ( 'eduhub' !== $props['variant'] && '' !== trim( (string) $props['background'] ) && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $props['background'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() in the loader.
}

// Live sizes each compliance badge by kind (.apg-badge-soc2 / -govramp / -hipaa).
$ap_badge_class = static function ( $badge ) {
	$file = strtolower( (string) wp_parse_url( (string) $badge['image'], PHP_URL_PATH ) );
	foreach ( array( 'soc2', 'govramp', 'hipaa' ) as $kind ) {
		if ( false !== strpos( basename( $file ), $kind ) ) {
			return 'apg-badge-' . $kind;
		}
	}
	return '';
};

/*
 * Education hub: live #ap-eduhub-hero, ported whole with its own class names
 * (.apf-*): the backdrop sits on the wrap, the copy over the stage (which keeps
 * a 520px floor from 768 to 1100px), the chips in a ruled band below; on phones
 * the copy stacks over the phone backdrop. Photos are placed from the props.
 */
if ( 'eduhub' === $props['variant'] ) :
	$ap_edu_vars = array();
	if ( '' !== trim( (string) $props['background'] ) ) {
		$ap_edu_vars[] = '--apf-bg:url("' . esc_url_raw( trim( (string) $props['background'] ) ) . '")';
	}
	if ( '' !== $mobile ) {
		$ap_edu_vars[] = '--apf-mbg:url("' . esc_url_raw( $mobile ) . '")';
	}
	$ap_feat = static function ( $feat ) use ( $ap_icon ) {
		$icon = trim( (string) ( $feat['icon'] ?? '' ) );
		if ( ! empty( $feat['icon_mask'] ) && '' !== $icon ) {
			$mask = 'url("' . esc_url_raw( $icon ) . '") center/contain no-repeat';
			return '<span class="apf-feat-mask" aria-hidden="true" style="' . esc_attr( '-webkit-mask:' . $mask . ';mask:' . $mask ) . '"></span>';
		}
		return $ap_icon( $feat );
	};
	?>
<section class="ap-hub-hero ap-hub-hero--eduhub"<?php echo $ap_edu_vars ? ' style="' . esc_attr( implode( ';', $ap_edu_vars ) ) . '"' : ''; ?>>
	<div class="apf-wrap">
		<div class="apf-stage">
			<?php foreach ( array_slice( $photos, 0, 3 ) as $i => $photo ) : ?>
				<div class="apf-photo apf-photo<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" style="<?php echo esc_attr( $ap_pct( $photo, array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ) ); ?>">
					<img src="<?php echo esc_url( $photo['image'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ?? '' ); ?>" style="<?php echo esc_attr( $ap_pct( $photo, array( 'ix' => 'left', 'iy' => 'top', 'iw' => 'width', 'ih' => 'height' ) ) ); ?>" decoding="async" />
				</div>
			<?php endforeach; ?>
			<div class="apf-veil"></div>
		</div>
		<div class="apf-content">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="apf-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="apf-headline"><?php echo $ap_accent( $title, $props['title_accent'], 'apf-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="apf-body"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
			<div class="apf-cta-row">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="ap-btn ap-btn-primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $badges ) : ?>
					<div class="apf-badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php $ap_bc = str_replace( 'apg-', 'apf-', $ap_badge_class( $badge ) ); ?>
							<img<?php echo '' !== $ap_bc ? ' class="' . esc_attr( $ap_bc ) . '"' : ''; ?> src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" />
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php if ( $feats ) : ?>
		<div class="ap-hero-feats-band">
			<div class="ap-hero-feats">
				<?php foreach ( array( false, true ) as $dup ) : ?>
					<?php foreach ( $feats as $feat ) : ?>
						<div class="ap-hero-feat<?php echo $dup ? ' apf-dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_feat( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?><span><?php echo esc_html( $feat['label'] ); ?></span></div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</section>
	<?php
	return;
endif;
?>
<section class="ap-hub-hero"<?php echo '' !== $root_st ? ' style="' . esc_attr( $root_st ) . '"' : ''; ?>>
	<div class="apg-hero-stage">
		<div class="apg-hero-visual">
			<?php if ( '' !== trim( (string) $props['background'] ) ) : ?>
				<img class="apg-hero-bg" src="<?php echo esc_url( $props['background'] ); ?>" alt="" width="1440" height="640" decoding="async" fetchpriority="high" />
			<?php endif; ?>
			<?php foreach ( array_slice( $photos, 0, 3 ) as $i => $photo ) : ?>
				<?php $box = $ap_pct( $photo, array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ); ?>
				<?php if ( isset( $photo['iw'] ) && is_numeric( $photo['iw'] ) ) : ?>
					<div class="apg-hero-p3w" style="<?php echo esc_attr( $box ); ?>">
						<img src="<?php echo esc_url( $photo['image'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ?? '' ); ?>" style="<?php echo esc_attr( $ap_pct( $photo, array( 'ix' => 'left', 'iy' => 'top', 'iw' => 'width', 'ih' => 'height' ) ) ); ?>" decoding="async" />
					</div>
				<?php else : ?>
					<img class="apg-hero-photo apg-hero-p<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" src="<?php echo esc_url( $photo['image'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ?? '' ); ?>" style="<?php echo esc_attr( $box ); ?>"<?php echo isset( $photo['w'], $photo['h'] ) && is_numeric( $photo['w'] ) && is_numeric( $photo['h'] ) ? ' width="' . (int) round( (float) $photo['w'] * 14.4 ) . '" height="' . (int) round( (float) $photo['h'] * 6.4 ) . '"' : ''; ?> decoding="async" />
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
							<?php $ap_bc = $ap_badge_class( $badge ); ?>
							<img<?php echo '' !== $ap_bc ? ' class="' . esc_attr( $ap_bc ) . '"' : ''; ?> src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" />
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
