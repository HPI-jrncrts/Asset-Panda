<?php
/**
 * story-slider — customer-story slider.
 *
 * Matches the live product-page testimonial (#ap-*-case, apc-* classes), which
 * the industry hubs' #ap-gov-case mirrors. Every slide shares one grid cell so
 * the slider keeps the tallest slide's height and never jumps; behaviour.js
 * swaps .is-active and turns a video slide's poster into a YouTube player on
 * click (nothing loads from YouTube until then).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A slide needs a quote, except a video slide with a title: the video carries the
// story (live School Reunification opens on one). Slides that have a quote render
// exactly as before.
$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			if ( ! is_array( $i ) ) {
				return false;
			}
			if ( '' !== trim( (string) ( $i['quote'] ?? '' ) ) ) {
				return true;
			}
			return 1 === preg_match( '/^[\w-]{6,20}$/', (string) ( $i['video_id'] ?? '' ) ) && '' !== trim( (string) ( $i['title'] ?? '' ) );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$count      = count( $items );
$quote_icon = trim( (string) $props['quote_icon'] );
if ( '' === $quote_icon ) {
	$quote_icon = ap_ui_asset_url( 'story-slider', 'quote-up.png' );
}
$arrow = ap_ui_asset_url( 'story-slider', 'icon-arrow.svg' );

// "The *HOSPICE* of East Texas" → escaped text with the starred run in <strong>.
$ap_wordmark = static function ( $text ) {
	return preg_replace( '/\*([^*]+)\*/', '<strong>$1</strong>', esc_html( trim( (string) $text ) ) );
};

$ap_px = static function ( $value ) {
	return is_numeric( $value ) && (float) $value > 0 ? round( (float) $value, 1 ) . 'px' : '';
};

