<?php
/**
 * integration-hero — hero of an integration detail page.
 *
 * Figma: Integrations Detail Page Template > Hero Section (7318:166961). A
 * centred column (eyebrow, a frosted card pairing the partner logo with the
 * Asset Panda PRO mark, the H1, one button) over the 2026 colour wash. The
 * wash is the Figma background container rendered once to a small WebP
 * (assets/hero-bg.webp) instead of ten 600px-blurred SVG orbs and a 4K photo;
 * a white fade at the foot lets it run into the white section below.
 *
 * The logo pair repeats the H1 ("Slack + Asset Panda PRO"), so it is
 * decoration unless the editor gives the partner logo alt text; then both
 * logos carry alt and the pair reads "Slack + Asset Panda PRO".
 *
 * layout "split" (the 2026-11 detail pages): the copy column — eyebrow, H1,
 * lede, button, all left-aligned — beside the integration video (poster +
 * play link; behaviour.js swaps in the player on click, as integration-detail
 * does for its overview video). The logo card leaves the hero for the page
 * body; with no video it takes the right column instead, so the split hero is
 * never half empty. The poster is the hero image: eager, high fetch priority.
 * video_thumb "logos" draws the frame instead: the logo card on the hero's
 * colour wash, sized in container units so it scales like a picture, with the
 * play button under the card (decorative — the play link names the video).
 * Below 1025px the video stacks under the copy.
 *
 * NOTE: first-screen section — no data-ap-reveal (motion.js is a footer
 * script). The copy rises in on load from style.css instead.
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

$eyebrow    = trim( (string) $props['eyebrow'] );
$lede       = trim( (string) $props['lede'] );
$logo       = trim( (string) $props['logo'] );
$logo_alt   = trim( (string) $props['logo_alt'] );
$brand_logo = trim( (string) $props['brand_logo'] );
$brand_alt  = '' !== $logo_alt ? trim( (string) $props['brand_logo_alt'] ) : '';
$has_cta    = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$split      = 'split' === $props['layout'];

if ( '' === $brand_logo ) {
	$brand_logo = ap_ui_asset_url( 'integration-hero', 'panda-pro.svg' );
}

// Split layout: the video on the right. The player URL for a link: YouTube
// (nocookie), Vimeo, or a file — the same rules as integration-detail.
$v_url    = trim( (string) $props['video_url'] );
$v_poster = trim( (string) $props['video_poster'] );
$v_title  = trim( (string) $props['video_title'] );
$v_embed  = null;
if ( $split && '' !== $v_url ) {
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $v_url, $m ) ) {
		$v_embed = array(
			'kind' => 'iframe',
			'src'  => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0&playsinline=1',
		);
	} elseif ( preg_match( '~vimeo\.com/(?:video/)?([0-9]{6,12})(?:/([0-9a-f]{6,20}))?~i', $v_url, $m ) ) {
		$v_embed = array(
			'kind' => 'iframe',
			'src'  => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1' . ( ! empty( $m[2] ) ? '&h=' . $m[2] : '' ),
		);
	} elseif ( preg_match( '~^(?:https?://|/)[^\s"\'<>]+\.(?:mp4|webm|m4v|mov)(?:[?#][^\s"\'<>]*)?$~i', $v_url ) ) {
		$v_embed = array(
			'kind' => 'video',
			'src'  => $v_url,
		);
	}
}
// video_thumb "logos": the frame shows the logo card on the colour wash instead of a poster image.
$v_logos   = $split && $v_embed && 'logos' === $props['video_thumb'] && '' !== $logo;
$has_media = $split && ( '' !== $v_poster || $v_embed );

// The frosted logo card (centre layout; split layout only when there is no video).
$ap_ihero_card = static function () use ( $logo, $logo_alt, $brand_logo, $brand_alt ) {
	?>
	<div class="ap-integration-hero__card"<?php echo '' === $logo_alt ? ' aria-hidden="true"' : ''; ?>>
		<?php if ( '' !== $logo ) : ?>
			<img class="ap-integration-hero__logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" width="64" height="64" decoding="async" />
			<img class="ap-integration-hero__plus" src="<?php echo esc_url( ap_ui_asset_url( 'integration-hero', 'plus.svg' ) ); ?>" alt="<?php echo '' !== $logo_alt ? '+' : ''; ?>" width="9" height="9" decoding="async" />
		<?php endif; ?>
		<?php if ( '' !== $brand_logo ) : ?>
			<img class="ap-integration-hero__brand" src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_alt ); ?>" width="61" height="64" decoding="async" />
		<?php endif; ?>
	</div>
	<?php
};

// H1, lede and button (both layouts).
$ap_ihero_text = static function () use ( $title, $lede, $has_cta, $props ) {
	?>
	<h1 class="ap-integration-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h1>

	<?php if ( '' !== $lede ) : ?>
		<p class="ap-integration-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
	<?php endif; ?>

	<?php if ( $has_cta ) : ?>
		<div class="ap-integration-hero__actions">
			<a class="ap-integration-hero__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
		</div>
	<?php endif; ?>
	<?php
};
?>
<?php if ( $split ) : ?>
<section class="ap-integration-hero ap-integration-hero--split"<?php echo $v_embed ? ' data-ap-integration-hero' : ''; ?>>
	<div class="ap-integration-hero__inner">
		<div class="ap-integration-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-integration-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<?php $ap_ihero_text(); ?>
		</div>

		<?php if ( $has_media ) : ?>
			<div class="ap-integration-hero__media<?php echo $v_logos ? ' ap-integration-hero__media--logos' : ( '' === $v_poster ? ' ap-integration-hero__media--bare' : '' ); ?>">
				<?php if ( $v_logos ) : ?>
					<div class="ap-integration-hero__thumb" aria-hidden="true">
						<div class="ap-integration-hero__thumb-card">
							<img class="ap-integration-hero__thumb-logo" src="<?php echo esc_url( $logo ); ?>" alt="" width="96" height="96" fetchpriority="high" decoding="async" />
							<img class="ap-integration-hero__thumb-plus" src="<?php echo esc_url( ap_ui_asset_url( 'integration-hero', 'plus.svg' ) ); ?>" alt="" width="12" height="12" decoding="async" />
							<img class="ap-integration-hero__thumb-brand" src="<?php echo esc_url( $brand_logo ); ?>" alt="" width="92" height="96" decoding="async" />
						</div>
					</div>
				<?php elseif ( '' !== $v_poster ) : ?>
					<img class="ap-integration-hero__poster" src="<?php echo esc_url( $v_poster ); ?>" alt="<?php echo esc_attr( $v_embed ? '' : $v_title ); ?>" width="1440" height="810" loading="eager" fetchpriority="high" decoding="async" />
				<?php endif; ?>
				<?php if ( $v_embed ) : ?>
					<a class="ap-integration-hero__play" href="<?php echo esc_url( $v_url ); ?>" data-ap-ihero-play data-kind="<?php echo esc_attr( $v_embed['kind'] ); ?>" data-embed="<?php echo esc_url( $v_embed['src'] ); ?>" data-title="<?php echo esc_attr( '' !== $v_title ? $v_title : 'Product video' ); ?>" aria-label="<?php echo esc_attr( '' !== $v_title ? 'Play video: ' . $v_title : 'Play video' ); ?>">
						<span class="ap-integration-hero__play-btn" aria-hidden="true">
							<img src="<?php echo esc_url( ap_ui_asset_url( 'integration-hero', 'play-triangle.svg' ) ); ?>" alt="" width="20" height="16" decoding="async" />
						</span>
					</a>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="ap-integration-hero__aside"><?php $ap_ihero_card(); ?></div>
		<?php endif; ?>
	</div>
</section>
<?php else : ?>
<section class="ap-integration-hero">
	<div class="ap-integration-hero__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-integration-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>

		<?php $ap_ihero_card(); ?>

		<?php $ap_ihero_text(); ?>
	</div>
</section>
<?php endif; ?>
