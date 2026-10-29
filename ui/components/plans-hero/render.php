<?php
/**
 * plans-hero — the pricing v2 hero.
 *
 * Built from the Asset Panda Pricing v2 mockup: an optional notice strip, then
 * a centred eyebrow, a two-line h1 (the second line in the brand gradient), a
 * bold sub line, a lead, two buttons and the compliance badges, with the client
 * logo band closing the section.
 *
 * The mockup pins the logo band to the bottom of the hero with absolute
 * positioning. Here it sits in normal flow, so a band that wraps onto several
 * rows on a phone pushes the section taller instead of covering the buttons.
 *
 * Images may be full URLs or bare file names from this component's assets/
 * folder, so the mockup's art ships with the kit.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// layout "trust" is the proof row on its own (client logos, then compliance
// badges), for pages that open on plans-picker instead of this hero. layout
// "split" is the service-page hero: copy left, the Book a Demo form right.
$layout  = in_array( $props['layout'], array( 'trust', 'split' ), true ) ? $props['layout'] : 'hero';
$variant = 'clean' === $props['variant'] ? 'clean' : 'classic';

$title = trim( (string) $props['title'] );
if ( 'trust' !== $layout && '' === $title ) {
	return;
}

// A bare file name resolves to this component's bundled asset; anything else
// is treated as a URL and escaped at output.
$ap_src = static function ( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:svg|webp|png|jpe?g)$/i', $value ) ) {
		return ap_ui_asset_url( 'plans-hero', $value );
	}
	return $value;
};

// Rows of { image, alt, h }; h is the display height in px.
$ap_images = static function ( $rows ) use ( $ap_src ) {
	$out = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$src = $ap_src( $row['image'] ?? '' );
		if ( '' === $src ) {
			continue;
		}
		$h     = (int) ( $row['h'] ?? 0 );
		$out[] = array(
			'src' => $src,
			'alt' => trim( (string) ( $row['alt'] ?? '' ) ),
			'h'   => ( $h > 0 && $h <= 200 ) ? $h : 0,
		);
	}
	return $out;
};

$notice_label = trim( (string) $props['notice_label'] );
$notice       = trim( (string) $props['notice'] );
$eyebrow      = trim( (string) $props['eyebrow'] );
$accent       = trim( (string) $props['title_accent'] );
$subtitle     = trim( (string) $props['subtitle'] );
$lede         = trim( (string) $props['lede'] );
$cta_label    = trim( (string) $props['cta_label'] );
$cta_url      = trim( (string) $props['cta_url'] );
$alt_label    = trim( (string) $props['secondary_label'] );
$alt_url      = trim( (string) $props['secondary_url'] );
$badges_label = trim( (string) $props['badges_label'] );
$band_text    = trim( (string) $props['band_text'] );
$band_accent  = trim( (string) $props['band_accent'] );
$background   = $ap_src( $props['background'] );
$badges       = $ap_images( $props['badges'] );
$logos        = $ap_images( $props['logos'] );
$has_cta      = '' !== $cta_label && '' !== $cta_url;
$has_alt      = '' !== $alt_label && '' !== $alt_url;
$has_band     = '' !== $band_text || '' !== $band_accent || ! empty( $logos );

if ( 'trust' === $layout ) :
	if ( empty( $badges ) && ! $has_band ) {
		return;
	}
	?>
	<section class="ap-plans-hero ap-plans-hero--trust ap-plans-hero--<?php echo esc_attr( $variant ); ?>">
		<div class="ap-plans-hero__trust">
			<?php if ( '' !== $band_text || '' !== $band_accent ) : ?>
				<p class="ap-plans-hero__band-text">
					<?php echo esc_html( $band_text ); ?>
					<?php if ( '' !== $band_accent ) : ?>
						<strong><?php echo esc_html( $band_accent ); ?></strong>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $logos ) ) : ?>
				<ul class="ap-plans-hero__logos">
					<?php foreach ( $logos as $logo ) : ?>
						<li><img src="<?php echo esc_url( $logo['src'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ); ?>"<?php echo $logo['h'] ? ' style="height:' . (int) $logo['h'] . 'px"' : ''; ?> loading="lazy" decoding="async" /></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $badges ) ) : ?>
				<div class="ap-plans-hero__comp">
					<?php if ( '' !== $badges_label ) : ?>
						<p class="ap-plans-hero__comp-label"><?php echo esc_html( $badges_label ); ?></p>
					<?php endif; ?>
					<ul class="ap-plans-hero__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<li><img src="<?php echo esc_url( $badge['src'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ); ?>"<?php echo $badge['h'] ? ' style="height:' . (int) $badge['h'] . 'px"' : ''; ?> loading="lazy" decoding="async" /></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return;
endif;

$bg_style = '' !== $background ? ' style="' . esc_attr( '--ap-plans-hero-bg:url("' . esc_url( $background ) . '")' ) . '"' : '';
// First-screen backdrop (a CSS background): preload it (PageSpeed LCP).
if ( '' !== $background && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $background ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() in the loader.
}

if ( 'split' === $layout ) :
	/*
	 * The form is the live Book a Demo widget: the demo-booking component,
	 * rendered without its heading (this hero owns the page's h1) and without
	 * its stats, awards or background. Only its widget settings pass through.
	 */
	$form_title = trim( (string) $props['form_title'] );
	$form_lead  = trim( (string) $props['form_lead'] );
	$form_html  = '';
	$form       = is_array( $props['form'] ) ? $props['form'] : array();
	if ( ! empty( $form ) ) {
		$form_keys  = array( 'view', 'button_label', 'days_heading', 'times_heading', 'duration_label', 'compliance_label', 'compliance', 'cal_team', 'cal_user', 'cal_event', 'max_days', 'hs_portal', 'hs_form', 'guard_url', 'track_path', 'thanks_url', 'thanks_preview_url', 'contact_email', 'contact_phone' );
		$form_props = array_intersect_key( $form, array_flip( $form_keys ) );
		$form_html  = ap_render_component(
			'demo-booking',
			array_merge(
				$form_props,
				array(
					'layout' => 'centered',
					'title'  => '',
					'lead'   => '',
				)
			)
		);
	}
	$has_form = '' !== trim( $form_html );
	?>
	<section class="ap-plans-hero ap-plans-hero--split ap-plans-hero--<?php echo esc_attr( $variant ); ?>"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr( esc_url() ) above. ?>>
		<?php if ( '' !== $notice_label || '' !== $notice ) : ?>
			<p class="ap-plans-hero__notice">
				<?php if ( '' !== $notice_label ) : ?>
					<b><?php echo esc_html( $notice_label ); ?></b>
				<?php endif; ?>
				<?php if ( '' !== $notice_label && '' !== $notice ) : ?>
					<span class="ap-plans-hero__notice-dot" aria-hidden="true">&middot;</span>
				<?php endif; ?>
				<?php echo esc_html( $notice ); ?>
			</p>
		<?php endif; ?>

		<div class="ap-plans-hero__stage ap-plans-hero__split<?php echo $has_form ? '' : ' is-copy-only'; ?>">
			<div class="ap-plans-hero__copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-plans-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h1 class="ap-plans-hero__title">
					<span><?php echo esc_html( $title ); ?></span>
					<?php if ( '' !== $accent ) : ?>
						<span class="ap-plans-hero__accent"><?php echo esc_html( $accent ); ?></span>
					<?php endif; ?>
				</h1>
				<?php if ( '' !== $subtitle ) : ?>
					<p class="ap-plans-hero__sub"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $lede ) : ?>
					<p class="ap-plans-hero__lede"><?php echo esc_html( $lede ); ?></p>
				<?php endif; ?>
				<?php if ( $has_cta || $has_alt ) : ?>
					<div class="ap-plans-hero__ctas">
						<?php if ( $has_cta ) : ?>
							<a class="ap-plans-hero__btn ap-plans-hero__btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
						<?php endif; ?>
						<?php if ( $has_alt ) : ?>
							<a class="ap-plans-hero__btn ap-plans-hero__btn--outline" href="<?php echo esc_url( $alt_url ); ?>"><?php echo esc_html( $alt_label ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $badges ) ) : ?>
					<div class="ap-plans-hero__comp">
						<?php if ( '' !== $badges_label ) : ?>
							<p class="ap-plans-hero__comp-label"><?php echo esc_html( $badges_label ); ?></p>
						<?php endif; ?>
						<ul class="ap-plans-hero__badges">
							<?php foreach ( $badges as $badge ) : ?>
								<li><img src="<?php echo esc_url( $badge['src'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ); ?>"<?php echo $badge['h'] ? ' style="height:' . (int) $badge['h'] . 'px"' : ''; ?> decoding="async" /></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $has_form ) : ?>
				<div class="ap-plans-hero__form">
					<?php if ( '' !== $form_title ) : ?>
						<h2 class="ap-plans-hero__form-title"><?php echo esc_html( $form_title ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $form_lead ) : ?>
						<p class="ap-plans-hero__form-lead"><?php echo esc_html( $form_lead ); ?></p>
					<?php endif; ?>
					<?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput -- the demo-booking component escapes its own output. ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $has_band ) : ?>
			<div class="ap-plans-hero__band">
				<?php if ( '' !== $band_text || '' !== $band_accent ) : ?>
					<p class="ap-plans-hero__band-text">
						<?php echo esc_html( $band_text ); ?>
						<?php if ( '' !== $band_accent ) : ?>
							<strong><?php echo esc_html( $band_accent ); ?></strong>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $logos ) ) : ?>
					<ul class="ap-plans-hero__logos">
						<?php foreach ( $logos as $logo ) : ?>
							<li><img src="<?php echo esc_url( $logo['src'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ); ?>"<?php echo $logo['h'] ? ' style="height:' . (int) $logo['h'] . 'px"' : ''; ?> loading="lazy" decoding="async" /></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return;
