<?php
/**
 * comparison — pillar / Asset Panda / spreadsheets table.
 *
 * Matches the live homepage (dev page 7, #ap-asset-intel-compare-v1), built
 * from Figma Why Asset Intelligence (4331:24561). Columns 347 / 427 / 347. The
 * centre column is an elevated card (indigo-400 border, radius 16, shadow)
 * with a 160px gradient banner; the right column is a muted panel that starts
 * 55px lower and rounds only its right corners.
 *
 * Built on CSS grid so every row lines up across all three columns
 * automatically: each cell carries its row number in --r, and two decorative
 * layers paint the centre card and right panel behind the cells. Rows are
 * ARIA rows (display: contents) so it reads as a table.
 *
 * Below 992px it is one stacked card: the banner, one header line
 * ("Asset Panda (Powered by UrsaAI) vs. Spreadsheets & Siloed Tools"), then
 * each pillar as a label, the Asset Panda answer and the old way.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
$rows  = array_values(
	array_filter(
		is_array( $props['rows'] ) ? $props['rows'] : array(),
		static function ( $r ) {
			return is_array( $r ) && ! empty( $r['pillar'] ) && ! empty( $r['after'] ) && ! empty( $r['before'] );
		}
	)
);

if ( '' === $title || empty( $rows ) ) {
	return;
}

$eyebrow   = trim( (string) $props['eyebrow'] );
$accent    = trim( (string) $props['title_accent'] );
$intro     = trim( (string) $props['intro'] );
$reveal    = (bool) $props['reveal'];
$has_cta   = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$has_link  = '' !== trim( (string) $props['link_label'] ) && '' !== trim( (string) $props['link_url'] );
$n         = count( $rows );
$cta_row   = $n + 3;
$span_card = ( $has_cta || $has_link ) ? $n + 3 : $n + 2;
$asset     = static function ( $f ) {
	return esc_url( ap_ui_asset_url( 'comparison', $f ) );
};
?>
<section class="ap-compare">
	<div class="ap-compare__inner">
		<div class="ap-compare__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-compare__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-compare__title">
				<?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?>
				<?php if ( '' !== $accent ) : ?>
					<span class="ap-compare__accent"><?php echo esc_html( $accent ); ?></span>
				<?php endif; ?>
			</h2>
			<?php if ( '' !== $intro ) : ?>
				<p class="ap-compare__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-compare__table" role="table" aria-label="<?php echo esc_attr( $title ); ?>"
			style="--ap-span-card: <?php echo (int) $span_card; ?>; --ap-span-panel: <?php echo (int) ( $n + 2 ); ?>;"
			<?php echo $reveal ? ' data-ap-reveal="fade" data-ap-reveal-delay="1"' : ''; ?>>
			<div class="ap-compare__deco-card" aria-hidden="true"></div>
			<div class="ap-compare__deco-panel" aria-hidden="true"></div>

			<div class="ap-compare__banner ap-compare__c2" style="--r: 1;" aria-hidden="true">
				<img class="ap-compare__bamboo ap-compare__bamboo--a" src="<?php echo $asset( 'bamboo-a.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" />
				<img class="ap-compare__bamboo ap-compare__bamboo--b" src="<?php echo $asset( 'bamboo-b.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" />
				<img class="ap-compare__panda" src="<?php echo $asset( 'panda.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" width="85" height="97" />
				<span class="ap-compare__powered">
					<span><?php echo esc_html( $props['after_note'] ); ?></span>
					<img src="<?php echo $asset( 'ursa-logo.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="UrsaAI" width="64" height="21" />
				</span>
			</div>
			<div class="ap-compare__panel-icon ap-compare__c3" style="--r: 1;" aria-hidden="true">
				<img src="<?php echo $asset( 'doc.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" width="48" height="57" />
			</div>

			<div class="ap-compare__row ap-compare__row--head" role="row">
				<div class="ap-compare__cell ap-compare__c1 ap-compare__colhead ap-compare__colhead--pillar" role="columnheader" style="--r: 2;"><?php echo esc_html( $props['pillar_title'] ); ?></div>
				<div class="ap-compare__cell ap-compare__c2 ap-compare__colhead ap-compare__colhead--after" role="columnheader" style="--r: 2;"><?php echo ap_ui_brandmarks( esc_html( $props['after_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></div>
				<div class="ap-compare__cell ap-compare__c3 ap-compare__colhead ap-compare__colhead--before" role="columnheader" style="--r: 2;"><?php echo esc_html( $props['before_title'] ); ?></div>
			</div>

			<?php foreach ( $rows as $i => $row ) : ?>
				<?php $r = $i + 3; ?>
				<div class="ap-compare__row" role="row">
					<div class="ap-compare__cell ap-compare__c1 ap-compare__pillar" role="rowheader" style="--r: <?php echo (int) $r; ?>;"><?php echo esc_html( $row['pillar'] ); ?></div>
					<div class="ap-compare__cell ap-compare__c2 ap-compare__after" role="cell" style="--r: <?php echo (int) $r; ?>;">
						<img src="<?php echo $asset( 'panda-mini.png' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" width="24" height="26" />
						<p><?php echo ap_ui_brandmarks( esc_html( $row['after'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then fixed brand markup. ?></p>
					</div>
					<div class="ap-compare__cell ap-compare__c3 ap-compare__before" role="cell" style="--r: <?php echo (int) $r; ?>;">
						<img src="<?php echo $asset( 'table.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" width="24" height="24" />
						<p><?php echo esc_html( $row['before'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>

			<?php if ( $has_cta || $has_link ) : ?>
				<div class="ap-compare__cta ap-compare__c2" style="--r: <?php echo (int) $cta_row; ?>;">
					<?php if ( $has_cta ) : ?>
						<a class="ap-compare__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
					<?php endif; ?>
					<?php if ( $has_link ) : ?>
						<a class="ap-compare__plain" href="<?php echo esc_url( $props['link_url'] ); ?>">
							<span><?php echo esc_html( $props['link_label'] ); ?></span>
							<img src="<?php echo $asset( 'arrow.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" alt="" width="24" height="24" />
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
