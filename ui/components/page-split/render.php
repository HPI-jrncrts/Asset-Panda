<?php
/**
 * page-split — copy beside an image (or a centred intro band without one).
 *
 * The reusable content row of the reskinned company and resource pages:
 * "Who we are" (careers), "Our Team" and "Founder/CEO" (about), the partner
 * contact (partners), the compare hub's list panels, and live's CEO pull
 * quote (quote: true keeps it the h2 it is on live, set as a large quote).
 * Copy accepts a small safe markup: paragraphs on blank lines, **bold** and
 * [label](url) links; every piece is escaped and link targets go through
 * esc_url with an explicit protocol list.
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

$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$tone      = in_array( $props['tone'], array( 'white', 'soft', 'panel' ), true ) ? $props['tone'] : 'white';
$title_tag = 'h3' === $props['title_tag'] ? 'h3' : 'h2';
$quote     = ! empty( $props['quote'] );
$eyebrow   = trim( (string) $props['eyebrow'] );
$image     = trim( (string) $props['image'] );
$img_w     = max( 0, (int) $props['image_width'] );
$img_h     = max( 0, (int) $props['image_height'] );
$img_style = in_array( $props['image_style'], array( 'photo', 'circle', 'card', 'plain' ), true ) ? $props['image_style'] : 'photo';
$reverse   = ! empty( $props['reverse'] );
$person    = trim( (string) $props['person_name'] );
$role      = trim( (string) $props['person_role'] );
$cols      = '2' === $props['links_columns'] ? '2' : '1';
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$sec_label = trim( (string) $props['secondary_label'] );
$sec_url   = trim( (string) $props['secondary_url'] );
$protocols = array( 'http', 'https', 'mailto', 'tel' );

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

$paragraphs = array();
foreach ( preg_split( '/\R\s*\R/', trim( (string) $props['body'] ) ) as $para ) {
	if ( '' !== trim( $para ) ) {
		$paragraphs[] = trim( $para );
	}
}
$bullets = array();
foreach ( is_array( $props['bullets'] ) ? $props['bullets'] : array() as $b ) {
	if ( is_string( $b ) && '' !== trim( $b ) ) {
		$bullets[] = trim( $b );
	}
}
$links = array();
foreach ( is_array( $props['links'] ) ? $props['links'] : array() as $l ) {
	if ( is_array( $l ) && '' !== trim( (string) ( $l['label'] ?? '' ) ) && '' !== trim( (string) ( $l['url'] ?? '' ) ) ) {
		$links[] = array(
			'label' => trim( (string) $l['label'] ),
			'url'   => trim( (string) $l['url'] ),
			'note'  => trim( (string) ( $l['note'] ?? '' ) ),
		);
	}
}

$arrow = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
$check = '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="10" fill="currentColor" opacity=".14"></circle><path d="M6 10.4l2.6 2.6L14 7.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
$qmark = '<svg class="ap-page-split__qmark" width="40" height="32" viewBox="0 0 40 32" fill="none" aria-hidden="true" focusable="false"><path d="M0 32V19.2C0 8.53 5.33 2.13 16 0l2 4.27C11.6 6.13 8.67 9.6 8.4 14.67H16V32H0Zm22 0V19.2C22 8.53 27.33 2.13 38 0l2 4.27c-6.4 1.86-9.33 5.33-9.6 10.4H38V32H22Z" fill="currentColor"></path></svg>';

$has_media = '' !== $image;
$classes   = 'ap-page-split ap-page-split--' . $tone . ( $has_media ? ' ap-page-split--media' : ' ap-page-split--solo' ) . ( $reverse ? ' ap-page-split--reverse' : '' ) . ( $quote ? ' ap-page-split--quote' : '' );
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<?php if ( 'soft' === $tone ) : ?>
		<div class="ap-page-split__wash" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="ap-page-split__wrap">
		<div class="ap-page-split__row">
			<div class="ap-page-split__copy" data-ap-reveal>
				<?php if ( $quote ) : ?>
					<?php echo $qmark; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
				<?php endif; ?>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-page-split__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<<?php echo tag_escape( $title_tag ); ?> class="ap-page-split__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></<?php echo tag_escape( $title_tag ); ?>>
				<?php if ( $quote && ( '' !== $person || '' !== $role ) ) : ?>
					<p class="ap-page-split__person"><?php if ( '' !== $person ) : ?><strong><?php echo esc_html( $person ); ?></strong><?php endif; ?><?php if ( '' !== $role ) : ?><span><?php echo esc_html( $role ); ?></span><?php endif; ?></p>
				<?php endif; ?>
				<?php if ( $paragraphs ) : ?>
					<div class="ap-page-split__body">
						<?php foreach ( $paragraphs as $para ) : ?>
							<p><?php echo $ap_rich( $para ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $bullets ) : ?>
					<ul class="ap-page-split__bullets">
						<?php foreach ( $bullets as $b ) : ?>
							<li><?php echo $check; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo $ap_rich( $b ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ! $quote && ( '' !== $person || '' !== $role ) ) : ?>
					<p class="ap-page-split__person"><?php if ( '' !== $person ) : ?><strong><?php echo esc_html( $person ); ?></strong><?php endif; ?><?php if ( '' !== $role ) : ?><span><?php echo esc_html( $role ); ?></span><?php endif; ?></p>
				<?php endif; ?>
				<?php if ( $links ) : ?>
					<ul class="ap-page-split__links ap-page-split__links--cols-<?php echo esc_attr( $cols ); ?>">
						<?php foreach ( $links as $l ) : ?>
							<li>
								<a href="<?php echo esc_url( $l['url'], $protocols ); ?>">
									<span class="ap-page-split__link-label"><?php echo ap_ui_brandmarks( esc_html( $l['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?><?php if ( '' !== $l['note'] ) : ?><small><?php echo esc_html( $l['note'] ); ?></small><?php endif; ?></span>
									<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ( '' !== $cta_label && '' !== $cta_url ) || ( '' !== $sec_label && '' !== $sec_url ) ) : ?>
					<div class="ap-page-split__ctas">
						<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
							<a class="ap-page-split__btn ap-page-split__btn--primary" href="<?php echo esc_url( $cta_url, $protocols ); ?>"><?php echo esc_html( $cta_label ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $sec_label && '' !== $sec_url ) : ?>
							<a class="ap-page-split__btn ap-page-split__btn--outline" href="<?php echo esc_url( $sec_url, $protocols ); ?>"><?php echo esc_html( $sec_label ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( $has_media ) : ?>
				<div class="ap-page-split__media ap-page-split__media--<?php echo esc_attr( $img_style ); ?>" data-ap-reveal="<?php echo $reverse ? 'left' : 'right'; ?>">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"<?php echo $img_w > 0 && $img_h > 0 ? ' width="' . esc_attr( (string) $img_w ) . '" height="' . esc_attr( (string) $img_h ) . '"' : ''; ?> loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