endif;
?>
<section class="ap-plans-hero"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr( esc_url() ) above. ?>>
	<?php if ( '' !== $notice_label || '' !== $notice ) : ?>
		<p class="ap-plans-hero__notice">
			<?php if ( '' !== $notice_label ) : ?>
				<b><?php echo esc_html( $notice_label ); ?></b>
			<?php endif; ?>
			<?php if ( '' !== $notice_label && '' !== $notice ) : ?>
				<span class="ap-plans-hero__notice-dot" aria-hidden="true">&middot;</span>
			<?php endif; ?>
			<?php echo esc_html( $notice ); ?>
		</p>
	<?php endif; ?>

	<div class="ap-plans-hero__stage">
		<div class="ap-plans-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-plans-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h1 class="ap-plans-hero__title">
				<span><?php echo esc_html( $title ); ?></span>
				<?php if ( '' !== $accent ) : ?>
					<span class="ap-plans-hero__accent"><?php echo esc_html( $accent ); ?></span>
				<?php endif; ?>
			</h1>
			<?php if ( '' !== $subtitle ) : ?>
				<p class="ap-plans-hero__sub"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-plans-hero__lede"><?php echo esc_html( $lede ); ?></p>
			<?php endif; ?>

			<?php if ( $has_cta || $has_alt ) : ?>
				<div class="ap-plans-hero__ctas">
					<?php if ( $has_cta ) : ?>
						<a class="ap-plans-hero__btn ap-plans-hero__btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					<?php endif; ?>
					<?php if ( $has_alt ) : ?>
						<a class="ap-plans-hero__btn ap-plans-hero__btn--outline" href="<?php echo esc_url( $alt_url ); ?>"><?php echo esc_html( $alt_label ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $badges ) ) : ?>
				<div class="ap-plans-hero__comp">
					<?php if ( '' !== $badges_label ) : ?>
						<p class="ap-plans-hero__comp-label"><?php echo esc_html( $badges_label ); ?></p>
					<?php endif; ?>
					<ul class="ap-plans-hero__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<li><img src="<?php echo esc_url( $badge['src'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ); ?>"<?php echo $badge['h'] ? ' style="height:' . (int) $badge['h'] . 'px"' : ''; ?> decoding="async" /></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $has_band ) : ?>
		<div class="ap-plans-hero__band">
			<?php if ( '' !== $band_text || '' !== $band_accent ) : ?>
				<p class="ap-plans-hero__band-text">
					<?php echo esc_html( $band_text ); ?>
					<?php if ( '' !== $band_accent ) : ?>
						<strong><?php echo esc_html( $band_accent ); ?></strong>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $logos ) ) : ?>
				<ul class="ap-plans-hero__logos">
					<?php foreach ( $logos as $logo ) : ?>
						<li><img src="<?php echo esc_url( $logo['src'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ); ?>"<?php echo $logo['h'] ? ' style="height:' . (int) $logo['h'] . 'px"' : ''; ?> loading="lazy" decoding="async" /></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
