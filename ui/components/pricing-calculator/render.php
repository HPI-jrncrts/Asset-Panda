<?php
/**
 * pricing-calculator — the interactive ROI calculator.
 *
 * Matches the live `.ap-roi-calculator-section` on /new-pricing/: a bordered
 * card with range sliders on the left and the annual-return panel on the right
 * (stacked below 992px), and a centred sales line under the card.
 *
 * Self-contained so other pages (the legacy /roi-calculator/) can use it on its
 * own. Every number the maths uses arrives as a prop and travels to
 * behaviour.js as data attributes; the script holds no figures of its own. The
 * first paint is computed here with the same model, so the card reads right
 * before (and without) the script.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$num = static function ( $value, $fallback ) {
	return is_numeric( $value ) ? (float) $value : (float) $fallback;
};

// A number as an attribute / chip string: 10 not 10.0, 0.7 not 0.70000.
$plain = static function ( $value ) {
	$value = (float) $value;
	if ( floor( $value ) === $value && abs( $value ) < 1e15 ) {
		return (string) (int) $value;
	}
	return rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );
};

// Mirrors format() in behaviour.js.
$format = static function ( $value, $kind ) use ( $plain ) {
	switch ( $kind ) {
		case 'number':
			return number_format( round( $value ) );
		case 'currency':
			return '$' . number_format( round( $value ) );
		case 'money_short':
			if ( $value >= 1000000 ) {
				$millions = $value / 1000000;
				return '$' . ( 0.0 === fmod( $millions, 1 ) ? sprintf( '%.0F', $millions ) : sprintf( '%.1F', $millions ) ) . 'M';
			}
			return '$' . (int) round( $value / 1000 ) . 'K';
		default:
			return $plain( $value );
	}
};

// Scalar prop -> trimmed string; anything else (an array from a bad save) -> ''.
$str = static function ( $value ) {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
};

$fill = static function ( $text, $value ) {
	return str_replace( '{n}', $value, $text );
};

// ---- Model inputs ----
$keys    = array( 'people', 'hourly', 'assets', 'hours' );
$formats = array( 'raw', 'number', 'currency', 'money_short' );
$sliders = array();
$seen    = array();
foreach ( is_array( $props['sliders'] ) ? $props['sliders'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$key = $str( $item['key'] ?? '' );
	if ( ! in_array( $key, $keys, true ) || isset( $seen[ $key ] ) ) {
		continue;
	}
	$min = $num( $item['min'] ?? null, 0 );
	$max = $num( $item['max'] ?? null, 100 );
	if ( $max <= $min ) {
		continue;
	}
	$step  = $num( $item['step'] ?? null, 1 );
	$step  = $step > 0 ? $step : 1.0;
	$value = min( $max, max( $min, $num( $item['value'] ?? null, $min ) ) );
	$kind  = in_array( $str( $item['format'] ?? '' ), $formats, true ) ? $str( $item['format'] ) : ( 'assets' === $key ? 'money_short' : 'raw' );

	$seen[ $key ] = true;
	$sliders[]    = array(
		'key'       => $key,
		'label'     => $str( $item['label'] ?? '' ),
		'min'       => $min,
		'max'       => $max,
		'step'      => $step,
		'value'     => $value,
		'pill'      => '' !== $str( $item['pill'] ?? '' ) ? $str( $item['pill'] ) : '{n}',
		'pill_one'  => $str( $item['pill_one'] ?? '' ),
		'format'    => $kind,
		'min_label' => $str( $item['min_label'] ?? '' ),
		'max_label' => $str( $item['max_label'] ?? '' ),
	);
}
if ( ! $sliders ) {
	return;
}

// ---- Model constants (behaviour.js reads the same values) ----
$time_pct = $num( $props['time_saved_pct'], 70 );
$loss_pct = $num( $props['loss_pct'], 2 );
$weeks    = $num( $props['weeks'], 52 );
$baseline = $num( $props['people_baseline'], 10 );
$baseline = $baseline > 0 ? $baseline : 10.0;

$in = array(
	'people' => $baseline,
	'hourly' => 0.0,
	'assets' => 0.0,
	'hours'  => 0.0,
);
foreach ( $sliders as $slider ) {
	$in[ $slider['key'] ] = $slider['value'];
}

// Same operation order as behaviour.js (and live), so the doubles agree.
$time_rate   = $time_pct / 100;
$loss_rate   = $loss_pct / 100;
$hours_saved = round( $in['hours'] * $weeks * $time_rate * ( $in['people'] / $baseline ) );
$labor       = round( $hours_saved * $in['hourly'] );
$loss        = round( $in['assets'] * $loss_rate );
$results     = array(
	'hours' => $format( $hours_saved, 'number' ),
	'labor' => $format( $labor, 'currency' ),
	'loss'  => $format( $loss, 'currency' ),
	'total' => $format( $labor + $loss, 'currency' ),
);

// ---- Breakdown rows ----
$icons = array(
	'time'   => '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"></circle><path d="M12 7v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>',
	'money'  => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M16.5 7.5c-.8-1.1-2.2-1.8-4-1.8-2.4 0-4 1.2-4 3s1.5 2.5 4 3.1c2.7.7 4.5 1.3 4.5 3.4 0 1.9-1.8 3.1-4.4 3.1-2.1 0-3.8-.8-4.8-2.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
	'shield' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v5c0 4.7-2.9 8.7-7 10-4.1-1.3-7-5.3-7-10V6l7-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path></svg>',
);
$row_icon = array(
	'hours' => 'time',
	'labor' => 'money',
	'loss'  => 'shield',
);
$rows     = array();
foreach ( is_array( $props['rows'] ) ? $props['rows'] : array() as $item ) {
	$key = is_array( $item ) ? $str( $item['key'] ?? '' ) : '';
	if ( ! isset( $row_icon[ $key ] ) ) {
		continue;
	}
	$icon     = $str( $item['icon'] ?? '' );
	$template = $str( $item['value'] ?? '' );
	$rows[]   = array(
		'key'      => $key,
		'icon'     => isset( $icons[ $icon ] ) ? $icon : $row_icon[ $key ],
		'title'    => $str( $item['title'] ?? '' ),
		'note'     => $str( $item['note'] ?? '' ),
		'template' => '' !== $template ? $template : '{n}',
	);
}

$inputs_label  = trim( (string) $props['inputs_label'] );
$inputs_text   = trim( (string) $props['inputs_text'] );
$note          = trim( (string) $props['note'] );
$results_label = trim( (string) $props['results_label'] );
$results_text  = trim( (string) $props['results_text'] );
$total_label   = trim( (string) $props['total_label'] );
$total_text    = trim( (string) $props['total_text'] );
$roi_text      = trim( (string) $props['roi_text'] );
$cta_label     = trim( (string) $props['cta_label'] );
$cta_url       = trim( (string) $props['cta_url'] );
$footer_text   = trim( (string) $props['footer_text'] );
$footer_label  = trim( (string) $props['footer_link_label'] );
$footer_url    = trim( (string) $props['footer_link_url'] );
$disclaimer    = trim( (string) $props['disclaimer'] );

// variant 2026 = the Figma "Pricing - New Jun 11 2026" card. Every addition it
// makes is gated on this flag, so the classic output stays byte-identical.
$is_2026 = '2026' === (string) $props['variant'];

// Figma's breakdown icons (exported from node 7169:88817), shipped in assets/.
$figma_icons = array(
	'time'   => 'icon-clock.svg',
	'money'  => 'icon-dollar.svg',
	'shield' => 'icon-shield.svg',
);
$icon_html = static function ( $icon ) use ( $is_2026, $icons, $figma_icons ) {
	$url = $is_2026 ? ap_ui_asset_url( 'pricing-calculator', $figma_icons[ $icon ] ) : '';
	if ( '' === $url ) {
		return $icons[ $icon ];
	}
	return '<img src="' . esc_url( $url ) . '" width="20" height="20" alt="" loading="lazy" decoding="async">';
};

// Ids pair each label with its slider; unique across the page.
$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-pricing-calc-' ) : 'ap-pricing-calc-' . substr( md5( uniqid( '', true ) ), 0, 8 );
?>
<section class="ap-pricing-calculator<?php echo $is_2026 ? ' ap-pricing-calculator--2026' : ''; ?>" data-ap-roi data-ap-roi-time-pct="<?php echo esc_attr( $plain( $time_pct ) ); ?>" data-ap-roi-loss-pct="<?php echo esc_attr( $plain( $loss_pct ) ); ?>" data-ap-roi-weeks="<?php echo esc_attr( $plain( $weeks ) ); ?>" data-ap-roi-base="<?php echo esc_attr( $plain( $baseline ) ); ?>">
	<div class="ap-pricing-calculator__card">
		<div class="ap-pricing-calculator__grid">
			<div class="ap-pricing-calculator__inputs">
				<?php if ( '' !== $inputs_label ) : ?>
					<p class="ap-pricing-calculator__label"><?php echo esc_html( $inputs_label ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $inputs_text ) : ?>
					<p class="ap-pricing-calculator__description"><?php echo esc_html( $inputs_text ); ?></p>
				<?php endif; ?>

				<div class="ap-pricing-calculator__sliders">
					<?php
					foreach ( $sliders as $slider ) :
						$id        = $uid . '-' . $slider['key'];
						$shown     = $format( $slider['value'], $slider['format'] );
						$pill_text = $fill( 1.0 === $slider['value'] && '' !== $slider['pill_one'] ? $slider['pill_one'] : $slider['pill'], $shown );
						$span      = $slider['max'] - $slider['min'];
						?>
						<div class="ap-pricing-calculator__slider">
							<div class="ap-pricing-calculator__slider-top">
								<label class="ap-pricing-calculator__slider-name" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $slider['label'] ); ?></label>
								<span class="ap-pricing-calculator__pill" data-ap-roi-pill="<?php echo esc_attr( $slider['key'] ); ?>" data-ap-roi-format="<?php echo esc_attr( $slider['format'] ); ?>" data-ap-roi-template="<?php echo esc_attr( $slider['pill'] ); ?>"<?php echo '' !== $slider['pill_one'] ? ' data-ap-roi-template-one="' . esc_attr( $slider['pill_one'] ) . '"' : ''; ?>><?php echo esc_html( $pill_text ); ?></span>
							</div>

							<input class="ap-pricing-calculator__range" id="<?php echo esc_attr( $id ); ?>" type="range" min="<?php echo esc_attr( $plain( $slider['min'] ) ); ?>" max="<?php echo esc_attr( $plain( $slider['max'] ) ); ?>" step="<?php echo esc_attr( $plain( $slider['step'] ) ); ?>" value="<?php echo esc_attr( $plain( $slider['value'] ) ); ?>" aria-valuetext="<?php echo esc_attr( $pill_text ); ?>" style="<?php echo esc_attr( '--ap-fill:' . $plain( ( $slider['value'] - $slider['min'] ) / $span * 100 ) . '%' ); ?>" data-ap-roi-input="<?php echo esc_attr( $slider['key'] ); ?>" />

							<?php if ( '' !== $slider['min_label'] || '' !== $slider['max_label'] ) : ?>
								<div class="ap-pricing-calculator__limits">
									<span><?php echo esc_html( $slider['min_label'] ); ?></span>
									<span><?php echo esc_html( $slider['max_label'] ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( '' !== $note ) : ?>
					<p class="ap-pricing-calculator__note"><?php echo esc_html( $note ); ?></p>
				<?php endif; ?>
			</div>

			<div class="ap-pricing-calculator__results">
				<?php if ( '' !== $results_label || '' !== $results_text ) : ?>
					<div class="ap-pricing-calculator__results-intro">
						<?php if ( '' !== $results_label ) : ?>
							<p class="ap-pricing-calculator__label ap-pricing-calculator__label--purple"><?php echo esc_html( $results_label ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $results_text ) : ?>
							<p class="ap-pricing-calculator__description"><?php echo esc_html( $results_text ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="ap-pricing-calculator__total-card">
					<?php if ( '' !== $total_label ) : ?>
						<p class="ap-pricing-calculator__kicker"><?php echo esc_html( $total_label ); ?></p>
					<?php endif; ?>
					<p class="ap-pricing-calculator__total" data-ap-roi-output="total"<?php echo $is_2026 ? ' aria-live="polite" aria-atomic="true"' : ''; ?>><?php echo esc_html( $results['total'] ); ?></p>
					<?php if ( '' !== $total_text ) : ?>
						<p class="ap-pricing-calculator__subtext"><?php echo esc_html( $total_text ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( $rows ) : ?>
					<div class="ap-pricing-calculator__breakdown">
						<?php foreach ( $rows as $row ) : ?>
							<div class="ap-pricing-calculator__row">
								<span class="ap-pricing-calculator__icon ap-pricing-calculator__icon--<?php echo esc_attr( $row['icon'] ); ?>" aria-hidden="true"><?php echo $icon_html( $row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup from the arrays above (or an esc_url'd asset URL), keyed by a validated enum. ?></span>
								<div>
									<?php if ( '' !== $row['title'] ) : ?>
										<p class="ap-pricing-calculator__row-title"><?php echo esc_html( $row['title'] ); ?></p>
									<?php endif; ?>
									<?php if ( '' !== $row['note'] ) : ?>
										<p class="ap-pricing-calculator__row-note"><?php echo esc_html( $row['note'] ); ?></p>
									<?php endif; ?>
								</div>
								<span class="ap-pricing-calculator__value" data-ap-roi-output="<?php echo esc_attr( $row['key'] ); ?>" data-ap-roi-template="<?php echo esc_attr( $row['template'] ); ?>"><?php echo esc_html( $fill( $row['template'], $results[ $row['key'] ] ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $roi_text ) : ?>
					<p class="ap-pricing-calculator__roi-note"><?php echo preg_replace( '/\*([^*\r\n]+)\*/', '<strong>$1</strong>', esc_html( $roi_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped first; only <strong> is added. ?></p>
				<?php endif; ?>

				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<div class="ap-pricing-calculator__buttons">
						<a class="ap-pricing-calculator__btn ap-pricing-calculator__btn--primary" href="<?php echo esc_url( $cta_url ); ?>"><span><?php echo esc_html( $cta_label ); ?></span></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( '' !== $footer_text || ( '' !== $footer_label && '' !== $footer_url ) ) : ?>
		<p class="ap-pricing-calculator__footer-link">
			<?php echo esc_html( $footer_text ); ?>
			<?php if ( '' !== $footer_label && '' !== $footer_url ) : ?>
				<a href="<?php echo esc_url( $footer_url, array( 'http', 'https', 'tel', 'mailto' ) ); ?>"><?php echo esc_html( $footer_label ); echo $is_2026 ? '<span class="ap-pricing-calculator__arrow" aria-hidden="true">&#8594;</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>
<?php if ( '' !== $disclaimer ) : ?>
	<p class="ap-pricing-calculator__disclaimer"><?php echo esc_html( $disclaimer ); ?></p>
<?php endif; ?>
</section>
