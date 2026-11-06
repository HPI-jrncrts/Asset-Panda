<?php
/**
 * page-reach — the "Who We Serve" section of the 2026 company pages (Our Company, Figma 7450:97488).
 *
 * Copy left; on desktop the stat cards step in and out (the design's offsets) over a dotted world map, and
 * below 1001px they stack full width under the copy. Copy takes the page-* light markup: a blank line starts a
 * paragraph, [label](url) makes a link and **double asterisks** bold.
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

$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$accent = trim( (string) $props['title_accent'] );

$title_html = esc_html( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr( $title_html, 0, $at ) . '<span class="ap-page-reach__accent">' . $needle . '</span>' . substr( $title_html, $at + strlen( $needle ) );
	}
}
$title_html = ap_ui_brandmarks( nl2br( $title_html, false ) );

$links = array();
foreach ( is_array( $props['links'] ) ? $props['links'] : array() as $link ) {
	if ( is_array( $link ) && '' !== trim( (string) ( $link['label'] ?? '' ) ) && '' !== trim( (string) ( $link['url'] ?? '' ) ) ) {
		$links[] = array(
			'label' => trim( (string) $link['label'] ),
			'url'   => trim( (string) $link['url'] ),
		);
	}
}

$cards = array();
foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( is_array( $card ) && '' !== trim( (string) ( $card['title'] ?? '' ) ) ) {
		$cards[] = array(
			'icon'   => trim( (string) ( $card['icon'] ?? '' ) ),
			'kicker' => trim( (string) ( $card['kicker'] ?? '' ) ),
			'title'  => trim( (string) $card['title'] ),
			'text'   => trim( (string) ( $card['text'] ?? '' ) ),
		);
	}
}

$map       = trim( (string) $props['map'] );
$highlight = trim( (string) $props['highlight'] );
$foot      = trim( (string) $props['foot'] );
$arrow     = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-page-reach<?php echo $cards ? '' : ' ap-page-reach--solo'; ?><?php echo '' !== $foot ? ' ap-page-reach--foot' : ''; ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-reach__orbs" aria-hidden="true"></div>
	<div class="ap-page-reach__wrap">
		<div class="ap-page-reach__copy">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-page-reach__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-page-reach__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
			<?php if ( '' !== trim( (string) $props['body'] ) ) : ?>
				<div class="ap-page-reach__body"><?php echo $ap_paras( $props['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
			<?php endif; ?>
			<?php if ( '' !== $highlight ) : ?>
				<p class="ap-page-reach__highlight"><?php echo ap_ui_brandmarks( esc_html( $highlight ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
			<?php if ( '' !== trim( (string) $props['body_after'] ) ) : ?>
				<div class="ap-page-reach__body"><?php echo $ap_paras( $props['body_after'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
			<?php endif; ?>
			<?php if ( $links ) : ?>
				<ul class="ap-page-reach__links">
					<?php foreach ( $links as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'], $protocols ); ?>"><span><?php echo ap_ui_brandmarks( esc_html( $link['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( $cards ) : ?>
			<div class="ap-page-reach__visual">
				<?php if ( '' !== $map ) : ?>
					<img class="ap-page-reach__map" src="<?php echo esc_url( $map ); ?>" alt="" width="603" height="338" loading="lazy" decoding="async" aria-hidden="true" />
				<?php endif; ?>
				<ul class="ap-page-reach__cards">
					<?php foreach ( $cards as $i => $card ) : ?>
						<li class="ap-page-reach__card" data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) min( $i, 4 ) ); ?>">
							<?php if ( '' !== $card['icon'] ) : ?>
								<img class="ap-page-reach__icon" src="<?php echo esc_url( $card['icon'] ); ?>" alt="" width="88" height="88" loading="lazy" decoding="async" />
							<?php endif; ?>
							<div class="ap-page-reach__card-text">
								<?php if ( '' !== $card['kicker'] ) : ?>
									<p class="ap-page-reach__kicker"><?php echo esc_html( $card['kicker'] ); ?></p>
								<?php endif; ?>
								<p class="ap-page-reach__card-title"><?php echo ap_ui_brandmarks( esc_html( $card['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
								<?php if ( '' !== $card['text'] ) : ?>
									<p class="ap-page-reach__card-body"><?php echo esc_html( $card['text'] ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( '' !== $foot ) : ?>
		<div class="ap-page-reach__foot"><?php echo $ap_paras( $foot ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
	<?php endif; ?>
</section>
