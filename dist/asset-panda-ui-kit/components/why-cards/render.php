<?php
/**
 * why-cards — "one platform, different teams" use-case cards.
 *
 * Matches the live .epx-why section (solution pages, Emergency Preparedness,
 * School Reunification, Help Desk): eyebrow and heading centred over up to
 * three cards whose titles link onward. The cards reveal on scroll through a
 * CSS scroll timeline (no script), as on live.
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
	$items[] = array(
		'title'      => trim( (string) $item['title'] ),
		'body'       => trim( (string) ( $item['body'] ?? '' ) ),
		'url'        => trim( (string) ( $item['url'] ?? '' ) ),
		// Opt-in: with a url, an arrow link under the body carries it (the title stays plain).
		'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
		'icon'       => trim( (string) ( $item['icon'] ?? '' ) ),
		'icon_svg'   => ap_ui_svg( $item['icon_svg'] ?? '' ),
		'icon_alt'   => (string) ( $item['icon_alt'] ?? '' ),
	);
}

// The solution pages' explore-link arrow (as solution-rows draws it).
$ap_why_arrow = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="#5044E1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
// Opt-in grid (more than three cards): 0 keeps today's single row.
$cols    = in_array( (int) $props['columns'], array( 2, 3 ), true ) ? (int) $props['columns'] : 0;
?>
<section class="ap-why-cards ap-why-cards--<?php echo esc_attr( $variant ); ?><?php echo $cols ? ' ap-why-cards--cols ap-why-cards--cols-' . esc_attr( (string) $cols ) : ''; ?>">
	<div class="ap-why-cards__orbs" aria-hidden="true"></div>
	<div class="ap-why-cards__wrap">
		<div class="ap-why-cards__head ap-why-cards__rev">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-why-cards__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-why-cards__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-why-cards__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $items ) : ?>
			<div class="ap-why-cards__grid">
				<?php foreach ( $items as $i => $item ) : ?>
					<article class="ap-why-cards__card ap-why-cards__rev ap-why-cards__rev--d<?php echo esc_attr( (string) min( $i + 1, 3 ) ); ?>" tabindex="0">
						<?php if ( '' !== $item['icon_svg'] ) : ?>
							<span class="ap-why-cards__ico" aria-hidden="true"><?php echo $item['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php elseif ( '' !== $item['icon'] ) : ?>
							<img class="ap-why-cards__ico" src="<?php echo esc_url( $item['icon'] ); ?>" alt="<?php echo esc_attr( $item['icon_alt'] ); ?>" width="32" height="32" loading="lazy" decoding="async" />
						<?php endif; ?>
						<?php $ap_why_link = '' !== $item['url'] && '' !== $item['link_label']; ?>
						<h3>
							<?php if ( '' !== $item['url'] && ! $ap_why_link ) : ?>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></a>
							<?php else : ?>
								<?php echo ap_ui_brandmarks( ap_ui_lines( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?>
							<?php endif; ?>
						</h3>
						<?php if ( '' !== $item['body'] ) : ?>
							<p><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
						<?php if ( $ap_why_link ) : ?>
							<a class="ap-why-cards__link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?><?php echo $ap_why_arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
