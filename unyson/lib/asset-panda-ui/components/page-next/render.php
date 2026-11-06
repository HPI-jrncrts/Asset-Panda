<?php
/**
 * page-next — the "What's Next" section of the 2026 company pages (Our Company, Figma 7460:105096).
 *
 * Each card is one link (the whole card is the target). Copy takes the page-* light markup: a blank line starts
 * a paragraph, [label](url) makes a link and **double asterisks** bold.
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
$cards = array();
foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( is_array( $card ) && '' !== trim( (string) ( $card['title'] ?? '' ) ) && '' !== trim( (string) ( $card['url'] ?? '' ) ) ) {
		$cards[] = array(
			'title' => trim( (string) $card['title'] ),
			'url'   => trim( (string) $card['url'] ),
			'icon'  => trim( (string) ( $card['icon'] ?? '' ) ),
		);
	}
}
if ( '' === $title || ! $cards ) {
	return;
}

$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$accent = trim( (string) $props['title_accent'] );

$title_html = esc_html( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr( $title_html, 0, $at ) . '<span class="ap-page-next__accent">' . $needle . '</span>' . substr( $title_html, $at + strlen( $needle ) );
	}
}
$title_html = ap_ui_brandmarks( nl2br( $title_html, false ) );
?>
<section class="ap-page-next"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-next__orbs" aria-hidden="true"></div>
	<div class="ap-page-next__wrap">
		<div class="ap-page-next__intro">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-page-next__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-page-next__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
			<?php if ( '' !== trim( (string) $props['body'] ) ) : ?>
				<div class="ap-page-next__body"><?php echo $ap_paras( $props['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
			<?php endif; ?>
		</div>
		<ul class="ap-page-next__cards">
			<?php foreach ( $cards as $i => $card ) : ?>
				<li data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) min( $i, 4 ) ); ?>">
					<a class="ap-page-next__card" href="<?php echo esc_url( $card['url'], $protocols ); ?>">
						<?php if ( '' !== $card['icon'] ) : ?>
							<img class="ap-page-next__icon" src="<?php echo esc_url( $card['icon'] ); ?>" alt="" width="28" height="28" loading="lazy" decoding="async" />
						<?php endif; ?>
						<span class="ap-page-next__card-title"><?php echo ap_ui_brandmarks( esc_html( $card['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
