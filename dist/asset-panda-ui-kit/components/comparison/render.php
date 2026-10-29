<?php
/**
 * comparison — pillar / Asset Panda / old-way table.
 *
 * Matches the live #ap-asset-intel-compare-v1 section. The markup below is
 * live's own, class for class, so the ported sheets (one per page, scoped by
 * the variant class) apply as they do there: a three-column grid from 1280px,
 * one stacked card under it. The homepage closes the centre column with a CTA
 * row; the Asset Intelligence Platform page sets a "Connected by design" card
 * under the table instead.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows = array_values(
	array_filter(
		is_array( $props['rows'] ) ? $props['rows'] : array(),
		static function ( $r ) {
			return is_array( $r ) && ! empty( $r['pillar'] ) && ! empty( $r['after'] ) && ! empty( $r['before'] );
		}
	)
);

$lines = array_values(
	array_filter(
		array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $props['title'] ) ) ),
		static function ( $l ) {
			return '' !== $l;
		}
	)
);

if ( empty( $lines ) || empty( $rows ) ) {
	return;
}

$variant  = in_array( $props['variant'], array( 'home', 'platform', 'guide' ), true ) ? $props['variant'] : 'home';
$is_guide = 'guide' === $variant;
$win_k    = trim( (string) $props['winner_title'] );
$note     = trim( (string) $props['note'] );
// Winner pill tone (guide): a name containing Asset Panda is green, a tie grey, anything else indigo.
$win_tone = static function ( $w ) {
	$w = strtolower( trim( (string) $w ) );
	if ( '' === $w ) {
		return '';
	}
	if ( false !== strpos( $w, 'asset panda' ) ) {
		return 'ap';
	}
	return 'tie' === rtrim( $w, '*' ) ? 'tie' : 'other';
};
$accent   = trim( (string) $props['title_accent'] );
$eyebrow  = trim( (string) $props['eyebrow'] );
$intro    = trim( (string) $props['intro'] );
$before   = trim( (string) $props['before_title'] );
$vs       = '' !== trim( (string) $props['vs_label'] ) ? (string) $props['vs_label'] : ' vs. ' . $before;
$label    = '' !== trim( (string) $props['table_label'] ) ? $props['table_label'] : implode( ' ', $lines );
$has_cta  = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
$has_link = '' !== trim( (string) $props['link_label'] ) && '' !== trim( (string) $props['link_url'] );
$has_conn = '' !== trim( (string) $props['connected_title'] );
$last     = count( $rows ) - 1;
$asset    = static function ( $f ) {
	return esc_url( ap_ui_asset_url( 'comparison', $f ) );
};
$dims     = static function ( $w, $h ) {
	$w = (int) $w;
	$h = (int) $h;
	return ( $w > 0 && $h > 0 && $w < 10000 && $h < 10000 ) ? ' width="' . $w . '" height="' . $h . '"' : '';
};
?>
<section class="ap-compare ap-compare--<?php echo esc_attr( $variant ); ?>">
	<div class="ap-compare-inner">
		<div class="ap-compare-heading">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2>
				<?php foreach ( $lines as $line ) : ?>
					<span<?php echo $line === $accent ? ' class="ap-gradient-text"' : ''; ?>><?php echo ap_ui_brandmarks( esc_html( $line ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
				<?php endforeach; ?>
			</h2>
			<?php if ( '' !== $intro ) : ?>
				<p><?php echo ap_ui_brandmarks( esc_html( $intro ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-compare-table" role="table" aria-label="<?php echo esc_attr( $label ); ?>"<?php echo $is_guide ? ' style="--ap-compare-rows:' . (int) count( $rows ) . '"' : ''; ?>>
			<div class="ap-cell ap-left-spacer" aria-hidden="true"></div>
			<div class="ap-cell ap-center-top" aria-hidden="true">
				<?php if ( '' !== trim( (string) $props['banner'] ) ) : ?>
					<img<?php echo $dims( $props['banner_w'], $props['banner_h'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> decoding="async" class="ap-center-top-img" src="<?php echo esc_url( $props['banner'] ); ?>" alt="" loading="lazy" />
				<?php endif; ?>
			</div>
			<div class="ap-cell ap-right-top" aria-hidden="true">
				<img decoding="async" class="ap-file-icon-img" src="<?php echo $asset( 'compare-file.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url in the helper. ?>" alt="" loading="lazy" />
			</div>
			<div role="row" style="display:contents">
				<div class="ap-cell ap-left-title" role="columnheader"><?php echo esc_html( $props['pillar_title'] ); ?></div>
				<div class="ap-cell ap-center-title" role="columnheader"><?php echo ap_ui_brandmarks( esc_html( $props['after_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?><span class="ap-compare-vs" aria-hidden="true"><?php echo esc_html( $vs ); ?></span></div>
				<div class="ap-cell ap-right-title" role="columnheader"><?php echo esc_html( $before ); ?></div>
			</div>
			<?php foreach ( $rows as $i => $row ) : ?>
				<div role="row" style="display:contents">
					<div class="ap-cell ap-left-label" role="rowheader"><?php echo esc_html( $row['pillar'] ); ?> <?php if ( $is_guide && '' !== $win_tone( $row['winner'] ?? '' ) ) : ?><span class="ap-compare-winner ap-compare-winner--<?php echo esc_attr( $win_tone( $row['winner'] ) ); ?>"><?php if ( '' !== $win_k && 'tie' !== $win_tone( $row['winner'] ) ) : ?><span class="ap-compare-winner__k"><?php echo esc_html( $win_k ); ?></span><?php endif; ?><?php echo esc_html( $row['winner'] ); ?></span><?php endif; ?></div>
					<div role="cell" class="ap-cell ap-center-copy<?php echo ( ( 'platform' === $variant || ( $is_guide && ! $has_cta && ! $has_link ) ) && $i === $last ) ? ' ap-center-copy-end' : ''; ?>">
						<img decoding="async" class="ap-panda-mini-img" src="<?php echo $asset( 'compare-panda.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url in the helper. ?>" alt="" loading="lazy" />
						<p><?php echo ap_ui_brandmarks( esc_html( $row['after'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					</div>
					<div role="cell" class="ap-cell ap-right-copy<?php echo $i === $last ? ' ap-right-copy-end' : ''; ?>">
						<img decoding="async" class="ap-table-mini-img" src="<?php echo $asset( 'compare-table.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url in the helper. ?>" alt="" loading="lazy" />
						<p><?php echo esc_html( $row['before'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
			<?php if ( $has_cta || $has_link ) : ?>
				<div class="ap-cell ap-left-empty" aria-hidden="true"></div>
				<div class="ap-cell ap-right-empty" aria-hidden="true"></div>
				<div role="row" style="display:contents">
					<div role="cell" class="ap-cell ap-center-cta">
						<?php if ( $has_cta ) : ?>
							<a href="<?php echo esc_url( $props['cta_url'] ); ?>" class="ap-compare-cta"><?php echo esc_html( $props['cta_label'] ); ?></a>
						<?php endif; ?>
						<?php if ( $has_link ) : ?>
							<a href="<?php echo esc_url( $props['link_url'] ); ?>" class="ap-compare-link"><?php echo esc_html( $props['link_label'] ); ?> <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="#00A991" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $note ) : ?>
			<p class="ap-compare-note"><?php echo ap_ui_brandmarks( esc_html( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
		<?php endif; ?>

		<?php if ( $has_conn ) : ?>
			<div class="ap-connected">
				<div class="ap-connected-text">
					<p class="ap-connected-title"><?php echo esc_html( $props['connected_title'] ); ?></p>
					<?php if ( '' !== trim( (string) $props['connected_body'] ) ) : ?>
						<p class="ap-connected-body"><?php echo ap_ui_brandmarks( esc_html( $props['connected_body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['connected_label'] ) && '' !== trim( (string) $props['connected_url'] ) ) : ?>
						<a class="ap-connected-link" href="<?php echo esc_url( $props['connected_url'] ); ?>"><?php echo esc_html( $props['connected_label'] ); ?> <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.75 16L21.5 12.25L17.75 8.5M21.5 12.25H3.5" stroke="#00A991" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>
					<?php endif; ?>
				</div>
				<?php if ( '' !== trim( (string) $props['connected_image'] ) ) : ?>
					<img<?php echo $dims( $props['connected_w'], $props['connected_h'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> decoding="async" class="ap-connected-shot" src="<?php echo esc_url( $props['connected_image'] ); ?>" alt="<?php echo esc_attr( $props['connected_image_alt'] ); ?>" loading="lazy" />
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
