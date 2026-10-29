<?php
/**
 * compare-table — the comparison-guide table of the resource-center articles.
 *
 * A real table: criterion (row header with a small icon), the Asset Panda cell
 * under the indigo wordmark header, the competitor cell under its grey header,
 * and a Winner pill. Below 700px the wrapper scrolls sideways; the table keeps
 * its minimum width so the columns stay readable.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows = array();
foreach ( is_array( $props['rows'] ) ? $props['rows'] : array() as $row ) {
	if ( ! is_array( $row ) || '' === trim( (string) ( $row['label'] ?? '' ) ) ) {
		continue;
	}
	$rows[] = array(
		'label'        => trim( (string) $row['label'] ),
		'icon_svg'     => ap_ui_svg( $row['icon_svg'] ?? '' ),
		'after'        => trim( (string) ( $row['after'] ?? '' ) ),
		'before'       => trim( (string) ( $row['before'] ?? '' ) ),
		'after_check'  => ! isset( $row['after_check'] ) || ! empty( $row['after_check'] ),
		'before_check' => ! empty( $row['before_check'] ),
		'winner'       => trim( (string) ( $row['winner'] ?? '' ) ),
		'winner_note'  => trim( (string) ( $row['winner_note'] ?? '' ) ),
	);
}

if ( empty( $rows ) ) {
	return;
}

$label_title  = trim( (string) $props['label_title'] );
$after_title  = trim( (string) $props['after_title'] );
$before_title = trim( (string) $props['before_title'] );
$before_mark  = trim( (string) $props['before_mark'] );
$before_logo  = trim( (string) $props['before_logo'] );
$winner_title = trim( (string) $props['winner_title'] );
$note         = trim( (string) $props['note'] );
// No row names a winner (tables carried over from older guides): the Winner column is left out.
$has_winner = (bool) array_filter(
	$rows,
	static function ( $row ) {
		return '' !== $row['winner'] || '' !== $row['winner_note'];
	}
);
// Column names written on the phone layout's stacked cells.
$after_name  = '' !== $after_title ? $after_title : 'Asset Panda';
$before_name = trim( ( '' !== $before_mark ? strtoupper( $before_mark ) . ' ' : '' ) . $before_title );

// Pill tone from the winner's name.
$tone = static function ( $w ) {
	$w = strtolower( trim( (string) $w ) );
	if ( '' === $w ) {
		return '';
	}
	if ( false !== strpos( $w, 'asset panda' ) ) {
		return 'ap';
	}
	return 'tie' === rtrim( $w, '*' ) ? 'tie' : 'other';
};

$check = '<svg class="ap-compare-table__dot ap-compare-table__dot--on" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="8" fill="#00A991"/><path d="M4.8 8.2l2.1 2.1 4.3-4.6" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$dot   = '<svg class="ap-compare-table__dot" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="8" fill="#C9CCD3"/><circle cx="8" cy="8" r="3" fill="#fff"/></svg>';

$pill_icons = array(
	'ap'    => '<img src="' . esc_url( ap_ui_asset_url( 'compare-table', 'panda.svg' ) ) . '" alt="" width="14" height="15" />',
	'tie'   => '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2 5.5h9.5M9 3l2.5 2.5L9 8M14 10.5H4.5M7 8l-2.5 2.5L7 13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	'other' => '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M5 2.5h6v3a3 3 0 0 1-6 0v-3zM5 4H3a2 2 0 0 0 2 2.5M11 4h2a2 2 0 0 1-2 2.5M8 8.5v2.5M5.5 13.5h5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
);
?>
<div class="ap-compare-table<?php echo $has_winner ? '' : ' ap-compare-table--nowin'; ?>">
	<div class="ap-compare-table__scroll">
		<table class="ap-compare-table__table">
			<thead>
				<tr>
					<th scope="col" class="ap-compare-table__th ap-compare-table__th--crit"><?php echo esc_html( $label_title ); ?></th>
					<th scope="col" class="ap-compare-table__th ap-compare-table__th--a">
						<span class="ap-compare-table__ap">
							<img class="ap-compare-table__ap-icon" src="<?php echo esc_url( ap_ui_asset_url( 'compare-table', 'logo-icon.svg' ) ); ?>" alt="" width="24" height="26" />
							<img class="ap-compare-table__ap-word" src="<?php echo esc_url( ap_ui_asset_url( 'compare-table', 'logo-wordmark.svg' ) ); ?>" alt="" width="47" height="27" />
							<span class="ap-compare-table__sr"><?php echo esc_html( $after_title ); ?></span>
						</span>
					</th>
					<th scope="col" class="ap-compare-table__th ap-compare-table__th--b">
						<span class="ap-compare-table__ezo">
							<?php if ( '' !== $before_logo ) : ?>
								<img class="ap-compare-table__b-logo" src="<?php echo esc_url( $before_logo ); ?>" alt="<?php echo esc_attr( $props['before_logo_alt'] ); ?>" />
							<?php elseif ( '' !== $before_mark ) : ?>
								<span class="ap-compare-table__mark" aria-hidden="true"><?php echo esc_html( $before_mark ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $before_title ) : ?>
								<span class="ap-compare-table__brand"><?php echo esc_html( $before_title ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $before_mark && '' === $before_logo ) : ?>
								<span class="ap-compare-table__sr"><?php echo esc_html( $before_mark ); ?></span>
							<?php endif; ?>
						</span>
					</th>
					<?php if ( $has_winner ) : ?>
						<th scope="col" class="ap-compare-table__th ap-compare-table__th--w"><?php echo esc_html( $winner_title ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$t    = $tone( $row['winner'] );
					$star = '' !== $row['winner'] && '*' === substr( $row['winner'], -1 ); // the footnote mark sits outside the pill
					$wtxt = $star ? rtrim( $row['winner'], '*' ) : $row['winner'];
					?>
					<tr>
						<th scope="row" class="ap-compare-table__crit">
							<?php if ( '' !== $row['icon_svg'] ) : ?>
								<span class="ap-compare-table__ico" aria-hidden="true"><?php echo $row['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
							<?php endif; ?>
							<span><?php echo esc_html( $row['label'] ); ?></span>
						</th>
						<td class="ap-compare-table__a" data-col="<?php echo esc_attr( $after_name ); ?>"><?php echo $row['after_check'] ? $check : $dot; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo ap_ui_brandmarks( esc_html( $row['after'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span></td>
						<td class="ap-compare-table__b" data-col="<?php echo esc_attr( $before_name ); ?>"><?php echo $row['before_check'] ? $check : $dot; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo esc_html( $row['before'] ); ?></span></td>
						<?php if ( $has_winner ) : ?>
							<td class="ap-compare-table__w">
								<?php if ( '' !== $t ) : ?>
									<span class="ap-compare-table__pill ap-compare-table__pill--<?php echo esc_attr( $t ); ?>"><?php echo $pill_icons[ $t ]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><?php echo esc_html( $wtxt ); ?></span><?php if ( $star ) : ?><span class="ap-compare-table__star" aria-label="footnote">*</span><?php endif; ?>
								<?php endif; ?>
								<?php if ( '' !== $row['winner_note'] ) : ?>
									<span class="ap-compare-table__note"><?php echo esc_html( $row['winner_note'] ); ?></span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( '' !== $note ) : ?>
		<p class="ap-compare-table__foot"><?php echo ap_ui_brandmarks( esc_html( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
	<?php endif; ?>
</div>
