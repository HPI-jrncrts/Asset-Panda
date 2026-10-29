<?php
/**
 * plans-compare — the pricing v2 "Compare plans" section.
 *
 * Built from the Asset Panda Pricing v2 mockup: a centred heading and lead,
 * the feature-by-plan table, and the "on every plan" pills.
 *
 * Without JavaScript the table scrolls sideways on narrow screens with the
 * feature column pinned. behaviour.js adds the phone plan switch (one plan
 * column at a time) and tints the column of a plan picked in plans-picker.
 * Every value cell carries data-col, so the script never counts columns.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_key = static function ( $name ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( trim( (string) $name ) ) ), '-' );
};

$plans = array();
foreach ( is_array( $props['plans'] ) ? $props['plans'] : array() as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	$name = trim( (string) ( $row['name'] ?? '' ) );
	if ( '' === $name ) {
		continue;
	}
	// accent: a named colour from a fixed palette (never raw CSS).
	$accent  = strtolower( trim( (string) ( $row['accent'] ?? '' ) ) );
	$plans[] = array(
		'key'    => $ap_key( $name ),
		'name'   => $name,
		'price'  => trim( (string) ( $row['price'] ?? '' ) ),
		'accent' => in_array( $accent, array( 'slate', 'teal', 'indigo', 'violet', 'amber', 'rose' ), true ) ? $accent : '',
	);
}

$title = trim( (string) $props['title'] );
if ( '' === $title || empty( $plans ) ) {
	return;
}

$count = count( $plans );
$kinds = array( 'top', 'group', 'sub', 'sub2' );
$rows  = array();
foreach ( is_array( $props['rows'] ) ? $props['rows'] : array() as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	$label = trim( (string) ( $row['label'] ?? '' ) );
	if ( '' === $label ) {
		continue;
	}
	$kind   = in_array( $row['kind'] ?? '', $kinds, true ) ? $row['kind'] : 'top';
	$values = $row['values'] ?? array();
	if ( ! is_array( $values ) ) {
		$values = explode( '|', (string) $values );
	}
	$values = array_map(
		static function ( $v ) {
			return is_scalar( $v ) ? trim( (string) $v ) : '';
		},
		array_values( $values )
	);
	$rows[] = array(
		'label'  => $label,
		'hint'   => trim( (string) ( $row['hint'] ?? '' ) ),
		'kind'   => $kind,
		'values' => array_slice( array_pad( $values, $count, '' ), 0, $count ),
	);
}

if ( empty( $rows ) ) {
	return;
}

$lede        = trim( (string) $props['lede'] );
$focus       = $ap_key( $props['focus_plan'] );
$focus_index = 1;
foreach ( $plans as $i => $plan ) {
	if ( $plan['key'] === $focus ) {
		$focus_index = $i + 1;
	}
}
$included = trim( (string) $props['included_label'] );
$excluded = trim( (string) $props['excluded_label'] );
$pills    = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $props['everything'] ) ), 'strlen' ) );
$pills_hd   = trim( (string) $props['everything_title'] );

// The featured plan's column is tinted top to bottom and tagged in its header.
$featured_key   = $ap_key( $props['featured_plan'] );
$featured_label = trim( (string) $props['featured_label'] );

// Extra classes for every cell of a column: its accent, and whether it is featured.
$col_class = array();
foreach ( $plans as $i => $plan ) {
	$classes = array();
	if ( '' !== $plan['accent'] ) {
		$classes[] = 'is-acc-' . $plan['accent'];
	}
	if ( '' !== $featured_key && $plan['key'] === $featured_key ) {
		$classes[] = 'is-featured-col';
	}
	$col_class[ $i ] = implode( ' ', $classes );
}

// Illustration peeking over the table: a bare file name is one of this component's assets.
$art = trim( (string) $props['art'] );
if ( preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:svg|webp|png|jpe?g)$/i', $art ) ) {
	$art = ap_ui_asset_url( 'plans-compare', $art );
}
$art_alt  = trim( (string) $props['art_alt'] );
$art_dims = ( (int) $props['art_width'] > 0 && (int) $props['art_height'] > 0 )
	? ' width="' . (int) $props['art_width'] . '" height="' . (int) $props['art_height'] . '"'
	: '';
$variant    = 'clean' === $props['variant'] ? 'clean' : 'classic';
$section_id = $ap_key( $props['section_id'] );

// yes -> tick, no -> dash, anything else is a value shown as written.
$ap_cell = static function ( $value ) use ( $included, $excluded ) {
	$v = strtolower( $value );
	if ( in_array( $v, array( 'yes', 'y', 'true', '✓', '✔' ), true ) ) {
		return array( 'tick', '<span class="ap-plans-compare__y" aria-hidden="true">&#10003;</span><span class="ap-plans-compare__sr">' . esc_html( $included ) . '</span>' );
	}
	if ( in_array( $v, array( 'no', 'n', 'false', '', '-', '—', '–' ), true ) ) {
		return array( 'dash', '<span class="ap-plans-compare__n" aria-hidden="true">&mdash;</span><span class="ap-plans-compare__sr">' . esc_html( $excluded ) . '</span>' );
	}
	return array( 'value', esc_html( $value ) );
};
?>
<section class="ap-plans-compare ap-plans-compare--<?php echo esc_attr( $variant ); ?>"<?php echo '' !== $section_id ? ' id="' . esc_attr( $section_id ) . '"' : ''; ?> data-ap-plans-compare data-ap-focus="<?php echo (int) $focus_index; ?>">
	<div class="ap-plans-compare__inner">
		<div class="ap-plans-compare__head">
			<h2 class="ap-plans-compare__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-plans-compare__lede"><?php echo esc_html( $lede ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-plans-compare__switch" role="group" aria-label="<?php echo esc_attr( $props['switch_label'] ); ?>" data-ap-compare-switch>
			<?php foreach ( $plans as $i => $plan ) : ?>
				<button type="button" class="ap-plans-compare__switch-btn" aria-pressed="<?php echo ( $i + 1 ) === $focus_index ? 'true' : 'false'; ?>" data-col="<?php echo (int) ( $i + 1 ); ?>" data-ap-plan="<?php echo esc_attr( $plan['key'] ); ?>">
					<span><?php echo esc_html( $plan['name'] ); ?></span>
					<?php if ( '' !== $plan['price'] ) : ?>
						<small><?php echo esc_html( $plan['price'] ); ?></small>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="ap-plans-compare__stage<?php echo '' !== $art ? ' has-art' : ''; ?>">
		<div class="ap-plans-compare__box" role="region" aria-label="<?php echo esc_attr( $props['table_label'] ); ?>" tabindex="0">
			<table class="ap-plans-compare__table">
				<thead>
					<tr>
						<?php // The header's empty corner holds the illustration, standing on the header's bottom rule. ?>
						<td class="ap-plans-compare__rh<?php echo '' !== $art ? ' ap-plans-compare__corner' : ''; ?>">
							<?php if ( '' !== $art ) : ?>
								<img class="ap-plans-compare__art" src="<?php echo esc_url( $art ); ?>" alt="<?php echo esc_attr( $art_alt ); ?>"<?php echo $art_dims; // phpcs:ignore WordPress.Security.EscapeOutput -- integers only. ?><?php echo '' === $art_alt ? ' aria-hidden="true"' : ''; ?> loading="lazy" decoding="async" />
							<?php endif; ?>
						</td>
						<?php foreach ( $plans as $i => $plan ) : ?>
							<th scope="col" class="<?php echo esc_attr( $col_class[ $i ] ); ?>" data-col="<?php echo (int) ( $i + 1 ); ?>" data-ap-plan="<?php echo esc_attr( $plan['key'] ); ?>">
								<?php if ( '' !== $featured_label && false !== strpos( $col_class[ $i ], 'is-featured-col' ) ) : ?>
									<span class="ap-plans-compare__tag"><?php echo esc_html( $featured_label ); ?></span>
								<?php endif; ?>
								<span class="ap-plans-compare__pn"><?php echo esc_html( $plan['name'] ); ?></span>
								<?php if ( '' !== $plan['price'] ) : ?>
									<span class="ap-plans-compare__pp"><?php echo esc_html( $plan['price'] ); ?></span>
								<?php endif; ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php if ( 'group' === $row['kind'] ) : ?>
							<tr class="is-group">
								<th scope="colgroup" colspan="<?php echo (int) ( $count + 1 ); ?>"><span><?php echo esc_html( $row['label'] ); ?></span></th>
							</tr>
						<?php else : ?>
							<tr class="is-<?php echo esc_attr( $row['kind'] ); ?>">
								<th scope="row" class="ap-plans-compare__rh">
									<?php echo esc_html( $row['label'] ); ?>
									<?php if ( '' !== $row['hint'] ) : ?>
										<span class="ap-plans-compare__hint"><?php echo esc_html( $row['hint'] ); ?></span>
									<?php endif; ?>
								</th>
								<?php foreach ( $row['values'] as $i => $value ) : ?>
									<?php list( $type, $html ) = $ap_cell( $value ); ?>
									<td class="<?php echo esc_attr( trim( 'is-' . $type . ' ' . ( $col_class[ $i ] ?? '' ) ) ); ?>" data-col="<?php echo (int) ( $i + 1 ); ?>"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_html() in $ap_cell. ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		</div>

		<?php if ( ! empty( $pills ) ) : ?>
			<div class="ap-plans-compare__every">
				<?php if ( '' !== $pills_hd ) : ?>
					<p class="ap-plans-compare__every-title"><?php echo esc_html( $pills_hd ); ?></p>
				<?php endif; ?>
				<ul class="ap-plans-compare__pills">
					<?php foreach ( $pills as $pill ) : ?>
						<li><span aria-hidden="true">&#10003;</span><?php echo esc_html( $pill ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</section>
