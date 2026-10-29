<?php
/**
 * solution-rows — capability rows under a centred heading.
 *
 * Matches the live .epx-sol section: each row is a tinted card with a coloured
 * border, the icon and title on the left, copy and an optional link on the
 * right; the rows stack below 1001px. Rows reveal through a CSS scroll
 * timeline, as on live.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'indigo', 'green', 'yellow', 'blue', 'red', 'cyan' );

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$tone    = trim( (string) ( $item['tone'] ?? '' ) );
	$links   = array();
	foreach ( ( isset( $item['links'] ) && is_array( $item['links'] ) ) ? $item['links'] : array() as $extra ) {
		$extra_label = is_array( $extra ) ? trim( (string) ( $extra['label'] ?? '' ) ) : '';
		$extra_url   = is_array( $extra ) ? trim( (string) ( $extra['url'] ?? '' ) ) : '';
		if ( '' !== $extra_label && '' !== $extra_url ) {
			$links[] = array(
				'label' => $extra_label,
				'url'   => $extra_url,
			);
		}
	}
	$items[] = array(
		'title'      => trim( (string) $item['title'] ),
		'body'       => trim( (string) ( $item['body'] ?? '' ) ),
		'icon_svg'   => ap_ui_svg( $item['icon_svg'] ?? '' ),
		'badge'      => trim( (string) ( $item['badge'] ?? '' ) ),
		'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
		'url'        => trim( (string) ( $item['url'] ?? '' ) ),
		'links'      => $links,
		'tone'       => in_array( $tone, $tones, true ) ? $tone : $tones[ count( $items ) % count( $tones ) ],
	);
}

if ( empty( $items ) ) {
	return;
}

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
$title   = trim( (string) $props['title'] );
$accent  = trim( (string) $props['title_accent'] );
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
$head_w  = (int) $props['head_width'];
$head_w  = ( $head_w >= 320 && $head_w <= 1200 ) ? $head_w : 800;

// The accent is a slice of the heading: escape first, then wrap that slice.
$title_html = ap_ui_brandmarks( esc_html( $title ) );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped    = ap_ui_brandmarks( esc_html( $accent ) );
	$title_html = str_replace( $escaped, '<span class="ap-solution-rows__g">' . $escaped . '</span>', $title_html );
}

// Live's link arrow: 24px on the solution pages, a slim 19x9 one on the others.
$arrow = 'solution' === $variant
	? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="#5044E1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>'
	: '<svg viewBox="0 0 19 8.5" width="19" height="9" fill="none" aria-hidden="true"><path d="M14.75 8L18.5 4.25L14.75 0.5M18.5 4.25H0.5" stroke="#5044E1" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-solution-rows ap-solution-rows--<?php echo esc_attr( $variant ); ?>" style="<?php echo esc_attr( '--ap-srows-head:' . $head_w . 'px' ); ?>">
	<div class="ap-solution-rows__wrap">
		<div class="ap-solution-rows__head ap-solution-rows__rev">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-solution-rows__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-solution-rows__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only fixed spans are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-solution-rows__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>
		<div class="ap-solution-rows__rows">
			<?php foreach ( $items as $i => $item ) : ?>
				<article class="ap-solution-rows__row ap-solution-rows__row--<?php echo esc_attr( $item['tone'] ); ?><?php echo '' !== $item['badge'] ? ' ap-solution-rows__row--badged' : ''; ?><?php echo '' === $item['icon_svg'] ? ' ap-solution-rows__row--no-icon' : ''; ?> ap-solution-rows__rev ap-solution-rows__rev--d<?php echo esc_attr( (string) min( $i + 1, 6 ) ); ?>">
					<div class="ap-solution-rows__lead">
						<?php if ( '' !== $item['icon_svg'] ) : ?>
							<span class="ap-solution-rows__ico" aria-hidden="true"><?php echo $item['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php endif; ?>
						<h3><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						<?php if ( '' !== $item['badge'] ) : ?>
							<span class="ap-solution-rows__badge"><span><?php echo esc_html( $item['badge'] ); ?></span></span>
						<?php endif; ?>
					</div>
					<div class="ap-solution-rows__body">
						<?php if ( '' !== $item['body'] ) : ?>
							<p><?php echo ap_ui_brandmarks( ap_ui_lines( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
						<?php endif; ?>
						<?php if ( '' !== $item['link_label'] && '' !== $item['url'] ) : ?>
							<a class="ap-solution-rows__cta" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
						<?php endif; ?>
						<?php foreach ( $item['links'] as $extra ) : ?>
							<a class="ap-solution-rows__cta" href="<?php echo esc_url( $extra['url'] ); ?>"><?php echo esc_html( $extra['label'] ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
						<?php endforeach; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
