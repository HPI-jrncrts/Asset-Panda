<?php
/**
 * link-cards — the picker grid of linked image cards.
 *
 * Matches the live #ap-gov-picker. Each card is a single link; the photo is a
 * washed-out backdrop cropped by image_top, as on live.
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

$items = array_values( array_filter( is_array( $props['items'] ) ? $props['items'] : array(), static function ( $i ) { return is_array( $i ) && '' !== trim( (string) ( $i['title'] ?? '' ) ); } ) );
if ( empty( $items ) ) {
	return;
}
$arrow = ap_ui_asset_url( 'link-cards', 'icon-arrow.svg' );
?>
<?php $ap_plain = ! array_filter( $items, static function ( $i ) { return ! empty( $i['image'] ); } ); ?>
<section class="ap-link-cards<?php echo 3 === count( $items ) ? ' ap-link-cards--thirds' : ''; ?><?php echo $ap_plain ? ' ap-link-cards--plain' : ''; ?>">
	<div class="apg-pick-orb apg-pick-orb-green"></div>
	<div class="apg-pick-orb apg-pick-orb-indigo"></div>
	<div class="apg-pick-head">
		<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
			<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h2 class="apg-h2"><?php echo $ap_accent( $props['title'], $props['title_accent'], 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
	</div>
	<div class="apg-pick-grid">
		<?php foreach ( $items as $item ) : ?>
			<?php $url = trim( (string) ( $item['url'] ?? '' ) ); ?>
			<?php if ( '' !== $url ) : ?>
				<a class="apg-pick-card" href="<?php echo esc_url( $url ); ?>">
			<?php else : ?>
				<div class="apg-pick-card">
			<?php endif; ?>
				<?php if ( ! empty( $item['image'] ) ) : ?>
					<span class="apg-pick-bg"><img src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy" decoding="async" style="<?php echo esc_attr( $ap_pct( $item, array( 'image_top' => 'top' ) ) ); ?>" /></span>
				<?php endif; ?>
				<span class="apg-pick-fade"></span>
				<span class="apg-pick-body">
					<?php echo $ap_icon( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
					<span class="apg-pick-title"><?php echo esc_html( $item['title'] ); ?></span>
					<?php if ( ! empty( $item['body'] ) ) : ?>
						<span class="apg-pick-desc"><?php echo esc_html( $item['body'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $item['link_label'] ) ) : ?>
						<span class="apg-pick-cta"><?php echo esc_html( $item['link_label'] ); ?><img src="<?php echo esc_url( $arrow ); ?>" alt="" /></span>
					<?php endif; ?>
				</span>
			<?php if ( '' !== $url ) : ?>
				</a>
			<?php else : ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</section>
