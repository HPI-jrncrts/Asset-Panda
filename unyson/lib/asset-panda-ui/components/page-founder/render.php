<?php
/**
 * page-founder — the founder section of the 2026 company pages (Our Company, Figma 7460:104311).
 *
 * The subtitle stays inside the h2 (as a block line), so the heading reads "Meet Rex Kurzius, Founder and CEO" to
 * search and screen readers, as the content doc's heading does, while drawing like the design's two lines.
 * Copy takes the page-* light markup: a blank line starts a paragraph, [label](url) makes a link, **bold**.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protocols = array( 'http', 'https', 'mailto', 'tel' );

$ap_text  = static function ( $text ) {
	$html = nl2br( esc_html( $text ), false );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};
$ap_rich  = static function ( $text ) use ( $ap_text, $protocols ) {
	$text = trim( (string) $text );
	$out  = '';
	$pos  = 0;
	if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$out .= $ap_text( substr( $text, $pos, $hit[0][1] - $pos ) );
			$url  = esc_url( $hit[2][0], $protocols );
			$out .= '' !== $url ? '<a href="' . $url . '">' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};
$ap_paras = static function ( $text ) use ( $ap_rich ) {
	$out = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $para ) {
		if ( '' !== trim( $para ) ) {
			$out .= '<p>' . $ap_rich( trim( $para ) ) . '</p>';
		}
	}
	return $out;
};

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$anchor     = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$subtitle   = trim( (string) $props['subtitle'] );
$link_label = trim( (string) $props['link_label'] );
$link_url   = trim( (string) $props['link_url'] );
$image      = trim( (string) $props['image'] );
$img_w      = max( 0, (int) $props['image_width'] );
$img_h      = max( 0, (int) $props['image_height'] );
$icons      = array(
	'linkedin' => '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#0A66C2" d="M19.5 3h-15A1.5 1.5 0 0 0 3 4.5v15A1.5 1.5 0 0 0 4.5 21h15a1.5 1.5 0 0 0 1.5-1.5v-15A1.5 1.5 0 0 0 19.5 3ZM8.4 18.3H5.7V9.7h2.7v8.6ZM7 8.5a1.6 1.6 0 1 1 0-3.1 1.6 1.6 0 0 1 0 3.1Zm11.3 9.8h-2.7v-4.2c0-1 0-2.3-1.4-2.3s-1.6 1.1-1.6 2.2v4.3H10V9.7h2.6v1.2a2.8 2.8 0 0 1 2.5-1.4c2.7 0 3.2 1.8 3.2 4.1v4.7Z"/></svg>',
);
$icon       = $icons[ (string) $props['link_icon'] ] ?? '';
?>
<section class="ap-page-founder<?php echo '' === $image ? ' ap-page-founder--solo' : ''; ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-founder__orb" aria-hidden="true"></div>
	<div class="ap-page-founder__wrap">
		<?php if ( '' !== $image ) : ?>
			<div class="ap-page-founder__media" data-ap-reveal>
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> loading="lazy" decoding="async" />
			</div>
		<?php endif; ?>
		<div class="ap-page-founder__copy">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-page-founder__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-page-founder__title"><?php echo esc_html( $title ); ?><?php if ( '' !== $subtitle ) : ?><span class="ap-page-founder__sep">, </span><span class="ap-page-founder__subtitle"><?php echo esc_html( $subtitle ); ?></span><?php endif; ?></h2>
			<?php if ( '' !== trim( (string) $props['body'] ) ) : ?>
				<div class="ap-page-founder__body"><?php echo $ap_paras( $props['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
			<?php endif; ?>
			<?php if ( '' !== $link_label && '' !== $link_url ) : ?>
				<a class="ap-page-founder__link" href="<?php echo esc_url( $link_url, $protocols ); ?>"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo esc_html( $link_label ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>
</section>
