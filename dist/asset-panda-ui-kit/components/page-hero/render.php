<?php
/**
 * page-hero — the hero of the 2026 company and resource pages.
 *
 * Built for the pages that never had a Figma design (integrations, partners,
 * compare, FAQ, tutorials, about, careers, client reviews, ROI calculator) so
 * they open the same way the designed pages do: an indigo eyebrow, an 800
 * weight headline with a gradient phrase, a lede, check chips, the gradient
 * and outline buttons and the compliance badges, on the soft brand wash.
 *
 * Live's utility pages carry their h1 as a small label over a bigger h2
 * ("Asset Panda Integrations" / "Integrating With The World's Best"); with
 * eyebrow_tag h1 the label keeps that h1 and the headline becomes the h2, so
 * the page's heading outline survives the reskin unchanged.
 *
 * No entrance animation on the copy: it is the page's largest paint, and a
 * fade would only delay it (PageSpeed). The image loads eagerly at high
 * priority and is preloaded.
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

$anchor      = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$eyebrow     = trim( (string) $props['eyebrow'] );
$eyebrow_tag = 'h1' === $props['eyebrow_tag'] && '' !== $eyebrow ? 'h1' : 'p';
$title_tag   = 'h2' === $props['title_tag'] ? 'h2' : 'h1';
if ( 'h1' === $eyebrow_tag ) {
	$title_tag = 'h2';
}
$accent = trim( (string) $props['title_accent'] );
$image  = trim( (string) $props['image'] );
$layout = 'split' === $props['layout'] && '' !== $image ? 'split' : 'center';
$frame  = 'plain' === $props['image_frame'] ? 'plain' : 'card';
$img_w  = max( 0, (int) $props['image_width'] );
$img_h  = max( 0, (int) $props['image_height'] );

$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$sec_label = trim( (string) $props['secondary_label'] );
$sec_url   = trim( (string) $props['secondary_url'] );
$protocols = array( 'http', 'https', 'mailto', 'tel' );

// Escaped headline with the accent phrase wrapped (first occurrence only).
$title_html = esc_html( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr( $title_html, 0, $at ) . '<span class="ap-page-hero__accent">' . $needle . '</span>' . substr( $title_html, $at + strlen( $needle ) );
	}
}
$title_html = ap_ui_brandmarks( nl2br( $title_html, false ) );

// Lede: paragraphs on blank lines; **bold** and [label](url) links, every piece escaped.
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
$paragraphs = array();
foreach ( preg_split( '/\R\s*\R/', trim( (string) $props['lede'] ) ) as $para ) {
	if ( '' !== trim( $para ) ) {
		$paragraphs[] = trim( $para );
	}
}

$chips = array();
foreach ( is_array( $props['chips'] ) ? $props['chips'] : array() as $chip ) {
	if ( is_string( $chip ) && '' !== trim( $chip ) ) {
		$chips[] = trim( $chip );
	}
}

$badges = array();
foreach ( is_array( $props['badges'] ) ? $props['badges'] : array() as $badge ) {
	if ( is_array( $badge ) && '' !== trim( (string) ( $badge['image'] ?? '' ) ) ) {
		$badges[] = $badge;
	}
}

$stats = array();
foreach ( is_array( $props['stats'] ) ? $props['stats'] : array() as $stat ) {
	if ( is_array( $stat ) && '' !== trim( (string) ( $stat['value'] ?? '' ) ) && '' !== trim( (string) ( $stat['label'] ?? '' ) ) ) {
		$stats[] = array(
			'value' => trim( (string) $stat['value'] ),
			'label' => trim( (string) $stat['label'] ),
		);
	}
}

$preload = 'split' === $layout ? ap_ui_preload_image( $image ) : '';
$check   = '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="10" fill="currentColor" opacity=".14"></circle><path d="M6 10.4l2.6 2.6L14 7.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

$classes = 'ap-page-hero ap-page-hero--' . $layout . ( $stats ? ' ap-page-hero--has-stats' : '' );
?>
<?php echo $preload; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url in the loader. ?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-hero__wash" aria-hidden="true"></div>
	<div class="ap-page-hero__inner">
		<div class="ap-page-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<<?php echo tag_escape( $eyebrow_tag ); ?> class="ap-page-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></<?php echo tag_escape( $eyebrow_tag ); ?>>
			<?php endif; ?>
			<<?php echo tag_escape( $title_tag ); ?> class="ap-page-hero__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></<?php echo tag_escape( $title_tag ); ?>>
			<?php if ( $paragraphs ) : ?>
				<div class="ap-page-hero__lede">
					<?php foreach ( $paragraphs as $para ) : ?>
						<p><?php echo $ap_rich( $para ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $chips ) : ?>
				<ul class="ap-page-hero__chips">
					<?php foreach ( $chips as $chip ) : ?>
						<li><?php echo $check; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo ap_ui_brandmarks( esc_html( $chip ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ( '' !== $cta_label && '' !== $cta_url ) || ( '' !== $sec_label && '' !== $sec_url ) ) : ?>
				<div class="ap-page-hero__ctas">
					<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
						<a class="ap-page-hero__btn ap-page-hero__btn--primary" href="<?php echo esc_url( $cta_url, $protocols ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $sec_label && '' !== $sec_url ) : ?>
						<a class="ap-page-hero__btn ap-page-hero__btn--outline" href="<?php echo esc_url( $sec_url, $protocols ); ?>"><?php echo esc_html( $sec_label ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $badges ) : ?>
				<div class="ap-page-hero__badges">
					<?php foreach ( $badges as $badge ) : ?>
						<?php
						$bw = isset( $badge['w'] ) ? (int) $badge['w'] : 0;
						$bh = isset( $badge['h'] ) ? (int) $badge['h'] : 0;
						?>
						<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( (string) ( $badge['alt'] ?? '' ) ); ?>"<?php echo $bw > 0 && $bh > 0 ? ' width="' . esc_attr( (string) $bw ) . '" height="' . esc_attr( (string) $bh ) . '"' : ''; ?> decoding="async" />
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( 'split' === $layout ) : ?>
			<div class="ap-page-hero__media ap-page-hero__media--<?php echo esc_attr( $frame ); ?>">
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> loading="eager" fetchpriority="high" decoding="async" />
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $stats ) : ?>
		<dl class="ap-page-hero__stats">
			<?php foreach ( $stats as $stat ) : ?>
				<div class="ap-page-hero__stat">
					<dt><?php echo esc_html( $stat['label'] ); ?></dt>
					<dd><?php echo esc_html( $stat['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>
</section>
