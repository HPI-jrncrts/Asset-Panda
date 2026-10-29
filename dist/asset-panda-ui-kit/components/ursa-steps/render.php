<?php
/**
 * ursa-steps — "Ask. Build. Analyze. Act." (Ursa AI frame 7261:111601).
 *
 * A centred 640px heading block over full-width cards (1120px). Each card: the
 * numbered label ("01 ASK": number in indigo, word in green) and a 24px title in
 * a 360px column beside the copy, then a ruled two-column checklist with green
 * check icons (style.css). A dark card (indigo 600 to 950) drops the checklist and shows the
 * Ursa orb on the right. Icons and the orb are the kit's (assets/).
 *
 * No scroll motion (QA 2026-10-29): the cards are simply present. motion.js's
 * auto-reveal would otherwise fade and rise the heading block and each card as
 * they scroll in, so the section is marked up in the motion layer's own
 * "already revealed" state — data-ap-reveal with .ap-inview.ap-reveal-done —
 * which the runtime leaves alone (nothing inside it is picked, the observer
 * ignores it and motion.css never applies the hidden pre-state).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$checks = array();
	foreach ( is_array( $item['checks'] ?? null ) ? $item['checks'] : array() as $check ) {
		$check = trim( (string) ( is_array( $check ) ? ( $check['text'] ?? '' ) : $check ) );
		if ( '' !== $check ) {
			$checks[] = $check;
		}
	}
	$items[] = array(
		'num'    => trim( (string) ( $item['num'] ?? '' ) ),
		'label'  => trim( (string) ( $item['label'] ?? '' ) ),
		'title'  => trim( (string) $item['title'] ),
		'body'   => trim( (string) ( $item['body'] ?? '' ) ),
		'checks' => $checks,
		'dark'   => 'dark' === ( $item['tone'] ?? '' ),
	);
}
if ( empty( $items ) ) {
	return;
}

$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
?>
<section class="ap-ursa-steps ap-inview ap-reveal-done" data-ap-reveal aria-label="<?php echo esc_attr( wp_strip_all_tags( $props['title'] ) ); ?>">
	<div class="ap-ursa-steps__wrap">
		<div class="ap-ursa-steps__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-ursa-steps__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-ursa-steps__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-ursa-steps__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
		</div>
		<div class="ap-ursa-steps__cards">
			<?php foreach ( $items as $item ) : ?>
				<article class="ap-ursa-steps__card<?php echo $item['dark'] ? ' ap-ursa-steps__card--dark' : ''; ?>">
					<?php if ( $item['dark'] ) : ?>
						<img class="ap-ursa-steps__glow" src="<?php echo esc_url( ap_ui_asset_url( 'ursa-steps', 'glow-green.svg' ) ); ?>" alt="" width="729" height="639" loading="lazy" decoding="async" />
					<?php endif; ?>
					<div class="ap-ursa-steps__top">
						<div class="ap-ursa-steps__lead">
							<?php if ( '' !== $item['num'] || '' !== $item['label'] ) : ?>
								<p class="ap-ursa-steps__kicker"><?php if ( '' !== $item['num'] ) : ?><span class="ap-ursa-steps__num"><?php echo esc_html( $item['num'] ); ?></span> <?php endif; ?><?php echo esc_html( $item['label'] ); ?></p>
							<?php endif; ?>
							<h3 class="ap-ursa-steps__ct"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						</div>
						<?php if ( '' !== $item['body'] ) : ?>
							<p class="ap-ursa-steps__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
						<?php endif; ?>
						<?php if ( $item['dark'] ) : ?>
							<span class="ap-ursa-steps__orb" aria-hidden="true"><img src="<?php echo esc_url( ap_ui_asset_url( 'ursa-steps', 'orb.svg' ) ); ?>" alt="" width="191" height="199" loading="lazy" decoding="async" /></span>
						<?php endif; ?>
					</div>
					<?php if ( $item['checks'] ) : ?>
						<div class="ap-ursa-steps__checks">
							<?php foreach ( array_chunk( $item['checks'], (int) ceil( count( $item['checks'] ) / 2 ) ) as $column ) : ?>
								<ul class="ap-ursa-steps__list">
									<?php foreach ( $column as $text ) : ?>
										<li><?php echo ap_ui_brandmarks( esc_html( $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
