<?php
/**
 * platform-stack — "From first record to final audit."
 *
 * Matches the live homepage #ap-platform-stack-v3 section: the markup below is
 * live's own (class for class), so the ported sheets in style.css apply as they
 * do there. Cards keep live's ids (ap-card-*): the index links to them and live
 * keys some per-card colour to them.
 *
 * behaviour.js marks the index item for the card being read (all widths) and,
 * on phones, fills that card (.is-mobile-scroll-active), as live's script does.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones      = array( 'custom', 'gold', 'pink', 'green' );
$link_tones = array( 'purple', 'gold', 'pink', 'green' );

// Live's per-tone default link colour, used when an item gives none.
$link_for = array(
	'custom' => 'purple',
	'gold'   => 'gold',
	'pink'   => 'pink',
	'green'  => 'green',
);

$px = static function ( $value ) {
	return is_numeric( $value ) && $value > 0 && $value < 10000 ? (int) $value : 0;
};

// Only plain http(s) or root-relative URLs reach the style attribute.
$css_url = static function ( $value ) {
	$value = trim( (string) $value );
	return preg_match( '#^(?:https?://|/)[^\s"\'()\\\\<>]+$#i', $value ) ? $value : '';
};

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$n      = count( $items ) + 1;
	$tone   = trim( (string) ( $item['tone'] ?? '' ) );
	$tone   = in_array( $tone, $tones, true ) ? $tone : 'custom';
	$ltone  = trim( (string) ( $item['link_tone'] ?? '' ) );
	$anchor = trim( (string) ( $item['anchor'] ?? '' ) );
	$slot   = isset( $item['hover_slot'] ) ? (int) $item['hover_slot'] : 0;

	$items[] = array(
		'anchor'        => preg_match( '/^[a-z][a-z0-9-]{0,60}$/', $anchor ) ? $anchor : 'ap-card-' . $n,
		'tone'          => $tone,
		'link_tone'     => in_array( $ltone, $link_tones, true ) ? $ltone : $link_for[ $tone ],
		'visual_custom' => ! empty( $item['visual_custom'] ),
		'nav_label'     => trim( (string) ( $item['nav_label'] ?? '' ) ),
		'title'         => trim( (string) $item['title'] ),
		'body'          => trim( (string) ( $item['body'] ?? '' ) ),
		'link_label'    => trim( (string) ( $item['link_label'] ?? '' ) ),
		'link_url'      => trim( (string) ( $item['link_url'] ?? '' ) ),
		'image'         => trim( (string) ( $item['image'] ?? '' ) ),
		'image_alt'     => (string) ( $item['image_alt'] ?? '' ),
		'image_w'       => $px( $item['image_w'] ?? 0 ),
		'image_h'       => $px( $item['image_h'] ?? 0 ),
		'hover'         => trim( (string) ( $item['image_hover'] ?? '' ) ),
		'hover_alt'     => (string) ( $item['image_hover_alt'] ?? '' ),
		'hover_w'       => $px( $item['hover_w'] ?? 0 ),
		'hover_h'       => $px( $item['hover_h'] ?? 0 ),
		'hover_slot'    => ( $slot >= 1 && $slot <= 7 ) ? $slot : min( $n, 7 ),
		'hover_bg'      => $css_url( $item['hover_bg'] ?? '' ),
	);
}

if ( ! $items ) {
	return;
}

$eyebrow = trim( (string) $props['eyebrow'] );
$intro   = trim( (string) $props['intro'] );

// "width/height" attributes when both are known, as live sets them.
$dims = static function ( $w, $h ) {
	return ( $w && $h ) ? ' width="' . $w . '" height="' . $h . '"' : '';
};
?>
<section class="ap-pstack" data-ap-pstack>
	<div class="ap-platform-wrap">
		<div class="ap-platform-head">
			<div class="ap-platform-copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php if ( '' !== $intro ) : ?>
					<p class="ap-intro"><?php echo ap_ui_brandmarks( esc_html( $intro ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="ap-platform-body">
			<aside class="ap-platform-rail">
				<nav class="ap-platform-nav" aria-label="<?php echo esc_attr( $props['nav_title'] ); ?>">
					<?php foreach ( $items as $i => $item ) : ?>
						<a href="#<?php echo esc_attr( $item['anchor'] ); ?>" class="ap-nav-item<?php echo 0 === $i ? ' is-active' : ''; ?>" data-ap-pstack-link="<?php echo esc_attr( (string) $i ); ?>"><span class="ap-dot"></span><span class="ap-label"><?php echo ap_ui_brandmarks( esc_html( '' !== $item['nav_label'] ? $item['nav_label'] : $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span></a>
					<?php endforeach; ?>
				</nav>
			</aside>

			<div class="ap-platform-cards">
				<?php foreach ( $items as $i => $item ) : ?>
					<article id="<?php echo esc_attr( $item['anchor'] ); ?>" class="ap-stack-card ap-card ap-card-<?php echo esc_attr( $item['tone'] ); ?>" data-ap-pstack-panel="<?php echo esc_attr( (string) $i ); ?>"<?php echo '' !== $item['hover_bg'] ? ' style="' . esc_attr( '--ap-pstack-hover-bg:url("' . $item['hover_bg'] . '")' ) . '"' : ''; ?>>
						<div class="ap-card-copy">
							<h3><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
							<?php if ( '' !== $item['body'] ) : ?>
								<p><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
							<?php endif; ?>
							<?php if ( '' !== $item['link_label'] && '' !== $item['link_url'] ) : ?>
								<a href="<?php echo esc_url( $item['link_url'] ); ?>" class="ap-card-link ap-link-<?php echo esc_attr( $item['link_tone'] ); ?>"><span><?php echo ap_ui_brandmarks( esc_html( $item['link_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span><span class="ap-arrow" aria-hidden="true">&rarr;</span></a>
							<?php endif; ?>
						</div>
						<?php if ( '' !== $item['image'] || '' !== $item['hover'] ) : ?>
							<div class="ap-card-visual<?php echo $item['visual_custom'] ? ' ap-card-visual-custom' : ''; ?>">
								<?php if ( '' !== $item['image'] ) : ?>
									<div class="ap-screen-base"><img<?php echo $dims( $item['image_w'], $item['image_h'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> loading="lazy" decoding="async" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ); ?>" /></div>
								<?php endif; ?>
								<?php if ( '' !== $item['hover'] ) : ?>
									<div class="ap-hover-art ap-hover-card-<?php echo esc_attr( (string) $item['hover_slot'] ); ?>"><img<?php echo $dims( $item['hover_w'], $item['hover_h'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> loading="lazy" decoding="async" src="<?php echo esc_url( $item['hover'] ); ?>" alt="<?php echo esc_attr( $item['hover_alt'] ); ?>" /></div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