// The client logo plate over a video poster (2026-10 Figma: the Wilson County sheriff's badge on the
// Emergency Preparedness / School Reunification video). Photo slides keep their own markup below.
$ap_logo = static function ( $item ) use ( $ap_px, $ap_wordmark ) {
	$logo  = trim( (string) ( $item['logo'] ?? '' ) );
	$mark  = trim( (string) ( $item['logo_text'] ?? '' ) );
	$style = in_array( $item['logo_style'] ?? '', array( 'hospice', 'osage', 'fresenius' ), true ) ? $item['logo_style'] : '';
	$lw    = $ap_px( $item['logo_w'] ?? null );
	$lh    = $ap_px( $item['logo_h'] ?? null );
	if ( '' !== $logo ) {
		$size = '' !== $lw || '' !== $lh ? ' style="' . esc_attr( ( '' !== $lw ? 'width:' . $lw . ';' : '' ) . ( '' !== $lh ? 'height:' . $lh . ';' : '' ) . 'object-fit:contain' ) . '"' : '';
		return '<div class="apc-logo"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $item['logo_alt'] ?? '' ) . '" loading="lazy" decoding="async"' . $size . ' /></div>';
	}
	if ( '' !== $mark ) {
		return '<div class="apc-logo apc-logo--wordmark"><span class="apc-wordmark' . ( '' !== $style ? ' apc-wordmark--' . esc_attr( $style ) : '' ) . '">' . $ap_wordmark( $mark ) . '</span></div>';
	}
	return '';
};
?>
<section class="ap-story-slider<?php echo 'default' !== $props['variant'] ? ' ap-story-slider--' . esc_attr( $props['variant'] ) : ''; ?><?php echo 'wide' === $props['stack'] ? ' ap-story-slider--wide' : ''; ?>" data-ap-story>
	<div class="apc-container">
		<div class="apc-header">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="apc-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="apc-h2"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="apc-lede"><?php echo esc_html( $props['lede'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="apc-slider">
			<?php foreach ( $items as $n => $item ) : ?>
				<?php
				$video = preg_match( '/^[\w-]{6,20}$/', (string) ( $item['video_id'] ?? '' ) ) ? (string) $item['video_id'] : '';
				$logo  = trim( (string) ( $item['logo'] ?? '' ) );
				$mark  = trim( (string) ( $item['logo_text'] ?? '' ) );
				$style = in_array( $item['logo_style'] ?? '', array( 'hospice', 'osage', 'fresenius' ), true ) ? $item['logo_style'] : '';
				$lw    = $ap_px( $item['logo_w'] ?? null );
				$lh    = $ap_px( $item['logo_h'] ?? null );
				$quote = trim( (string) ( $item['quote'] ?? '' ) );
				?>
				<div class="apc-story apc-slide<?php echo 0 === $n ? ' is-active' : ''; ?><?php echo '' !== $video ? ' apc-slide--video' : ''; ?>"<?php echo 0 === $n ? '' : ' aria-hidden="true"'; ?>>
					<div class="apc-copy">
						<img class="apc-quote-icon" src="<?php echo esc_url( $quote_icon ); ?>" alt="" width="32" height="32" loading="lazy" decoding="async" />
						<?php if ( '' !== trim( (string) ( $item['category'] ?? '' ) ) ) : ?>
							<p class="apc-cat"><?php echo esc_html( $item['category'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) ( $item['title'] ?? '' ) ) ) : ?>
							<h3 class="apc-title"><?php echo esc_html( $item['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== $quote ) : ?><p class="apc-quote"><?php echo ap_ui_brandmarks( esc_html( $quote ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
<?php endif; // Column 0 on purpose: with a quote, the line above prints exactly what it always did (tabs, <p>, newline). ?>
						<?php if ( '' !== trim( (string) ( $item['author'] ?? '' ) ) || '' !== trim( (string) ( $item['role'] ?? '' ) ) ) : ?>
							<div class="apc-author">
								<?php if ( '' !== trim( (string) ( $item['author'] ?? '' ) ) ) : ?>
									<p class="apc-author-name"><?php echo esc_html( $item['author'] ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== trim( (string) ( $item['role'] ?? '' ) ) ) : ?>
									<p class="apc-author-role"><?php echo ap_ui_lines( $item['role'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $item['link_label'] ) && ! empty( $item['link_url'] ) ) : ?>
							<a class="apc-link" href="<?php echo esc_url( $item['link_url'] ); ?>"<?php echo 0 === $n ? '' : ' tabindex="-1"'; ?>><span><?php echo esc_html( $item['link_label'] ); ?></span><img src="<?php echo esc_url( $arrow ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" /></a>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $video ) : ?>
						<?php
						$poster = trim( (string) ( $item['image'] ?? '' ) );
						if ( '' === $poster ) {
							$poster = 'https://i.ytimg.com/vi/' . $video . '/maxresdefault.jpg';
						}
						$label = trim( (string) ( $item['video_title'] ?? '' ) );
						if ( '' === $label ) {
							$label = 'Video testimonial';
						}
						?>
						<div class="apc-media apc-media--video">
							<button class="apc-vid" type="button" data-yt="<?php echo esc_attr( $video ); ?>"
								<?php if ( is_numeric( $item['video_start'] ?? null ) ) : ?>data-start="<?php echo esc_attr( (string) (int) $item['video_start'] ); ?>"<?php endif; ?>
								<?php if ( is_numeric( $item['video_end'] ?? null ) ) : ?>data-end="<?php echo esc_attr( (string) (int) $item['video_end'] ); ?>"<?php endif; ?>
								data-title="<?php echo esc_attr( $label ); ?>"
								aria-label="<?php echo esc_attr( 'Play: ' . $label ); ?>"<?php echo 0 === $n ? '' : ' tabindex="-1"'; ?>>
								<img class="apc-vid-thumb" src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" width="1280" height="720" loading="lazy" decoding="async" />
								<span class="apc-vid-play" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48" focusable="false"><path class="apc-vid-bg" d="M66.5 7.7c-.8-2.9-2.5-5.4-5.4-6.2C55.8.1 34 0 34 0S12.2.1 6.9 1.5C4 2.3 2.3 4.8 1.5 7.7 .1 13 0 24 0 24s.1 11 1.5 16.3c.8 2.9 2.5 5.4 5.4 6.2C12.2 47.9 34 48 34 48s21.8-.1 27.1-1.5c2.9-.8 4.6-3.3 5.4-6.2C67.9 35 68 24 68 24s-.1-11-1.5-16.3z"></path><path d="M45 24 27 14v20" fill="#fff"></path></svg></span>
							</button>
							<?php echo $ap_logo( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
						</div>
					<?php else : ?>
						<div class="apc-media">
							<?php if ( ! empty( $item['image'] ) ) : ?>
								<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" width="992" height="530" loading="lazy" decoding="async" />
							<?php endif; ?>
							<?php if ( '' !== $logo ) : ?>
								<div class="apc-logo"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ?? '' ); ?>" loading="lazy" decoding="async"<?php echo '' !== $lw || '' !== $lh ? ' style="' . esc_attr( ( '' !== $lw ? 'width:' . $lw . ';' : '' ) . ( '' !== $lh ? 'height:' . $lh . ';' : '' ) . 'object-fit:contain' ) . '"' : ''; ?> /></div>
							<?php elseif ( '' !== $mark ) : ?>
								<div class="apc-logo apc-logo--wordmark"><span class="apc-wordmark<?php echo '' !== $style ? ' apc-wordmark--' . esc_attr( $style ) : ''; ?>"><?php echo $ap_wordmark( $mark ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></span></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<div class="apc-controls<?php echo $count < 2 ? ' apc-controls--solo' : ''; ?>">
				<?php if ( $count > 1 ) : ?>
					<div class="apc-arrows">
						<button class="apc-arrow-btn apc-prev" type="button" aria-label="Previous testimonial">&larr;</button>
						<button class="apc-arrow-btn apc-next" type="button" aria-label="Next testimonial">&rarr;</button>
					</div>
					<div class="apc-progress" aria-hidden="true"><span class="apc-progress-bar" style="width:<?php echo esc_attr( (string) round( 100 / $count, 3 ) ); ?>%"></span></div>
					<div class="apc-dots">
						<?php for ( $d = 0; $d < $count; $d++ ) : ?>
							<button class="apc-dot<?php echo 0 === $d ? ' is-active' : ''; ?>" type="button" aria-label="<?php echo esc_attr( 'View testimonial ' . ( $d + 1 ) ); ?>"></button>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $props['viewall_label'] ) && '' !== trim( (string) $props['viewall_url'] ) ) : ?>
					<a class="apc-viewall" href="<?php echo esc_url( $props['viewall_url'] ); ?>"><?php echo esc_html( $props['viewall_label'] ); ?> <img src="<?php echo esc_url( $arrow ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" /></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
