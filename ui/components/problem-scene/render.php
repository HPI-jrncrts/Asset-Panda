<?php
/**
 * problem-scene — the hub problem section.
 *
 * Matches the live #ap-gov-problem: two photos and four or five chips on desktop; below
 * 1025px the photos stack and the chips flow as a wrapped row.
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

$tones  = array( 'blue', 'red', 'green', 'yellow' );

// A chip's desktop placement, when the page overrides the default slot. Only
// lengths, auto, and calc()/min()/max() over lengths get through.
$ap_place = static function ( $chip ) {
	$out = array();
	foreach ( array( 'left', 'right', 'top' ) as $side ) {
		$value = trim( (string) ( $chip[ $side ] ?? '' ) );
		if ( '' !== $value && preg_match( '#^(auto|-?[\d.]+(px|%|vw)?|(calc|min|max)\([-\d.%pxvw +*/,()]+\))$#', $value ) ) {
			$out[] = $side . ':' . $value;
		}
	}
	return implode( ';', $out );
};
$images = array_values( array_filter( is_array( $props['images'] ) ? $props['images'] : array(), static function ( $i ) { return is_array( $i ) && ! empty( $i['image'] ); } ) );
$chips  = array_values( array_filter( is_array( $props['chips'] ) ? $props['chips'] : array(), static function ( $c ) { return is_array( $c ) && '' !== trim( (string) ( $c['text'] ?? '' ) ); } ) );
$bg     = trim( (string) $props['background'] );
?>
<section class="ap-problem-scene"<?php echo '' !== $bg ? ' style="' . esc_attr( '--apg-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
	<div class="apg-prob-head">
		<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
			<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h2 class="apg-h2"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></h2>
		<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
			<p class="apg-prob-lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $images || $chips ) : ?>
		<div class="apg-prob-scene"<?php echo is_numeric( $props['scene_height'] ) && $props['scene_height'] > 0 ? ' style="' . esc_attr( '--apg-scene-h:' . round( (float) $props['scene_height'] ) . 'px' ) . '"' : ''; ?>>
			<?php foreach ( array_slice( $images, 0, 2 ) as $i => $image ) : ?>
				<?php
				// A page may place and size its own photo (the epx pages use one
				// large photo instead of the hubs' overlapping pair).
				$geo = array();
				foreach ( array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) as $key => $property ) {
					if ( isset( $image[ $key ] ) && is_numeric( $image[ $key ] ) ) {
						$geo[] = $property . ':' . round( (float) $image[ $key ], 3 ) . '%';
					}
				}
				?>
				<img class="apg-prob-img apg-prob-img-<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" src="<?php echo esc_url( $image['image'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>" loading="lazy" decoding="async"<?php echo $geo ? ' style="' . esc_attr( implode( ';', $geo ) . ';transform:none' ) . '"' : ''; ?> />
			<?php endforeach; ?>
			<div class="apg-prob-chips-flow">
				<?php foreach ( array_slice( $chips, 0, 5 ) as $i => $chip ) : ?>
					<?php $tone = in_array( $chip['tone'] ?? '', $tones, true ) ? $chip['tone'] : $tones[ $i % 4 ]; ?>
					<?php $place = $ap_place( $chip ); ?>
					<div class="apg-prob-chip apg-chip-<?php echo esc_attr( $tone ); ?>"<?php echo '' !== $place ? ' style="' . esc_attr( $place ) . '"' : ''; ?>>
						<?php echo $ap_icon( $chip ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
						<span><?php echo esc_html( $chip['text'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) $props['cta_title'] ) || '' !== trim( (string) $props['cta_body'] ) ) : ?>
		<div class="apg-mini-cta">
			<div class="apg-mini-left">
				<?php if ( '' !== trim( (string) $props['cta_icon'] ) ) : ?>
					<img src="<?php echo esc_url( $props['cta_icon'] ); ?>" alt="" loading="lazy" decoding="async" />
				<?php endif; ?>
				<div>
					<p class="apg-mini-title"><?php echo esc_html( $props['cta_title'] ); ?></p>
					<p class="apg-mini-body"><?php echo esc_html( $props['cta_body'] ); ?></p>
				</div>
			</div>
			<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
				<a class="apg-btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
