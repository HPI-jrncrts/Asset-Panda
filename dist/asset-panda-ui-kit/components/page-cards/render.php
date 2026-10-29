<?php
/**
 * page-cards — card grid of the reskinned company and resource pages.
 *
 * Four looks over one card: icon (careers values and benefits, compare's
 * "Why Teams Switch"), number (the partner process steps, the ROI method),
 * badge (the about page's awards, each with its category links) and plain.
 * Icons arrive as inline SVG drawn in currentColor, so each one takes its
 * card's brand colour instead of live's black outline. Copy accepts **bold**
 * and [label](url) links; everything is escaped, link targets go through
 * esc_url with an explicit protocol list, SVG through ap_ui_svg().
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$accents   = array( 'indigo', 'green', 'yellow', 'red', 'violet' );
$protocols = array( 'http', 'https', 'mailto', 'tel' );

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $i => $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$title = trim( (string) ( $item['title'] ?? '' ) );
	$body  = trim( (string) ( $item['body'] ?? '' ) );
	if ( '' === $title && '' === $body ) {
		continue;
	}
	$links = array();
	foreach ( is_array( $item['links'] ?? null ) ? $item['links'] : array() as $l ) {
		if ( is_array( $l ) && '' !== trim( (string) ( $l['label'] ?? '' ) ) && '' !== trim( (string) ( $l['url'] ?? '' ) ) ) {
			$links[] = array( trim( (string) $l['label'] ), trim( (string) $l['url'] ) );
		}
	}
	$accent  = in_array( $item['accent'] ?? '', $accents, true ) ? $item['accent'] : $accents[ count( $items ) % 4 ];
	$items[] = array(
		'title'      => $title,
		'body'       => $body,
		'icon_svg'   => ap_ui_svg( $item['icon_svg'] ?? '' ),
		'icon'       => trim( (string) ( $item['icon'] ?? '' ) ),
		'image'      => trim( (string) ( $item['image'] ?? '' ) ),
		'image_alt'  => (string) ( $item['image_alt'] ?? '' ),
		'image_w'    => (int) ( $item['image_w'] ?? 0 ),
		'image_h'    => (int) ( $item['image_h'] ?? 0 ),
		'number'     => trim( (string) ( $item['number'] ?? '' ) ),
		'accent'     => $accent,
		'links'      => $links,
		'url'        => trim( (string) ( $item['url'] ?? '' ) ),
		'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
	);
}
if ( empty( $items ) ) {
	return;
}

$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$tone      = 'soft' === $props['tone'] ? 'soft' : 'white';
$look      = in_array( $props['look'], array( 'icon', 'number', 'badge', 'plain' ), true ) ? $props['look'] : 'icon';
$columns   = in_array( $props['columns'], array( '2', '3', '4' ), true ) ? $props['columns'] : '3';
$eyebrow   = trim( (string) $props['eyebrow'] );
$title     = trim( (string) $props['title'] );
$card_tag  = '' === $title ? 'h2' : 'h3';
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$sec_label = trim( (string) $props['secondary_label'] );
$sec_url   = trim( (string) $props['secondary_url'] );

$ap_text = static function ( $text ) {
	$html = nl2br( esc_html( $text ), false );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};
$ap_rich = static function ( $text ) use ( $ap_text, $protocols ) {
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
	$html = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $para ) {
		if ( '' !== trim( $para ) ) {
			$html .= '<p>' . $ap_rich( $para ) . '</p>';
		}
	}
	return $html;
};

$arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

$classes = 'ap-page-cards ap-page-cards--' . $tone . ' ap-page-cards--' . $look . ' ap-page-cards--cols-' . $columns;
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<?php if ( 'soft' === $tone ) : ?>
		<div class="ap-page-cards__wash" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="ap-page-cards__wrap">
		<?php if ( '' !== $title || '' !== $eyebrow || '' !== trim( (string) $props['intro'] ) ) : ?>
			<div class="ap-page-cards__head" data-ap-reveal>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-page-cards__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h2 class="ap-page-cards__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $props['intro'] ) ) : ?>
					<div class="ap-page-cards__intro"><?php echo $ap_paras( $props['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<ul class="ap-page-cards__grid">
			<?php foreach ( $items as $i => $item ) : ?>
				<?php
				$url      = '' !== $item['url'] ? esc_url( $item['url'], $protocols ) : '';
				$has_icon = '' !== $item['icon_svg'] || '' !== $item['icon'];
				?>
				<li class="ap-page-cards__card ap-page-cards__card--<?php echo esc_attr( $item['accent'] ); ?><?php echo '' !== $url ? ' ap-page-cards__card--link' : ''; ?>" data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( $i % (int) $columns ) ); ?>">
					<?php if ( 'number' === $look ) : ?>
						<div class="ap-page-cards__top">
							<span class="ap-page-cards__num"><?php echo esc_html( '' !== $item['number'] ? $item['number'] : (string) ( $i + 1 ) ); ?></span>
							<?php if ( $has_icon ) : ?>
								<span class="ap-page-cards__icon" aria-hidden="true"><?php echo '' !== $item['icon_svg'] ? $item['icon_svg'] : '<img src="' . esc_url( $item['icon'] ) . '" alt="" width="40" height="40" loading="lazy" decoding="async" />'; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg() / esc_url. ?></span>
							<?php endif; ?>
						</div>
					<?php elseif ( 'badge' === $look && '' !== $item['image'] ) : ?>
						<div class="ap-page-cards__badge">
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ); ?>"<?php echo $item['image_w'] > 0 && $item['image_h'] > 0 ? ' width="' . esc_attr( (string) $item['image_w'] ) . '" height="' . esc_attr( (string) $item['image_h'] ) . '"' : ''; ?> loading="lazy" decoding="async" />
						</div>
					<?php elseif ( 'icon' === $look && $has_icon ) : ?>
						<span class="ap-page-cards__icon" aria-hidden="true"><?php echo '' !== $item['icon_svg'] ? $item['icon_svg'] : '<img src="' . esc_url( $item['icon'] ) . '" alt="" width="40" height="40" loading="lazy" decoding="async" />'; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg() / esc_url. ?></span>
					<?php endif; ?>
					<?php if ( '' !== $item['title'] ) : ?>
						<<?php echo tag_escape( $card_tag ); ?> class="ap-page-cards__name">
							<?php if ( '' !== $url ) : ?>
								<a class="ap-page-cards__stretch" href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>"><?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></a>
							<?php else : ?>
								<?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?>
							<?php endif; ?>
						</<?php echo tag_escape( $card_tag ); ?>>
					<?php endif; ?>
					<?php if ( '' !== $item['body'] ) : ?>
						<div class="ap-page-cards__body"><?php echo $ap_paras( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
					<?php endif; ?>
					<?php if ( $item['links'] ) : ?>
						<ul class="ap-page-cards__links">
							<?php foreach ( $item['links'] as $l ) : ?>
								<li><a href="<?php echo esc_url( $l[1], $protocols ); ?>"><?php echo esc_html( $l[0] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( '' !== $url && '' !== $item['link_label'] ) : ?>
						<span class="ap-page-cards__more" aria-hidden="true"><?php echo esc_html( $item['link_label'] ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( ( '' !== $cta_label && '' !== $cta_url ) || ( '' !== $sec_label && '' !== $sec_url ) ) : ?>
			<div class="ap-page-cards__ctas">
				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<a class="ap-page-cards__btn ap-page-cards__btn--primary" href="<?php echo esc_url( $cta_url, $protocols ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $sec_label && '' !== $sec_url ) : ?>
					<a class="ap-page-cards__btn ap-page-cards__btn--outline" href="<?php echo esc_url( $sec_url, $protocols ); ?>"><?php echo esc_html( $sec_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) $props['note'] ) ) : ?>
			<div class="ap-page-cards__note"><?php echo $ap_paras( $props['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
		<?php endif; ?>
	</div>
</section>
