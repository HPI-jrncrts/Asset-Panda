<?php
/**
 * page-trust — the "Built on Trust" section of the 2026 company pages (Our Company, Figma 7460:104417).
 *
 * Award category lines link out when they carry a url (Forbes, Capterra listings) and are plain text otherwise.
 * The buttons join the kit's sitewide CTA scale (tokens.css). Copy takes the page-* light markup: a blank line
 * starts a paragraph, [label](url) makes a link and **double asterisks** bold.
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

$awards = array();
foreach ( is_array( $props['awards'] ) ? $props['awards'] : array() as $award ) {
	if ( ! is_array( $award ) || '' === trim( (string) ( $award['title'] ?? '' ) ) ) {
		continue;
	}
	$lines = array();
	foreach ( is_array( $award['items'] ?? null ) ? $award['items'] : array() as $line ) {
		if ( is_array( $line ) && '' !== trim( (string) ( $line['label'] ?? '' ) ) ) {
			$lines[] = array(
				'label' => trim( (string) $line['label'] ),
				'url'   => trim( (string) ( $line['url'] ?? '' ) ),
			);
		}
	}
	$awards[] = array(
		'image'  => trim( (string) ( $award['image'] ?? '' ) ),
		'alt'    => (string) ( $award['image_alt'] ?? '' ),
		'width'  => max( 0, (int) ( $award['width'] ?? 0 ) ),
		'height' => max( 0, (int) ( $award['height'] ?? 0 ) ),
		'title'  => trim( (string) $award['title'] ),
		'items'  => $lines,
	);
}

$anchor      = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$aside_icon  = trim( (string) $props['aside_icon'] );
$aside_title = trim( (string) $props['aside_title'] );
$aside_body  = trim( (string) $props['aside_body'] );
$sec_label   = trim( (string) $props['secondary_label'] );
$sec_url     = trim( (string) $props['secondary_url'] );
$cta_label   = trim( (string) $props['cta_label'] );
$cta_url     = trim( (string) $props['cta_url'] );
$link_label  = trim( (string) $props['link_label'] );
$link_url    = trim( (string) $props['link_url'] );
$has_buttons = ( '' !== $sec_label && '' !== $sec_url ) || ( '' !== $cta_label && '' !== $cta_url );
$arrow       = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-page-trust"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-trust__wrap">
		<div class="ap-page-trust__top">
			<div class="ap-page-trust__intro">
				<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
					<p class="ap-page-trust__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h2 class="ap-page-trust__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in ap_ui_lines(). ?></h2>
				<?php if ( '' !== trim( (string) $props['body'] ) ) : ?>
					<div class="ap-page-trust__body"><?php echo $ap_paras( $props['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $aside_title || '' !== $aside_body ) : ?>
				<div class="ap-page-trust__aside">
					<?php if ( '' !== $aside_icon ) : ?>
						<img class="ap-page-trust__aside-icon" src="<?php echo esc_url( $aside_icon ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async" />
					<?php endif; ?>
					<div class="ap-page-trust__aside-text">
						<?php if ( '' !== $aside_title ) : ?>
							<h3 class="ap-page-trust__aside-title"><?php echo esc_html( $aside_title ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== $aside_body ) : ?>
							<div class="ap-page-trust__aside-body"><?php echo $ap_paras( $aside_body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $awards ) : ?>
			<ul class="ap-page-trust__awards">
				<?php foreach ( $awards as $i => $award ) : ?>
					<li class="ap-page-trust__award" data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) min( $i, 4 ) ); ?>">
						<?php if ( '' !== $award['image'] ) : ?>
							<img class="ap-page-trust__badge" src="<?php echo esc_url( $award['image'] ); ?>" alt="<?php echo esc_attr( $award['alt'] ); ?>"<?php echo $award['width'] > 0 && $award['height'] > 0 ? ' width="' . esc_attr( (string) $award['width'] ) . '" height="' . esc_attr( (string) $award['height'] ) . '"' : ''; ?> loading="lazy" decoding="async" />
						<?php endif; ?>
						<h3 class="ap-page-trust__award-title"><?php echo esc_html( $award['title'] ); ?></h3>
						<?php if ( $award['items'] ) : ?>
							<ul class="ap-page-trust__award-items">
								<?php foreach ( $award['items'] as $line ) : ?>
									<li>
										<?php if ( '' !== $line['url'] ) : ?>
											<a href="<?php echo esc_url( $line['url'], $protocols ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $line['label'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $line['label'] ); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $has_buttons || ( '' !== $link_label && '' !== $link_url ) ) : ?>
			<div class="ap-page-trust__actions">
				<?php if ( $has_buttons ) : ?>
					<div class="ap-page-trust__buttons">
						<?php if ( '' !== $sec_label && '' !== $sec_url ) : ?>
							<a class="ap-page-trust__btn ap-page-trust__btn--outline" href="<?php echo esc_url( $sec_url, $protocols ); ?>"><?php echo esc_html( $sec_label ); ?></a>
						<?php endif; ?>
						<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
							<a class="ap-page-trust__btn ap-page-trust__btn--primary" href="<?php echo esc_url( $cta_url, $protocols ); ?>"><?php echo esc_html( $cta_label ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $link_label && '' !== $link_url ) : ?>
					<a class="ap-page-trust__link" href="<?php echo esc_url( $link_url, $protocols ); ?>"><span><?php echo esc_html( $link_label ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
