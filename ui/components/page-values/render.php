<?php
/**
 * page-values — the "Our People" section of the 2026 company pages (Our Company, Figma 7455:103802).
 *
 * Header row (intro left, arrow link right), then icon points beside a photo; below 1001px everything stacks
 * and the link moves under the intro. Copy takes the page-* light markup: a blank line starts a paragraph,
 * [label](url) makes a link and **double asterisks** bold.
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
$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( is_array( $item ) && ( '' !== trim( (string) ( $item['title'] ?? '' ) ) || '' !== trim( (string) ( $item['text'] ?? '' ) ) ) ) {
		$items[] = array(
			'icon'  => trim( (string) ( $item['icon'] ?? '' ) ),
			'title' => trim( (string) ( $item['title'] ?? '' ) ),
			'text'  => trim( (string) ( $item['text'] ?? '' ) ),
		);
	}
}
if ( '' === $title || ! $items ) {
	return;
}

$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$accent = trim( (string) $props['title_accent'] );

$title_html = esc_html( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr( $title_html, 0, $at ) . '<span class="ap-page-values__accent">' . $needle . '</span>' . substr( $title_html, $at + strlen( $needle ) );
	}
}
$title_html = ap_ui_brandmarks( nl2br( $title_html, false ) );

$link_label = trim( (string) $props['link_label'] );
$link_url   = trim( (string) $props['link_url'] );
$image      = trim( (string) $props['image'] );
$img_w      = max( 0, (int) $props['image_width'] );
$img_h      = max( 0, (int) $props['image_height'] );
$arrow      = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-page-values"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-values__wrap">
		<div class="ap-page-values__head">
			<div class="ap-page-values__intro">
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="ap-page-values__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 class="ap-page-values__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
				<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
					<div class="ap-page-values__lede"><?php echo $ap_paras( $props['lede'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $link_label && '' !== $link_url ) : ?>
				<a class="ap-page-values__link" href="<?php echo esc_url( $link_url, $protocols ); ?>"><span><?php echo esc_html( $link_label ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
			<?php endif; ?>
		</div>
		<div class="ap-page-values__row<?php echo '' === $image ? ' ap-page-values__row--solo' : ''; ?>">
			<ul class="ap-page-values__items">
				<?php foreach ( $items as $i => $item ) : ?>
					<li class="ap-page-values__item" data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) min( $i, 4 ) ); ?>">
						<?php if ( '' !== $item['icon'] ) : ?>
							<img class="ap-page-values__icon" src="<?php echo esc_url( $item['icon'] ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async" />
						<?php endif; ?>
						<div class="ap-page-values__item-text">
							<?php if ( '' !== $item['title'] ) : ?>
								<h3 class="ap-page-values__item-title"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
							<?php endif; ?>
							<?php if ( '' !== $item['text'] ) : ?>
								<p class="ap-page-values__item-body"><?php echo $ap_rich( $item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( '' !== $image ) : ?>
				<div class="ap-page-values__media" data-ap-reveal="left">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
