<?php
/**
 * hero — page hero.
 *
 * Matches the live homepage hero (dev page 7, #ap-home-hero-v2), which was
 * built from Figma Home Page > Hero (4331:28767): a centred 800px column
 * (eyebrow, H1 with a gradient accent line, bold lede, body, CTAs, compliance
 * badges) over six blurred colour orbs; an 848px frosted frame holding the
 * product video; a white client-logo strip overlapping the frame's lower edge.
 * On phones the logo strip runs as a continuous marquee, so the list renders
 * twice — the second copy is aria-hidden and only shown there.
 *
 * NOTE: this section sits at the top of the page, so it deliberately carries no
 * data-ap-reveal — motion.js is a footer script and above-the-fold reveals
 * would flash. Entrance motion runs on load, from style.css.
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

$eyebrow      = trim( (string) $props['eyebrow'] );
$accent       = trim( (string) $props['title_accent'] );
$lede         = trim( (string) $props['lede'] );
$subtitle     = trim( (string) $props['subtitle'] );
$video        = trim( (string) $props['media_video'] );
$poster       = trim( (string) $props['media_image'] );
$badges_label = trim( (string) $props['badges_label'] );
$logos_label  = trim( (string) $props['logos_label'] );
$logos_accent = trim( (string) $props['logos_accent'] );

$has_primary   = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$has_secondary = '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] );

$with_image = static function ( $list ) {
	return array_values(
		array_filter(
			is_array( $list ) ? $list : array(),
			static function ( $x ) {
				return is_array( $x ) && ! empty( $x['image'] );
			}
		)
	);
};

$badges = $with_image( $props['badges'] );
$logos  = $with_image( $props['logos'] );
$orbs   = array( 'red', 'indigo', 'violet', 'yellow', 'green', 'gray' );

$classes = array(
	'ap-hero',
	'ap-hero--' . $props['theme'],
	'ap-hero--align-' . $props['alignment'],
);
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<div class="ap-hero__decor" aria-hidden="true">
		<div class="ap-hero__orbs">
			<?php foreach ( $orbs as $orb ) : ?>
				<img class="ap-hero__orb ap-hero__orb--<?php echo esc_attr( $orb ); ?>" src="<?php echo esc_url( ap_ui_asset_url( 'hero', 'orb-' . $orb . '.svg' ) ); ?>" alt="" />
			<?php endforeach; ?>
		</div>
	</div>

	<div class="ap-hero__inner">
		<div class="ap-hero__copy">
			<div class="ap-hero__text">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h1 class="ap-hero__title">
					<?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?>
					<?php if ( '' !== $accent ) : ?>
						<span class="ap-hero__accent"><?php echo esc_html( $accent ); ?></span>
					<?php endif; ?>
				</h1>
				<?php if ( '' !== $lede || '' !== $subtitle ) : ?>
					<div class="ap-hero__body">
						<?php if ( '' !== $lede ) : ?>
							<p class="ap-hero__lede"><?php echo esc_html( $lede ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $subtitle ) : ?>
							<p class="ap-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $has_primary || $has_secondary || ! empty( $badges ) ) : ?>
				<div class="ap-hero__trust">
					<?php if ( $has_primary || $has_secondary ) : ?>
						<div class="ap-hero__actions">
							<?php if ( $has_primary ) : ?>
								<a class="ap-hero__btn ap-hero__btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
							<?php endif; ?>
							<?php if ( $has_secondary ) : ?>
								<a class="ap-hero__btn ap-hero__btn--outline<?php echo ! empty( $props['secondary_mobile_only'] ) ? ' ap-hero__btn--mobile-only' : ''; ?>" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $badges ) ) : ?>
						<div class="ap-hero__badges">
							<?php if ( '' !== $badges_label ) : ?>
								<p class="ap-hero__badges-label"><?php echo esc_html( $badges_label ); ?></p>
							<?php endif; ?>
							<ul class="ap-hero__badges-list">
								<?php foreach ( $badges as $badge ) : ?>
									<li><img class="ap-hero__badge" src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" /></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $video || '' !== $poster ) : ?>
			<div class="ap-hero__frame">
				<div class="ap-hero__screen">
					<?php if ( '' !== $video ) : ?>
						<video class="ap-hero__media" data-ap-hero-video
							src="<?php echo esc_url( $video ); ?>"
							<?php if ( '' !== $poster ) : ?>
								poster="<?php echo esc_url( $poster ); ?>"
							<?php endif; ?>
							aria-label="<?php echo esc_attr( $props['media_alt'] ); ?>"
							autoplay muted loop playsinline preload="metadata"></video>
					<?php else : ?>
						<img class="ap-hero__media" src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $props['media_alt'] ); ?>" fetchpriority="high" decoding="async" />
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $logos ) || '' !== $logos_label ) : ?>
		<div class="ap-hero__logos">
			<?php if ( '' !== $logos_label ) : ?>
				<p class="ap-hero__logos-label">
					<?php echo esc_html( $logos_label ); ?>
					<?php if ( '' !== $logos_accent ) : ?>
						<span class="ap-hero__logos-accent"><?php echo esc_html( $logos_accent ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $logos ) ) : ?>
				<div class="ap-hero__logos-viewport">
					<ul class="ap-hero__logos-list">
						<?php foreach ( array( false, true ) as $is_copy ) : ?>
							<?php foreach ( $logos as $logo ) : ?>
								<li<?php echo $is_copy ? ' class="ap-hero__logo-dup" aria-hidden="true"' : ''; ?>><img class="ap-hero__logo" src="<?php echo esc_url( $logo['image'] ); ?>" alt="<?php echo esc_attr( $is_copy ? '' : ( $logo['alt'] ?? '' ) ); ?>" loading="lazy" decoding="async" /></li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
