<?php
/**
 * solution-features — numbered feature cards beside the heading.
 *
 * Matches the live .epx-feat section: the heading column on the left (sticky
 * in the design; live's host pages stop sticky with overflow-x on html/body),
 * cards with a coloured left rule and a number glyph on the right, one column
 * below 1001px. Cards reveal through a CSS scroll timeline, as on live.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tone_of = array(
	'#5044e1' => 'indigo',
	'#e61c55' => 'red',
	'#00a991' => 'green',
	'#de8d14' => 'yellow',
);
$tones = array( 'indigo', 'red', 'green', 'yellow' );

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$accent  = strtolower( trim( (string) ( $item['accent'] ?? '' ) ) );
	$tone    = $tone_of[ $accent ] ?? ( in_array( $accent, $tones, true ) ? $accent : $tones[ count( $items ) % 4 ] );
	$items[] = array(
		'title'    => trim( (string) $item['title'] ),
		'body'     => trim( (string) ( $item['body'] ?? '' ) ),
		'icon_svg' => ap_ui_svg( $item['icon_svg'] ?? '' ),
		'tone'     => $tone,
	);
}

if ( empty( $items ) ) {
	return;
}

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service', 'steps' ), true ) ? $props['variant'] : 'solution';
$title   = trim( (string) $props['title'] );
$accent  = trim( (string) $props['title_accent'] );
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
// Opt-in extras (Partners): a heading over the cards, which pushes the card titles down to h4, and a button
// under the lede. Without them the section renders exactly as on the solution pages.
$list_title = trim( (string) $props['list_title'] );
$card_tag   = '' !== $list_title ? 'h4' : 'h3';
$cta_label  = trim( (string) $props['cta_label'] );
$cta_url    = trim( (string) $props['cta_url'] );

// The accent is a slice of the heading: escape first, then wrap that slice.
$title_html = ap_ui_brandmarks( esc_html( $title ) );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped    = ap_ui_brandmarks( esc_html( $accent ) );
	$title_html = str_replace( $escaped, '<span class="ap-solution-features__g">' . $escaped . '</span>', $title_html );
}
?>
<section class="ap-solution-features ap-solution-features--<?php echo esc_attr( $variant ); ?>">
	<div class="ap-solution-features__wrap">
		<div class="ap-solution-features__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-solution-features__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-solution-features__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only fixed spans are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-solution-features__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
			<p class="ap-solution-features__cta"><a class="ap-solution-features__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a></p>
<?php endif; ?>
		</div>
		<div class="ap-solution-features__list">
<?php if ( '' !== $list_title ) : ?>
			<h3 class="ap-solution-features__list-title"><?php echo ap_ui_brandmarks( esc_html( $list_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
<?php endif; ?>
			<?php foreach ( $items as $i => $item ) : ?>
				<article class="ap-solution-features__card ap-solution-features__card--<?php echo esc_attr( $item['tone'] ); ?> ap-solution-features__rev<?php echo $i ? ' ap-solution-features__rev--d' . esc_attr( (string) min( $i, 3 ) ) : ''; ?>" tabindex="0">
					<span class="ap-solution-features__num" aria-hidden="true"><?php echo $item['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
					<div class="ap-solution-features__body">
						<<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h3/h4. ?>><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></<?php echo $card_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h3/h4. ?>>
						<?php if ( '' !== $item['body'] ) : ?>
							<p><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
