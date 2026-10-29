<?php
/**
 * media-split — copy beside a composed scene.
 *
 * Matches one row of the live #ap-gov-intel. Scene layers are placed from
 * numbers; glow colours are validated as rgb()/rgba() before use.
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

$images = array_values( array_filter( is_array( $props['images'] ) ? $props['images'] : array(), static function ( $i ) { return is_array( $i ) && ! empty( $i['image'] ); } ) );
$glows  = array_values( array_filter( is_array( $props['glows'] ) ? $props['glows'] : array(), static function ( $g ) { return is_array( $g ) && preg_match( '/^rgba?\(\s*[\d.\s,%]+\)$/i', (string) ( $g['color'] ?? '' ) ); } ) );
$links  = array_values( array_filter( is_array( $props['links'] ) ? $props['links'] : array(), static function ( $l ) { return is_array( $l ) && ! empty( $l['label'] ) && ! empty( $l['url'] ); } ) );
// Labels pinned over the scene, and a sub-heading plus bullet list in the copy
// column (the help desk page's "a faster path to the answer" row).
$chips   = array_values( array_filter( is_array( $props['chips'] ) ? $props['chips'] : array(), static function ( $c ) { return is_array( $c ) && '' !== trim( (string) ( $c['label'] ?? '' ) ); } ) );
$bullets = array_values( array_filter( array_map( static function ( $b ) { return trim( (string) $b ); }, is_array( $props['bullets'] ) ? $props['bullets'] : array() ) ) );
$arrow  = ap_ui_asset_url( 'media-split', 'icon-arrow.svg' );

$scene_html = '';
if ( $images || $glows ) {
	ob_start();
	?>
	<div class="apg-int-scene" aria-hidden="true">
		<?php foreach ( $glows as $glow ) : ?>
			<span class="apg-glow" style="<?php echo esc_attr( $ap_pct( $glow, array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ) . ';background:radial-gradient(circle,' . $glow['color'] . ' 0%,rgba(255,255,255,0) 70%)' ); ?>"></span>
		<?php endforeach; ?>
		<?php foreach ( $images as $image ) : ?>
			<img class="<?php echo esc_attr( trim( ( ! empty( $image['float'] ) ? 'apg-float ' : '' ) . ( ! empty( $image['crop'] ) ? 'apg-tile-crop' : '' ) ) ); ?>" src="<?php echo esc_url( $image['image'] ); ?>" alt="" loading="lazy" decoding="async" style="<?php echo esc_attr( $ap_pct( $image, array( 'x' => 'left', 'y' => 'top', 'w' => 'width' ) ) ); ?>" />
		<?php endforeach; ?>
		<?php foreach ( $chips as $chip ) : ?>
			<span class="apg-int-chip" style="<?php echo esc_attr( $ap_pct( $chip, array( 'x' => 'left', 'y' => 'top', 'w' => 'width' ) ) ); ?>"><?php echo $ap_icon( $chip ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?><span><?php echo esc_html( $chip['label'] ); ?></span></span>
		<?php endforeach; ?>
	</div>
	<?php
	$scene_html = ob_get_clean();
}
?>
<?php
$scene_vars = array();
foreach ( array( 'scene_width' => '--apg-scene-w', 'scene_height' => '--apg-scene-h', 'pad_top' => '--apg-pad-t', 'pad_bottom' => '--apg-pad-b' ) as $prop => $var ) {
	if ( is_numeric( $props[ $prop ] ) && $props[ $prop ] > 0 ) {
		$scene_vars[] = $var . ':' . round( (float) $props[ $prop ] ) . 'px';
	}
}
?>
<section class="ap-media-split<?php echo '900' === (string) $props['stack'] ? ' ap-media-split--stack-900' : ''; ?>"<?php echo $scene_vars ? ' style="' . esc_attr( implode( ';', $scene_vars ) ) . '"' : ''; ?>>
	<?php if ( $props['heading_above'] ) : ?>
		<div class="apg-int-head">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="apg-h2"><?php echo $ap_accent( $props['title'], $props['title_accent'], 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
		</div>
	<?php endif; ?>
	<div class="apg-int-row<?php echo $props['reverse'] ? ' apg-int-row-rev' : ''; ?>">
		<?php echo $props['reverse'] ? '' : $scene_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built above with escaping. ?>
		<div class="apg-int-copy">
			<?php if ( ! $props['heading_above'] ) : ?>
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 class="apg-h2"><?php echo $ap_accent( $props['title'], $props['title_accent'], 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $props['sub_title'] ) ) : ?>
				<h3 class="apg-int-h3"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['sub_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h3>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $props['body'] ) ) : ?>
				<p class="apg-int-body"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
			<?php if ( $bullets ) : ?>
				<ul class="apg-int-bullets">
					<?php foreach ( $bullets as $bullet ) : ?>
						<li><?php echo ap_ui_brandmarks( esc_html( $bullet ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $links ) : ?>
				<div class="apg-int-ctas">
					<?php foreach ( $links as $link ) : ?>
						<a class="apg-int-cta" href="<?php echo esc_url( $link['url'] ); ?>"><span><?php echo ap_ui_brandmarks( esc_html( $link['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></span><img src="<?php echo esc_url( $arrow ); ?>" alt="" /></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php echo $props['reverse'] ? $scene_html : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built above with escaping. ?>
	</div>
</section>
