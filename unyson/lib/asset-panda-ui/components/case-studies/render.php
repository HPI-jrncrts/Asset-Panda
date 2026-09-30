<?php
/**
 * case-studies — customer story slider.
 *
 * Matches the live homepage (dev page 7, #ap-case-studies-v1), built from
 * Figma Case Studies (4331:24818). Desktop: the photo occupies the right 83%
 * of a 488px slide and a white card (57% wide) overlaps it from the left, 58px
 * down; phones: the photo sits on top and the card overlaps it by 40px. The
 * author's logo sits beside the author (under it on phones). Arrows, a rule
 * and dots sit beneath; behaviour.js loops and autoplays every 5 seconds.
 *
 * The track is a scroll-snap list, so it works with keyboard, touch and
 * trackpad before behaviour.js runs. Controls render hidden and are revealed
 * by the script — a control that cannot work must not be visible.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && ( ! empty( $i['title'] ) || ! empty( $i['quote'] ) );
		}
	)
);

if ( '' === $title || empty( $items ) ) {
	return;
}

$eyebrow = trim( (string) $props['eyebrow'] );
$intro   = trim( (string) $props['intro'] );
$reveal  = (bool) $props['reveal'];
$has_cta = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$count   = count( $items );
?>
<section class="ap-cases" data-ap-cases>
	<div class="ap-cases__inner">
		<div class="ap-cases__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<div class="ap-cases__headings">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-cases__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-cases__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
				<?php if ( '' !== $intro ) : ?>
					<p class="ap-cases__intro"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $has_cta ) : ?>
				<a class="ap-cases__plain" href="<?php echo esc_url( $props['cta_url'] ); ?>">
					<span><?php echo esc_html( $props['cta_label'] ); ?></span>
					<img src="<?php echo esc_url( ap_ui_asset_url( 'case-studies', 'arrow.svg' ) ); ?>" alt="" width="24" height="24" />
				</a>
			<?php endif; ?>
		</div>

		<div class="ap-cases__slider"<?php echo $reveal ? ' data-ap-reveal="fade" data-ap-reveal-delay="1"' : ''; ?>>
			<ul class="ap-cases__track" data-ap-cases-track tabindex="0" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php
				foreach ( $items as $i => $item ) :
					$has_logo   = ! empty( $item['logo'] );
					$has_author = ! empty( $item['author'] );
					?>
					<li class="ap-cases__slide" aria-label="<?php echo esc_attr( sprintf( '%d / %d', $i + 1, $count ) ); ?>">
						<?php if ( ! empty( $item['image'] ) ) : ?>
							<div class="ap-cases__media">
								<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ?? '' ); ?>" loading="lazy" decoding="async" />
							</div>
						<?php endif; ?>
						<article class="ap-cases__card">
							<?php if ( ! empty( $item['category'] ) ) : ?>
								<span class="ap-cases__tag"><?php echo esc_html( $item['category'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $item['title'] ) ) : ?>
								<h3 class="ap-cases__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( ! empty( $item['quote'] ) || $has_author || $has_logo ) : ?>
								<div class="ap-cases__quote">
									<img class="ap-cases__quote-icon" src="<?php echo esc_url( ap_ui_asset_url( 'case-studies', 'quote.svg' ) ); ?>" alt="" width="32" height="32" />
									<div class="ap-cases__quote-text">
										<?php if ( ! empty( $item['quote'] ) ) : ?>
											<blockquote><p><?php echo esc_html( $item['quote'] ); ?></p></blockquote>
										<?php endif; ?>
										<?php if ( $has_author || $has_logo ) : ?>
											<div class="ap-cases__author">
												<?php if ( $has_author ) : ?>
													<p class="ap-cases__author-text">
														<span class="ap-cases__author-name"><?php echo esc_html( $item['author'] ); ?></span>
														<?php if ( ! empty( $item['role'] ) ) : ?>
															<span class="ap-cases__role"><?php echo esc_html( $item['role'] ); ?></span>
														<?php endif; ?>
													</p>
												<?php endif; ?>
												<?php if ( $has_logo ) : ?>
													<img class="ap-cases__logo" src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ?? '' ); ?>" width="88" height="56" loading="lazy" decoding="async" />
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $item['link_label'] ) && ! empty( $item['link_url'] ) ) : ?>
								<div class="ap-cases__footer">
									<a class="ap-cases__read" href="<?php echo esc_url( $item['link_url'] ); ?>">
										<span><?php echo esc_html( $item['link_label'] ); ?></span>
										<img src="<?php echo esc_url( ap_ui_asset_url( 'case-studies', 'arrow-dark.svg' ) ); ?>" alt="" width="24" height="24" />
									</a>
								</div>
							<?php endif; ?>
						</article>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if ( $count > 1 ) : ?>
				<div class="ap-cases__controls" data-ap-cases-controls hidden>
					<div class="ap-cases__arrows">
						<button type="button" class="ap-cases__arrow" data-ap-cases-prev aria-label="Previous story">
							<img src="<?php echo esc_url( ap_ui_asset_url( 'case-studies', 'prev.svg' ) ); ?>" alt="" width="40" height="40" />
						</button>
						<button type="button" class="ap-cases__arrow" data-ap-cases-next aria-label="Next story">
							<img src="<?php echo esc_url( ap_ui_asset_url( 'case-studies', 'next.svg' ) ); ?>" alt="" width="40" height="40" />
						</button>
					</div>
					<span class="ap-cases__rule" aria-hidden="true"></span>
					<div class="ap-cases__dots">
						<?php for ( $d = 0; $d < $count; $d++ ) : ?>
							<button type="button" class="ap-cases__dot" data-ap-cases-dot="<?php echo esc_attr( (string) $d ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Show story %d', $d + 1 ) ); ?>"></button>
						<?php endfor; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
