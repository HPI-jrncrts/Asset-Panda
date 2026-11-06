<?php
/**
 * lp-hero — the opening section of long-form and utility pages.
 *
 * Built for the pages the live site keeps out of its menus (legal pages,
 * the government guides, webinar replays, the AWS re:Invent event page):
 * breadcrumbs, an eyebrow, the page h1, a lede and a meta line over the
 * same soft colour wash as the form and thank-you heroes. Three layouts:
 * a centred column, copy beside a picture card (split), or centred copy
 * over a wide picture (banner).
 *
 * @var array $props Validated props.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$layout  = in_array( $props['layout'], array( 'center', 'split', 'banner' ), true ) ? $props['layout'] : 'center';
$tone    = 'white' === $props['tone'] ? 'white' : 'wash';
$bg      = trim( (string) $props['background'] );
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
$meta    = trim( (string) $props['meta'] );
$label   = trim( (string) $props['cta_label'] );
$url     = trim( (string) $props['cta_url'] );
$image   = trim( (string) $props['image'] );
$img_w   = max( 0, (int) $props['image_width'] );
$img_h   = max( 0, (int) $props['image_height'] );
$home    = trim( (string) $props['home_url'] );
$home    = '' !== $home ? $home : '/';
if ( 'center' === $layout ) {
	$image = '';
}

$crumbs = array();
foreach ( is_array( $props['crumbs'] ) ? $props['crumbs'] : array() as $crumb ) {
	if ( is_array( $crumb ) && '' !== trim( (string) ( $crumb['label'] ?? '' ) ) ) {
		$crumbs[] = array(
			'label' => trim( (string) $crumb['label'] ),
			'url'   => trim( (string) ( $crumb['url'] ?? '' ) ),
		);
	}
}

/*
 * Escaped text with the author's line breaks, **bold** runs, [label](url)
 * links and the UrsaAI brand mark. Each piece is escaped before the fixed
 * markup is added, so nothing the author typed reaches the page as HTML.
 */
$ap_text = static function ( $text ) {
	$html = ap_ui_brandmarks( nl2br( esc_html( $text ), false ) );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return is_string( $bold ) ? $bold : $html;
};
$ap_rich = static function ( $text ) use ( $ap_text ) {
	$out = '';
	$pos = 0;
	if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$out .= $ap_text( substr( $text, $pos, $hit[0][1] - $pos ) );
			$href = esc_url( $hit[2][0], array( 'http', 'https', 'mailto', 'tel' ) );
			$ext  = (bool) preg_match( '#^https?://#i', $hit[2][0] );
			$out .= '' !== $href ? '<a href="' . $href . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};

$classes = 'ap-lp-hero ap-lp-hero--' . $layout . ' ap-lp-hero--' . $tone;
$style   = ( 'wash' === $tone && '' !== $bg ) ? '--ap-lp-hero-bg:url("' . esc_url_raw( $bg ) . '")' : '';
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<div class="ap-lp-hero__inner">
		<div class="ap-lp-hero__copy">
			<?php if ( $crumbs ) : ?>
				<nav class="ap-lp-hero__crumbs" aria-label="Breadcrumb">
					<ol>
						<li><a class="ap-lp-hero__home" href="<?php echo esc_url( $home ); ?>" aria-label="Home"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M1.5 8 7.47 2.03a.75.75 0 0 1 1.06 0L14.5 8M3 6.5v6.75c0 .41.34.75.75.75H6.5v-3.25c0-.41.34-.75.75-.75h1.5c.41 0 .75.34.75.75V14h2.75c.41 0 .75-.34.75-.75V6.5M5.5 14H11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg></a></li>
						<?php foreach ( $crumbs as $i => $crumb ) : ?>
							<li>
								<svg class="ap-lp-hero__sep" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m6 4 4 4-4 4" stroke="currentColor" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<?php if ( '' !== $crumb['url'] && count( $crumbs ) - 1 !== $i ) : ?>
									<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
								<?php else : ?>
									<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>
			<?php endif; ?>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-lp-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h1 class="ap-lp-hero__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and the brand mark are added. ?></h1>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-lp-hero__lede"><?php echo $ap_rich( $lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
			<?php endif; ?>
			<?php if ( '' !== $meta ) : ?>
				<p class="ap-lp-hero__meta"><?php echo esc_html( $meta ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $label && '' !== $url ) : ?>
				<div class="ap-lp-hero__actions">
					<a class="ap-lp-hero__btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $image ) : ?>
			<figure class="ap-lp-hero__media"<?php echo ( 'banner' === $layout && $img_w > 0 ) ? ' style="' . esc_attr( 'max-width:' . $img_w . 'px' ) . '"' : ''; ?>>
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> fetchpriority="high" decoding="async" />
			</figure>
		<?php endif; ?>
	</div>
</section>
