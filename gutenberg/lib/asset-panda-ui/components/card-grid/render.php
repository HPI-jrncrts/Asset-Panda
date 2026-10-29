<?php
/**
 * card-grid — centred heading over a grid of cards.
 *
 * A plain, reusable grid for pages with no Figma design: the contact page's
 * three contact cards (icon, title, copy with mailto/tel links, a button) and
 * the free-trial page's six account templates (screenshot, title, copy).
 *
 * Card copy accepts a small, safe markup: line breaks, **bold** and
 * [label](url) links. The text is split around the links and each piece is
 * escaped, so the fixed <a>, <strong> and <br> are the only tags that can
 * appear; link targets go through esc_url with an explicit protocol list.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( is_array( $item ) && '' !== trim( (string) ( $item['title'] ?? '' ) ) ) {
		$items[] = $item;
	}
}
if ( empty( $items ) ) {
	return;
}

$tone      = 'soft' === $props['tone'] ? 'soft' : 'white';
$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$title     = trim( (string) $props['title'] );
$title_tag = 'h1' === $props['title_tag'] ? 'h1' : 'h2';
$card_tag  = '' === $title ? 'h2' : 'h3';
$eyebrow   = trim( (string) $props['eyebrow'] );
$subtitle  = trim( (string) $props['subtitle'] );
$intro     = trim( (string) $props['intro'] );
$columns   = '2' === $props['columns'] ? '2' : '3';

$ap_text = static function ( $text ) {
	$html = nl2br( esc_html( $text ), false );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return is_string( $bold ) ? $bold : $html;
};
$ap_rich = static function ( $text ) use ( $ap_text ) {
	$text = trim( (string) $text );
	$out  = '';
	$pos  = 0;
	if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$out .= $ap_text( substr( $text, $pos, $hit[0][1] - $pos ) );
			$url  = esc_url( $hit[2][0], array( 'http', 'https', 'mailto', 'tel' ) );
			$out .= '' !== $url ? '<a href="' . $url . '">' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};
?>
<section class="ap-card-grid ap-card-grid--<?php echo esc_attr( $tone ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-card-grid__inner">
		<?php if ( '' !== $title || '' !== $eyebrow || '' !== $intro ) : ?>
			<div class="ap-card-grid__head">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-card-grid__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<<?php echo tag_escape( $title_tag ); ?> class="ap-card-grid__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo tag_escape( $title_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $subtitle ) : ?>
					<p class="ap-card-grid__sub"><?php echo ap_ui_lines( $subtitle ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
				<?php endif; ?>
				<?php if ( '' !== $intro ) : ?>
					<p class="ap-card-grid__intro"><?php echo ap_ui_lines( $intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="ap-card-grid__grid ap-card-grid__grid--<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$image  = trim( (string) ( $item['image'] ?? '' ) );
				$icon   = trim( (string) ( $item['icon'] ?? '' ) );
				$body   = trim( (string) ( $item['body'] ?? '' ) );
				$label  = trim( (string) ( $item['link_label'] ?? '' ) );
				$url    = trim( (string) ( $item['link_url'] ?? '' ) );
				$img_w  = isset( $item['image_w'] ) ? (int) $item['image_w'] : 0;
				$img_h  = isset( $item['image_h'] ) ? (int) $item['image_h'] : 0;
				?>
				<article class="ap-card-grid__card<?php echo '' !== $image ? ' ap-card-grid__card--image' : ''; ?>">
					<?php if ( '' !== $image ) : ?>
						<div class="ap-card-grid__media">
							<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) ( $item['image_alt'] ?? '' ) ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> loading="lazy" decoding="async" />
						</div>
					<?php elseif ( '' !== $icon ) : ?>
						<img class="ap-card-grid__icon" src="<?php echo esc_url( $icon ); ?>" alt="" width="56" height="56" loading="lazy" decoding="async" />
					<?php endif; ?>
					<div class="ap-card-grid__body">
						<<?php echo tag_escape( $card_tag ); ?> class="ap-card-grid__card-title"><?php echo esc_html( $item['title'] ); ?></<?php echo tag_escape( $card_tag ); ?>>
						<?php if ( '' !== $body ) : ?>
							<p class="ap-card-grid__text"><?php echo $ap_rich( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped piecewise in the helper. ?></p>
						<?php endif; ?>
						<?php if ( '' !== $label && '' !== $url ) : ?>
							<a class="ap-card-grid__btn" href="<?php echo esc_url( $url ); ?>" data-ap-hover="lift"><?php echo esc_html( $label ); ?></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
