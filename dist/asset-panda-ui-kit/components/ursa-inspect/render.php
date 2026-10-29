<?php
/**
 * ursa-inspect — "See something wrong? Let the photo carry more of the story."
 *
 * Ursa AI frame 7297:118866: a 540px copy column (eyebrow, 40px heading, 18px
 * paragraph, green-check list, closing note) 40px from a 540px card that holds
 * the live Ursa Inspection demo, tagged "Try it live" with a hand-drawn
 * connector; the outline and solid buttons sit centred below.
 *
 * The live demo (QA 2026-10-29: "It needs to be interactive embed") is framed
 * the way the homepage hero frames it: an <iframe> of the demo_url as wide as
 * the column, loading="lazy", allow="camera". The demo draws its own card and
 * its own "Try it live" tag, so while it shows, the kit's card chrome and tag
 * step aside; behaviour.js sizes the frame from the height the demo posts to
 * its parent, so it never scrolls inside. The demo's CSP (frame-ancestors)
 * only lets assetpanda.com and *.assetpanda.com frame it:
 *  - demo_url only: the frame, nothing else (as on the homepage).
 *  - poster only: the poster in the kit's card, with the kit's tag.
 *  - both (recommended): the poster card shows first; the frame loads behind
 *    it, inert, and takes over the moment the demo posts its first message.
 *    Where the host is refused (staging), no message ever comes and the
 *    poster card simply stays — never a blank or "refused" box.
 * The check icon and connector are the kit's (assets/).
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

$checks = array();
foreach ( is_array( $props['checks'] ) ? $props['checks'] : array() as $check ) {
	$check = trim( (string) ( is_array( $check ) ? ( $check['text'] ?? '' ) : $check ) );
	if ( '' !== $check ) {
		$checks[] = $check;
	}
}

$eyebrow   = trim( (string) $props['eyebrow'] );
$body      = trim( (string) $props['body'] );
$note      = trim( (string) $props['note'] );
$demo_url  = trim( (string) $props['demo_url'] );
$poster    = trim( (string) $props['poster'] );
$tag       = trim( (string) $props['tag'] );
$poster_w  = max( 1, (int) $props['poster_width'] );
$poster_h  = max( 1, (int) $props['poster_height'] );
$has_cta   = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$has_sec   = '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] );
$has_media = '' !== $demo_url || '' !== $poster;
$cover     = '' !== $demo_url && '' !== $poster; // the poster card shows until the demo is live.
$media_cls = 'ap-ursa-inspect__media';
if ( $cover ) {
	$media_cls .= ' ap-ursa-inspect__media--cover';
} elseif ( '' !== $demo_url ) {
	$media_cls .= ' ap-ursa-inspect__media--live';
}
$show_tag = '' !== $tag && '' !== $poster; // the live demo draws its own "Try it live" tag.
?>
<section class="ap-ursa-inspect" aria-label="<?php echo esc_attr( wp_strip_all_tags( $title ) ); ?>"<?php echo '' !== $demo_url ? ' data-ap-ursa-inspect' : ''; ?>>
	<div class="ap-ursa-inspect__wrap">
		<div class="ap-ursa-inspect__row<?php echo $has_media ? '' : ' ap-ursa-inspect__row--solo'; ?>">
			<div class="ap-ursa-inspect__copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-ursa-inspect__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-ursa-inspect__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php if ( '' !== $body ) : ?>
					<p class="ap-ursa-inspect__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>
				<?php if ( $checks ) : ?>
					<ul class="ap-ursa-inspect__list">
						<?php foreach ( $checks as $check ) : ?>
							<li><?php echo ap_ui_brandmarks( esc_html( $check ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( '' !== $note ) : ?>
					<p class="ap-ursa-inspect__note"><?php echo ap_ui_brandmarks( esc_html( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $has_media ) : ?>
				<div class="<?php echo esc_attr( $media_cls ); ?>">
					<?php if ( $show_tag ) : ?>
						<span class="ap-ursa-inspect__tag" aria-hidden="true"><img class="ap-ursa-inspect__line" src="<?php echo esc_url( ap_ui_asset_url( 'ursa-inspect', 'connector.svg' ) ); ?>" alt="" width="35" height="51" loading="lazy" decoding="async" /><span class="ap-ursa-inspect__pill"><span><?php echo esc_html( $tag ); ?></span></span></span>
					<?php endif; ?>
					<div class="ap-ursa-inspect__frame">
						<?php if ( '' !== $demo_url ) : ?>
							<iframe class="ap-ursa-inspect__demo" src="<?php echo esc_url( $demo_url ); ?>" title="<?php echo esc_attr( $props['demo_title'] ); ?>" loading="lazy" allow="camera"<?php echo $cover ? ' inert' : ''; ?>></iframe>
						<?php endif; ?>
						<?php if ( '' !== $poster ) : ?>
							<img class="ap-ursa-inspect__poster" src="<?php echo esc_url( $poster ); ?>" alt="<?php echo esc_attr( $props['poster_alt'] ); ?>" width="<?php echo esc_attr( (string) $poster_w ); ?>" height="<?php echo esc_attr( (string) $poster_h ); ?>" loading="lazy" decoding="async" />
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $has_cta || $has_sec ) : ?>
			<div class="ap-ursa-inspect__cta">
				<?php if ( $has_sec ) : ?>
					<a class="ap-ursa-inspect__btn ap-ursa-inspect__btn--ghost" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $props['secondary_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
				<?php endif; ?>
				<?php if ( $has_cta ) : ?>
					<a class="ap-ursa-inspect__btn ap-ursa-inspect__btn--solid" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo ap_ui_brandmarks( esc_html( $props['cta_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
