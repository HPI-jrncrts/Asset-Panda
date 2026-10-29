<?php
/**
 * photo-tags — the problem section on the September 2026 solution pages.
 *
 * Matches the live .sp-prob: a centred heading, a photo with up to four problem
 * tags pinned around it, a screen-reader list carrying the full sentences, and
 * a panda call-to-action bar. Tag positions arrive as numbers and the fade
 * colour as a validated hex, so author text never reaches a style attribute.
 * Inner class names are the live ones, so this stays diffable against the
 * ported sheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $html );
	}
	return $html;
};

$tags = array_values(
	array_filter(
		is_array( $props['tags'] ) ? $props['tags'] : array(),
		static function ( $t ) {
			return is_array( $t ) && '' !== trim( (string) ( $t['text'] ?? '' ) );
		}
	)
);
$sr   = array_values(
	array_filter(
		array_map(
			static function ( $s ) {
				return trim( (string) $s );
			},
			is_array( $props['sr_list'] ) ? $props['sr_list'] : array()
		)
	)
);
$bg   = trim( (string) $props['background'] );
?>
<?php
$ap_head  = (int) $props['head_width'];
$ap_style = array();
if ( '' !== $bg ) {
	$ap_style[] = '--ap-photo-tags-bg:url("' . esc_url_raw( $bg ) . '")';
}
if ( $ap_head >= 320 && $ap_head <= 1200 && 560 !== $ap_head ) {
	$ap_style[] = '--ap-photo-tags-head:' . $ap_head . 'px';
}
// Photo box and art height: the live pages' 547 x 364 photo on a 364px stage unless a page sets its own
// (the 2026 hub frames centre a 602 x 260 two-photo composite on a 260px stage).
$ap_pw = (int) $props['photo_width'];
$ap_ph = (int) $props['photo_height'];
$ap_ah = (int) $props['art_height'];
$ap_pw = ( $ap_pw >= 120 && $ap_pw <= 1120 ) ? $ap_pw : 547;
$ap_ph = ( $ap_ph >= 120 && $ap_ph <= 600 ) ? $ap_ph : 364;
if ( 547 !== $ap_pw || 364 !== $ap_ph ) {
	$ap_style[] = '--ap-photo-tags-pw:' . $ap_pw . 'px';
	$ap_style[] = '--ap-photo-tags-ph:' . $ap_ph . 'px';
	$ap_style[] = '--ap-photo-tags-ratio:' . $ap_pw . '/' . $ap_ph;
}
if ( $ap_ah >= 120 && $ap_ah <= 600 && 364 !== $ap_ah ) {
	$ap_style[] = '--ap-photo-tags-ah:' . $ap_ah . 'px';
}
$ap_lw = (int) $props['lede_width'];
if ( $ap_lw >= 320 && $ap_lw <= 1120 && 560 !== $ap_lw ) {
	$ap_style[] = '--ap-photo-tags-lede:' . $ap_lw . 'px';
}
?>
<section class="ap-photo-tags"<?php echo $ap_style ? ' style="' . esc_attr( implode( ';', $ap_style ) ) . '"' : ''; ?>>
	<div class="ap-photo-tags__bg" aria-hidden="true"></div>
	<div class="ap-photo-tags__wrap">
		<div class="ap-photo-tags__head sp-prev">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-photo-tags__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-photo-tags__h2"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-photo-tags__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-photo-tags__art">
			<?php if ( '' !== trim( (string) $props['photo'] ) ) : ?>
				<img class="ap-photo-tags__photo sp-prev" src="<?php echo esc_url( $props['photo'] ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" width="<?php echo (int) $ap_pw; ?>" height="<?php echo (int) $ap_ph; ?>" loading="lazy" decoding="async" />
			<?php endif; ?>
			<?php if ( $tags ) : ?>
				<ul class="ap-photo-tags__tags" aria-hidden="true">
					<?php foreach ( array_slice( $tags, 0, 4 ) as $i => $tag ) : ?>
						<?php
						$vars = array();
						foreach ( array( 'x' => '--x', 'y' => '--y' ) as $key => $var ) {
							if ( isset( $tag[ $key ] ) && is_numeric( $tag[ $key ] ) ) {
								$vars[] = $var . ':' . round( (float) $tag[ $key ], 2 ) . 'px';
							}
						}
						if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string) ( $tag['end'] ?? '' ) ) ) {
							$vars[] = '--end:' . $tag['end'];
						}
						?>
						<li class="sp-ptag sp-ptag--<?php echo esc_attr( (string) ( $i + 1 ) ); ?>" style="<?php echo esc_attr( implode( ';', $vars ) ); ?>"><?php echo ap_ui_svg( $tag['icon_svg'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?><span><?php echo esc_html( $tag['text'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( $sr ) : ?>
			<ul class="ap-photo-tags__sr">
				<?php foreach ( $sr as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $props['cta_title'] ) || '' !== trim( (string) $props['cta_body'] ) ) : ?>
			<div class="sp-pcta sp-prev">
				<div class="sp-pcta__body">
					<?php $logo = ap_ui_svg( $props['cta_logo_svg'] ); ?>
					<?php if ( '' !== $logo ) : ?>
						<span class="sp-pcta__logo" aria-hidden="true"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
					<?php endif; ?>
					<div>
						<?php if ( '' !== trim( (string) $props['cta_title'] ) ) : ?>
							<p class="sp-pcta__title"><?php echo ap_ui_brandmarks( esc_html( $props['cta_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $props['cta_body'] ) ) : ?>
							<p class="sp-pcta__text"><?php echo ap_ui_brandmarks( esc_html( $props['cta_body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="epx-btn epx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
