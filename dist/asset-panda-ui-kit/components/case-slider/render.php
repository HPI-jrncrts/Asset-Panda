<?php
/**
 * case-slider — customer stories, one at a time.
 *
 * Matches the live #ap-<page>-case section on the solution pages and Help Desk:
 * the markup is live's own (.apg-case-*), so the ported sheet applies as it
 * does there. Every slide is in the markup and stacked in one grid cell; only
 * the .is-active one shows. behaviour.js adds the dots and moves between slides
 * (as live's inline script does); without it the first story simply stays up.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['quote'] ?? '' ) ) ) {
		continue;
	}
	$items[] = array(
		'title'      => trim( (string) ( $item['title'] ?? '' ) ),
		'quote'      => trim( (string) $item['quote'] ),
		'author'     => trim( (string) ( $item['author'] ?? '' ) ),
		'role'       => trim( (string) ( $item['role'] ?? '' ) ),
		'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
		'link_url'   => trim( (string) ( $item['link_url'] ?? '' ) ),
		'image'      => trim( (string) ( $item['image'] ?? '' ) ),
		'image_alt'  => (string) ( $item['image_alt'] ?? '' ),
		'logo'       => trim( (string) ( $item['logo'] ?? '' ) ),
		'logo_alt'   => (string) ( $item['logo_alt'] ?? '' ),
		'logo_text'  => trim( (string) ( $item['logo_text'] ?? '' ) ),
		'logo_style' => in_array( $item['logo_style'] ?? '', array( 'hospice', 'osage', 'fresenius' ), true ) ? $item['logo_style'] : '',
		'logo_w'     => is_numeric( $item['logo_w'] ?? null ) && (float) $item['logo_w'] > 0 ? round( (float) $item['logo_w'], 1 ) : 0,
		'logo_h'     => is_numeric( $item['logo_h'] ?? null ) && (float) $item['logo_h'] > 0 ? round( (float) $item['logo_h'], 1 ) : 0,
	);
}

if ( empty( $items ) ) {
	return;
}

$variant  = in_array( $props['variant'], array( 'solution', 'service', 'hub', 'hub-industry' ), true ) ? $props['variant'] : 'solution';
// The industry hubs (live #ap-gov-case): logo badges (image or wordmark) over the photo, the
// story title as .apg-tst-title, View All as a plain link at the end of the controls row.
$hub      = in_array( $variant, array( 'hub', 'hub-industry' ), true );
$eyebrow  = trim( (string) $props['eyebrow'] );
$lede     = trim( (string) $props['lede'] );
$arrow    = esc_url( ap_ui_asset_url( 'case-slider', 'icon-arrow.svg' ) );
$quote    = esc_url( '' !== trim( (string) $props['quote_icon'] ) ? trim( (string) $props['quote_icon'] ) : ap_ui_asset_url( 'case-slider', 'quote-up.svg' ) );
$progress = round( 100 / count( $items ), 3 );

// View All: in the controls row on the solution pages, centred under them on Help Desk.
$viewall = '';
if ( $hub && '' !== trim( (string) $props['viewall_label'] ) && '' !== trim( (string) $props['viewall_url'] ) ) {
	$viewall = sprintf(
		'<a class="apg-case-cta apg-case-cta--all" href="%1$s"><span>%2$s</span><img decoding="async" src="%3$s" alt="" /></a>',
		esc_url( $props['viewall_url'] ),
		esc_html( $props['viewall_label'] ),
		$arrow // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above.
	);
} elseif ( '' !== trim( (string) $props['viewall_label'] ) && '' !== trim( (string) $props['viewall_url'] ) ) {
	$viewall = sprintf(
		'<a class="apg-case-viewall" href="%1$s"><span>%2$s</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>',
		esc_url( $props['viewall_url'] ),
		esc_html( $props['viewall_label'] )
	);
}
?>
<section class="ap-case-slider ap-case-slider--<?php echo esc_attr( $variant ); ?><?php echo $hub && 'hub-industry' === $variant ? ' ap-case-slider--hub' : ''; ?>" data-ap-case-slider data-ap-autoplay="<?php echo esc_attr( (string) max( 0, (int) $props['autoplay_ms'] ) ); ?>">
	<div class="apg-case-head">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="apg-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h2 class="apg-h2"><?php echo ap_ui_brandmarks( esc_html( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h2>
		<?php if ( '' !== $lede ) : ?>
			<p class="apg-case-lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
		<?php endif; ?>
	</div>
	<div class="apg-case-slides">
		<?php foreach ( $items as $i => $item ) : ?>
			<div class="apg-case-slide<?php echo 0 === $i ? ' is-active' : ''; ?>">
				<div class="apg-case-copy">
					<img decoding="async" width="32" height="32" class="apg-tst-quoteico" src="<?php echo $quote; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>" alt="" />
					<?php if ( '' !== $item['title'] && $hub ) : ?>
						<h3 class="apg-tst-title"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
					<?php elseif ( '' !== $item['title'] ) : ?>
						<h3 class="apg-case-headline"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
					<?php endif; ?>
					<p class="apg-tst-quote"><?php echo ap_ui_brandmarks( esc_html( $item['quote'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php if ( '' !== $item['author'] || '' !== $item['role'] ) : ?>
						<div>
							<?php if ( '' !== $item['author'] ) : ?>
								<p class="apg-tst-author"><?php echo esc_html( $item['author'] ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $item['role'] ) : ?>
								<p class="apg-tst-role"><?php echo $hub ? ap_ui_lines( $item['role'] ) : esc_html( $item['role'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; the hubs keep live's line break. ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( '' !== $item['link_label'] && '' !== $item['link_url'] ) : ?>
						<a class="apg-case-cta" href="<?php echo esc_url( $item['link_url'] ); ?>"><span><?php echo esc_html( $item['link_label'] ); ?></span><img decoding="async" src="<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>" alt="" /></a>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $item['image'] ) : ?>
					<div class="apg-case-img">
						<img decoding="async" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ); ?>" loading="lazy" />
						<?php if ( $hub && '' !== $item['logo'] ) : ?>
							<span class="apg-vid-logo"><img decoding="async" src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ); ?>" loading="lazy"<?php echo ( $item['logo_w'] && $item['logo_h'] ) ? ' style="' . esc_attr( 'width:' . $item['logo_w'] . 'px;height:' . $item['logo_h'] . 'px;object-fit:contain' ) . '"' : ''; ?> /></span>
						<?php elseif ( $hub && '' !== $item['logo_text'] ) : ?>
							<span class="apg-vid-logo"><span class="apg-logo-wordmark<?php echo '' !== $item['logo_style'] ? ' apg-logo-' . esc_attr( $item['logo_style'] ) : ''; ?>"><?php echo preg_replace( '/\*([^*]+)\*/', '<strong>$1</strong>', esc_html( $item['logo_text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only a fixed <strong> is added. ?></span></span>
						<?php elseif ( '' !== $item['logo'] ) : ?>
							<span class="sp-case-logo"><img decoding="async" src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ); ?>" loading="lazy" /></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="apg-case-controls">
		<div class="apg-case-arrows">
			<button class="apg-arrow-btn apg-arrow-prev" type="button" aria-label="<?php esc_attr_e( 'Previous case study', 'assetpanda' ); ?>"><img decoding="async" src="<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>" alt="" /></button>
			<button class="apg-arrow-btn apg-arrow-next" type="button" aria-label="<?php esc_attr_e( 'Next case study', 'assetpanda' ); ?>"><img decoding="async" src="<?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>" alt="" /></button>
		</div>
		<div class="apg-case-line"><span class="apg-case-progress" style="<?php echo esc_attr( 'width:' . $progress . '%' ); ?>"></span></div>
		<div class="apg-case-dots"></div>
		<?php
		if ( 'solution' === $variant || $hub ) {
			echo $viewall; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above.
		}
		?>
	</div>
	<?php if ( 'service' === $variant && '' !== $viewall ) : ?>
		<div class="apg-case-foot"><?php echo $viewall; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above. ?></div>
	<?php endif; ?>
</section>
