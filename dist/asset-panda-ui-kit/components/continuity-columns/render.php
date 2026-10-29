<?php
/**
 * continuity-columns — three labelled columns over a timeline rule.
 *
 * Matches the live .apx-oc on /asset-intelligence-platform/. The inner class
 * names are the live ones, so this stays diffable against the ported sheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && '' !== trim( (string) ( $i['title'] ?? '' ) );
		}
	)
);

if ( empty( $items ) ) {
	return;
}

$rule     = ap_ui_svg( $props['rule_svg'] );
$numbered = 'numbered' === $props['variant'];

// Heading with an optional gradient slice: escape first, then wrap the slice.
$title_html = ap_ui_brandmarks( ap_ui_lines( $props['title'] ) );
$accent     = trim( (string) $props['title_accent'] );
if ( '' !== $accent && false !== strpos( (string) $props['title'], $accent ) ) {
	$escaped    = ap_ui_brandmarks( esc_html( $accent ) );
	$title_html = str_replace( $escaped, '<span class="ap-continuity-columns__g">' . $escaped . '</span>', $title_html );
}
?>
<section class="ap-continuity-columns<?php echo $numbered ? ' ap-continuity-columns--numbered' : ''; ?>" aria-label="<?php echo esc_attr( wp_strip_all_tags( $props['title'] ) ); ?>">
	<div class="ap-continuity-columns__wrap">
		<div class="ap-continuity-columns__head">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-continuity-columns__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-continuity-columns__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and fixed spans are added. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-continuity-columns__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-continuity-columns__cards">
			<?php foreach ( $items as $item ) : ?>
				<div class="ap-continuity-columns__card"<?php echo $numbered && preg_match( '/^#[0-9a-fA-F]{3,8}$/', trim( (string) ( $item['accent'] ?? '' ) ) ) ? ' style="' . esc_attr( '--cc-accent:' . trim( (string) $item['accent'] ) ) . '"' : ''; ?>>
					<div class="ap-continuity-columns__cardhead">
						<?php if ( ! empty( $item['kicker'] ) ) : ?>
							<p class="ap-continuity-columns__kicker"><?php echo esc_html( $item['kicker'] ); ?></p>
						<?php endif; ?>
						<h3 class="ap-continuity-columns__ct"><?php if ( $numbered && '' !== ap_ui_svg( $item['icon_svg'] ?? '' ) ) : ?><span class="ap-continuity-columns__num" aria-hidden="true"><?php echo ap_ui_svg( $item['icon_svg'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span><?php endif; ?><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
					</div>
					<?php if ( ! empty( $item['body'] ) ) : ?>
						<p class="ap-continuity-columns__cb"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) ( $item['link_label'] ?? '' ) ) && '' !== trim( (string) ( $item['url'] ?? '' ) ) ) : ?>
						<a class="ap-continuity-columns__link" href="<?php echo esc_url( $item['url'] ); ?>"><span><?php echo ap_ui_brandmarks( esc_html( $item['link_label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span><img src="<?php echo esc_url( ap_ui_asset_url( 'continuity-columns', 'icon-arrow.svg' ) ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" /></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if ( '' !== $rule && ! $numbered ) : ?>
				<?php // The rule's light band on the vertical timeline (≤900px); see style.css "QA 2026-10-29". ?>
				<span class="ap-continuity-columns__beam ap-continuity-columns__beam--y" data-ap-cc-beam aria-hidden="true"></span>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $rule ) : ?>
			<div class="ap-continuity-columns__rule" aria-hidden="true"><?php echo $rule; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?><span class="ap-continuity-columns__beam" data-ap-cc-beam></span></div>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $props['foot'] ) ) : ?>
			<p class="ap-continuity-columns__foot"><?php echo ap_ui_brandmarks( esc_html( $props['foot'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
		<?php endif; ?>
	</div>
</section>
