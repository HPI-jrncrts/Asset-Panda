<?php
/**
 * roi-calculator — the legacy savings calculator (live /roi-calculator/).
 *
 * A card with the input groups on the left (the first always open, the others
 * collapsible <details>, each closing on its subtotal) and a sticky "Your
 * Savings" panel on the right; one column when the component is narrow. The
 * layout keys off the component's own width (container queries), because it
 * sits in an 800px article column on staging.
 *
 * The first paint is computed here with live's formulas on the same field
 * strings, in the same operation order, and formatted exactly as live's
 * U(x.toFixed(2)) does, so the card reads right before (and without) the
 * script. behaviour.js recomputes on every change.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Scalar prop -> trimmed string; anything else (an array from a bad save) -> ''.
$str = static function ( $value ) {
	if ( is_bool( $value ) || ! is_scalar( $value ) ) {
		return '';
	}
	return trim( (string) $value );
};

$num = static function ( $value, $fallback ) {
	return is_numeric( $value ) ? (float) $value : (float) $fallback;
};

// A number as an attribute string: 8 not 8.0.
$plain = static function ( $value ) {
	$value = (float) $value;
	if ( floor( $value ) === $value && abs( $value ) < 1e15 ) {
		return (string) (int) $value;
	}
	return rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );
};

/*
 * ---- The model's fields ----
 * Text fields carry live's keystroke filter (behaviour.js holds the same three
 * patterns); the three % fields are live's 0-100 sliders (step 1). A field the
 * editor leaves out is still in the model with live's starting value, as a
 * hidden input. `users` is drawn but no formula reads it — as on live.
 */
$filters  = array(
	'integer' => '/^\d*$/',
	'decimal' => '/^(\d*(\.\d{0,2})?)$/',
	'percent' => '/^(\d{0,2}(\.\d{0,2})?|100)$/',
);
$inputs   = array(
	'users'         => array( 'integer', 'numeric', '1' ),
	'assets'        => array( 'integer', 'numeric', '1000' ),
	'asset_value'   => array( 'decimal', 'decimal', '150' ),
	'loss_pct'      => array( 'percent', 'decimal', '2' ),
	'tracked_pct'   => array( 'slider', '', '100' ),
	'ghost_pct'     => array( 'slider', '', '15' ),
	'remaining_pct' => array( 'slider', '', '50' ),
	'federal_tax'   => array( 'percent', 'decimal', '19.8' ),
	'state_tax'     => array( 'percent', 'decimal', '6.2' ),
	'property_tax'  => array( 'percent', 'decimal', '2' ),
	'insurance'     => array( 'percent', 'decimal', '.03' ),
	'search_days'   => array( 'integer', 'numeric', '20' ),
	'salary'        => array( 'decimal', 'decimal', '32800' ),
);
$outputs  = array( 'loss', 'ghost_value', 'ghost_cost', 'search' );
$clean    = static function ( $key, $raw ) use ( $inputs, $filters ) {
	$kind = $inputs[ $key ][0];
	if ( 'slider' === $kind ) {
		return is_numeric( $raw ) ? (string) (int) max( 0, min( 100, round( (float) $raw ) ) ) : $inputs[ $key ][2];
	}
	return preg_match( $filters[ $kind ], $raw ) ? $raw : '';
};

// ---- Groups ----
$groups = array();
foreach ( is_array( $props['groups'] ) ? $props['groups'] : array() as $item ) {
	$item     = is_array( $item ) ? $item : array();
	$state    = strtolower( $str( $item['state'] ?? '' ) );
	$groups[] = array(
		'title' => $str( $item['title'] ?? '' ),
		'state' => in_array( $state, array( 'static', 'open', 'closed' ), true ) ? $state : 'closed',
		'rows'  => array(),
	);
}

// ---- Fields and subtotal rows, placed into their groups ----
$values = array();
foreach ( is_array( $props['fields'] ) ? $props['fields'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$key   = $str( $item['key'] ?? '' );
	$group = (int) $num( $item['group'] ?? null, 1 ) - 1;
	if ( ! isset( $groups[ $group ] ) ) {
		continue;
	}
	if ( in_array( $key, $outputs, true ) ) {
		$groups[ $group ]['rows'][] = array(
			'type'  => 'subtotal',
			'key'   => $key,
			'label' => $str( $item['label'] ?? '' ),
		);
		continue;
	}
	if ( ! isset( $inputs[ $key ] ) || isset( $values[ $key ] ) ) {
		continue;
	}
	$values[ $key ]             = $clean( $key, $str( $item['value'] ?? '' ) );
	$groups[ $group ]['rows'][] = array(
		'type'   => 'slider' === $inputs[ $key ][0] ? 'slider' : 'field',
		'key'    => $key,
		'label'  => $str( $item['label'] ?? '' ),
		'help'   => $str( $item['help'] ?? '' ),
		'suffix' => $str( $item['suffix'] ?? '' ),
	);
}
$groups = array_values(
	array_filter(
		$groups,
		static function ( $group ) {
			return (bool) $group['rows'];
		}
	)
);
if ( ! $groups ) {
	return;
}
$hidden = array();
foreach ( $inputs as $key => $spec ) {
	if ( ! isset( $values[ $key ] ) ) {
		$values[ $key ] = $spec[2];
		$hidden[]       = $key;
	}
}

// ---- Model constants (behaviour.js reads the same values from the root) ----
$hours_day  = $num( $props['hours_per_day'], 8 );
$hours_year = $num( $props['work_hours_per_year'], 2080 );
$days_year  = $num( $props['days_per_year'], 365 );
$currency   = $str( $props['currency'] );

/*
 * ---- First paint: live's maths in PHP ----
 * JavaScript's parseInt / parseFloat on the (already filtered) field strings;
 * NaN where live gets NaN (e.g. a lone "."), so the text matches live's too.
 */
$parse_int   = static function ( $value ) {
	$value = (string) $value;
	return preg_match( '/^\s*([+-]?\d+)/', $value, $m ) ? (float) $m[1] : NAN;
};
$parse_float = static function ( $value ) {
	$value = (string) $value;
	return preg_match( '/^\s*([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)/', $value, $m ) ? (float) $m[1] : NAN;
};
$or0         = static function ( $value ) {
	return '' !== $value ? $value : '0';
};

$v           = $values;
$loss        = $parse_int( $or0( $v['assets'] ) ) * $parse_float( $or0( $v['asset_value'] ) ) * ( $parse_float( $or0( $v['loss_pct'] ) ) / 100 ) * ( $parse_int( $v['tracked_pct'] ) / 100 );
$ghost_value = $parse_int( $or0( $v['assets'] ) ) * $parse_float( $or0( $v['asset_value'] ) ) * ( $parse_int( $v['tracked_pct'] ) / 100 ) * ( $parse_int( $v['ghost_pct'] ) / 100 ) * ( $parse_int( $v['remaining_pct'] ) / 100 );
$ghost_cost  = $ghost_value * ( ( $parse_float( $or0( $v['federal_tax'] ) ) + $parse_float( $or0( $v['state_tax'] ) ) + $parse_float( $or0( $v['property_tax'] ) ) ) / 100 ) + 0.01 * $ghost_value * $parse_float( $or0( $v['insurance'] ) );
$search      = ( '' !== $v['tracked_pct'] ? ( is_numeric( $v['tracked_pct'] ) ? (float) $v['tracked_pct'] : NAN ) / 100 : 0.0 ) * $parse_int( $or0( $v['search_days'] ) ) * $hours_day * ( $parse_float( $or0( $v['salary'] ) ) / $hours_year );
$total       = $loss + $ghost_cost + $search;

/*
 * Number.prototype.toFixed(2), exactly: the nearest multiple of 0.01 to the
 * double's exact binary value, ties to the larger. Splits the double into
 * mantissa x 2^exp so no decimal rounding happens on the way.
 */
$to_fixed = static function ( $x ) {
	if ( is_nan( $x ) ) {
		return 'NaN';
	}
	if ( is_infinite( $x ) ) {
		return $x > 0 ? 'Infinity' : '-Infinity';
	}
	$sign = $x < 0 ? '-' : '';
	$x    = abs( $x );
	if ( $x >= 1e21 ) {
		return $sign . str_replace( array( 'E+', 'E' ), array( 'e+', 'e' ), var_export( $x, true ) );
	}
	$bits     = unpack( 'J', pack( 'E', $x ) )[1];
	$exponent = ( $bits >> 52 ) & 0x7FF;
	$mantissa = $bits & 0xFFFFFFFFFFFFF;
	if ( 0 === $exponent ) {
		$exponent = 1;
	} else {
		$mantissa |= 0x10000000000000;
	}
	$shift = 1075 - $exponent; // $x = $mantissa / 2^$shift
	if ( $shift <= 0 ) {
		$cents = function_exists( 'bcmul' ) ? bcmul( (string) $mantissa, bcpow( '2', (string) -$shift ) ) . '00' : sprintf( '%.0F', $x ) . '00';
	} elseif ( $shift > 61 ) {
		$cents = '0'; // < 0.002: rounds to 0.00
	} else {
		$scaled = $mantissa * 100; // < 2^60, exact in a 64-bit int
		$whole  = intdiv( $scaled, 1 << $shift );
		$rest   = $scaled - $whole * ( 1 << $shift );
		$cents  = (string) ( 2 * $rest >= ( 1 << $shift ) ? $whole + 1 : $whole );
	}
	$cents = str_pad( $cents, 3, '0', STR_PAD_LEFT );
	return $sign . substr( $cents, 0, -2 ) . '.' . substr( $cents, -2 );
};

/*
 * Number(fixed).toLocaleString('en-US'): the double's shortest round-trip
 * digits, grouped by thousands, at most 3 decimals (never reached: the string
 * has 2). Then live's U() puts back a trailing zero (.50).
 */
$shown = static function ( $fixed ) {
	if ( 'NaN' === $fixed ) {
		return 'NaN';
	}
	if ( 'Infinity' === $fixed || '-Infinity' === $fixed ) {
		return ( '-' === $fixed[0] ? '-' : '' ) . "\u{221E}";
	}
	$y    = (float) $fixed;
	$sign = $y < 0 ? '-' : '';
	$y    = abs( $y );
	for ( $p = 0; $p < 17; $p++ ) {
		$sci = str_replace( ',', '.', sprintf( '%.' . $p . 'e', $y ) );
		if ( (float) $sci === $y ) {
			break;
		}
	}
	list( $mant, $exp ) = explode( 'e', $sci );
	$digits             = str_replace( '.', '', $mant );
	$point              = (int) $exp + 1; // digits before the decimal point
	if ( $point <= 0 ) {
		$int  = '0';
		$frac = str_repeat( '0', -$point ) . $digits;
	} elseif ( $point >= strlen( $digits ) ) {
		$int  = $digits . str_repeat( '0', $point - strlen( $digits ) );
		$frac = '';
	} else {
		$int  = substr( $digits, 0, $point );
		$frac = substr( $digits, $point );
	}
	$frac = rtrim( substr( $frac, 0, 3 ), '0' );
	$text = $sign . strrev( implode( ',', str_split( strrev( $int ), 3 ) ) ) . ( '' !== $frac ? '.' . $frac : '' );
	return preg_match( '/^\d*\.(10|20|30|40|50|60|70|80|90)$/', $fixed ) ? $text . '0' : $text;
};

$results = array(
	'loss'        => $shown( $to_fixed( $loss ) ),
	'ghost_value' => $shown( $to_fixed( $ghost_value ) ),
	'ghost_cost'  => $shown( $to_fixed( $ghost_cost ) ),
	'search'      => $shown( $to_fixed( $search ) ),
	'total'       => $shown( $to_fixed( $total ) ),
	'daily'       => $shown( $to_fixed( $total / $days_year ) ),
);

// ---- Savings panel ----
$icons    = array(
	'shield' => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v5c0 4.7-2.9 8.7-7 10-4.1-1.3-7-5.3-7-10V6l7-3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path></svg>',
	'money'  => '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M16.5 7.5c-.8-1.1-2.2-1.8-4-1.8-2.4 0-4 1.2-4 3s1.5 2.5 4 3.1c2.7.7 4.5 1.3 4.5 3.4 0 1.9-1.8 3.1-4.4 3.1-2.1 0-3.8-.8-4.8-2.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
	'search' => '<svg viewBox="0 0 24 24" fill="none"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="2"></circle><path d="M15.5 15.5L20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
	'chart'  => '<svg viewBox="0 0 24 24" fill="none"><path d="M4 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M7 16v-4M12 16V8M17 16v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>',
);
$row_icon = array(
	'loss'        => 'shield',
	'ghost_value' => 'chart',
	'ghost_cost'  => 'money',
	'search'      => 'search',
);
$rows     = array();
foreach ( is_array( $props['results'] ) ? $props['results'] : array() as $item ) {
	$key = is_array( $item ) ? $str( $item['key'] ?? '' ) : '';
	if ( ! isset( $row_icon[ $key ] ) ) {
		continue;
	}
	$icon   = $str( $item['icon'] ?? '' );
	$rows[] = array(
		'key'   => $key,
		'icon'  => isset( $icons[ $icon ] ) ? $icon : $row_icon[ $key ],
		'label' => $str( $item['label'] ?? '' ),
	);
}

$results_title = $str( $props['results_title'] );
$total_label   = $str( $props['total_label'] );
$daily_label   = $str( $props['daily_label'] );
$cta_label     = $str( $props['cta_label'] );
$cta_url       = esc_url( $str( $props['cta_url'] ) ); // '' for a refused scheme: no button then

// Ids pair each label, help tip and input; unique across the page.
$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-roi-calc-' ) : 'ap-roi-calc-' . substr( md5( uniqid( '', true ) ), 0, 8 );

$help_markup = static function ( $id, $row ) {
	if ( '' === $row['help'] ) {
		return '';
	}
	return '<span class="ap-roi-calculator__help">'
		. '<button class="ap-roi-calculator__info" type="button" aria-describedby="' . esc_attr( $id . '-help' ) . '"><span aria-hidden="true">i</span><span class="ap-roi-calculator__sr">' . esc_html( $row['label'] ) . '</span></button>'
		. '<span class="ap-roi-calculator__tip" id="' . esc_attr( $id . '-help' ) . '" role="tooltip">' . esc_html( $row['help'] ) . '</span>'
		. '</span>';
};
?>
<section class="ap-roi-calculator" data-ap-roicalc data-ap-roicalc-currency="<?php echo esc_attr( $currency ); ?>" data-ap-roicalc-hours-day="<?php echo esc_attr( $plain( $hours_day ) ); ?>" data-ap-roicalc-hours-year="<?php echo esc_attr( $plain( $hours_year ) ); ?>" data-ap-roicalc-days-year="<?php echo esc_attr( $plain( $days_year ) ); ?>">
	<div class="ap-roi-calculator__card">
		<div class="ap-roi-calculator__inputs">
			<?php
			foreach ( $groups as $g => $group ) :
				$static  = 'static' === $group['state'];
				$tag     = $static ? 'div' : 'details';
				$classes = 'ap-roi-calculator__group' . ( $static ? ' ap-roi-calculator__group--static' : '' ) . ( 0 === $g ? ' ap-roi-calculator__group--first' : '' ) . ( count( $groups ) - 1 === $g ? ' ap-roi-calculator__group--last' : '' );
				?>
				<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- one of two literal tag names. ?> class="<?php echo esc_attr( $classes ); ?>"<?php echo 'open' === $group['state'] ? ' open' : ''; ?>>
					<?php if ( $static ) : ?>
						<div class="ap-roi-calculator__group-head">
							<h2 class="ap-roi-calculator__group-title"><?php echo esc_html( $group['title'] ); ?></h2>
						</div>
					<?php else : ?>
						<summary class="ap-roi-calculator__group-head">
							<h2 class="ap-roi-calculator__group-title"><?php echo esc_html( $group['title'] ); ?></h2>
							<span class="ap-roi-calculator__chevron" aria-hidden="true"></span>
						</summary>
					<?php endif; ?>

					<div class="ap-roi-calculator__group-body">
						<?php
						foreach ( $group['rows'] as $row ) :
							if ( 'subtotal' === $row['type'] ) :
								?>
								<div class="ap-roi-calculator__subtotal">
									<span class="ap-roi-calculator__subtotal-label"><?php echo esc_html( $row['label'] ); ?></span>
									<span class="ap-roi-calculator__subtotal-value" data-ap-roicalc-output="<?php echo esc_attr( $row['key'] ); ?>"><?php echo esc_html( $currency . $results[ $row['key'] ] ); ?></span>
								</div>
								<?php
								continue;
							endif;

							$id    = $uid . '-' . str_replace( '_', '-', $row['key'] );
							$value = $values[ $row['key'] ];
							$desc  = '' !== $row['help'] ? ' aria-describedby="' . esc_attr( $id . '-help' ) . '"' : '';

							if ( 'slider' === $row['type'] ) :
								$template = '{n}' . $row['suffix'];
								$text     = str_replace( '{n}', $value, $template );
								?>
								<div class="ap-roi-calculator__field ap-roi-calculator__field--slider">
									<div class="ap-roi-calculator__field-head">
										<label class="ap-roi-calculator__field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row['label'] ); ?></label>
										<?php echo $help_markup( $id, $row ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside the closure. ?>
										<span class="ap-roi-calculator__stepper">
											<button class="ap-roi-calculator__step" type="button" data-ap-roicalc-step="-1" data-ap-roicalc-for="<?php echo esc_attr( $row['key'] ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>"><span aria-hidden="true">&minus;</span><span class="ap-roi-calculator__sr"><?php echo esc_html( 'Decrease ' . $row['label'] ); ?></span></button>
											<span class="ap-roi-calculator__pill" data-ap-roicalc-pill="<?php echo esc_attr( $row['key'] ); ?>" data-ap-roicalc-template="<?php echo esc_attr( $template ); ?>"><?php echo esc_html( $text ); ?></span>
											<button class="ap-roi-calculator__step" type="button" data-ap-roicalc-step="1" data-ap-roicalc-for="<?php echo esc_attr( $row['key'] ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>"><span aria-hidden="true">+</span><span class="ap-roi-calculator__sr"><?php echo esc_html( 'Increase ' . $row['label'] ); ?></span></button>
										</span>
									</div>
									<input class="ap-roi-calculator__range" id="<?php echo esc_attr( $id ); ?>" type="range" min="0" max="100" step="1" value="<?php echo esc_attr( $value ); ?>" aria-valuetext="<?php echo esc_attr( $text ); ?>" style="<?php echo esc_attr( '--ap-fill:' . $value . '%' ); ?>" data-ap-roicalc-field="<?php echo esc_attr( $row['key'] ); ?>"<?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr above. ?> />
									<div class="ap-roi-calculator__limits" aria-hidden="true">
										<span><?php echo esc_html( '0' . $row['suffix'] ); ?></span>
										<span><?php echo esc_html( '100' . $row['suffix'] ); ?></span>
									</div>
								</div>
							<?php else : ?>
								<div class="ap-roi-calculator__field">
									<div class="ap-roi-calculator__field-head">
										<label class="ap-roi-calculator__field-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $row['label'] ); ?></label>
										<?php echo $help_markup( $id, $row ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside the closure. ?>
									</div>
									<div class="ap-roi-calculator__control<?php echo '' !== $row['suffix'] ? ' ap-roi-calculator__control--suffix' : ''; ?>">
										<input class="ap-roi-calculator__input" id="<?php echo esc_attr( $id ); ?>" type="text" inputmode="<?php echo esc_attr( $inputs[ $row['key'] ][1] ); ?>" autocomplete="off" value="<?php echo esc_attr( $value ); ?>" data-ap-roicalc-field="<?php echo esc_attr( $row['key'] ); ?>" data-ap-roicalc-filter="<?php echo esc_attr( $inputs[ $row['key'] ][0] ); ?>"<?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr above. ?> />
										<?php if ( '' !== $row['suffix'] ) : ?>
											<span class="ap-roi-calculator__suffix" aria-hidden="true"><?php echo esc_html( $row['suffix'] ); ?></span>
										<?php endif; ?>
									</div>
								</div>
								<?php
							endif;
						endforeach;
						?>
					</div>
				</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- one of two literal tag names. ?>>
			<?php endforeach; ?>

			<?php foreach ( $hidden as $key ) : ?>
				<input type="hidden" value="<?php echo esc_attr( $values[ $key ] ); ?>" data-ap-roicalc-field="<?php echo esc_attr( $key ); ?>" />
			<?php endforeach; ?>
		</div>

		<div class="ap-roi-calculator__results">
			<?php if ( '' !== $results_title ) : ?>
				<h2 class="ap-roi-calculator__results-title"><?php echo esc_html( $results_title ); ?></h2>
			<?php endif; ?>

			<?php if ( $rows ) : ?>
				<div class="ap-roi-calculator__breakdown">
					<?php foreach ( $rows as $row ) : ?>
						<div class="ap-roi-calculator__row">
							<span class="ap-roi-calculator__icon ap-roi-calculator__icon--<?php echo esc_attr( $row['icon'] ); ?>" aria-hidden="true"><?php echo $icons[ $row['icon'] ]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup from the array above, keyed by a validated enum. ?></span>
							<span class="ap-roi-calculator__row-text">
								<span class="ap-roi-calculator__row-label"><?php echo esc_html( $row['label'] ); ?></span>
								<span class="ap-roi-calculator__row-value" data-ap-roicalc-output="<?php echo esc_attr( $row['key'] ); ?>"><?php echo esc_html( $currency . $results[ $row['key'] ] ); ?></span>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="ap-roi-calculator__total-card">
				<?php if ( '' !== $total_label ) : ?>
					<p class="ap-roi-calculator__kicker"><?php echo esc_html( $total_label ); ?></p>
				<?php endif; ?>
				<p class="ap-roi-calculator__total" data-ap-roicalc-output="total" aria-live="polite"><?php echo esc_html( $currency . $results['total'] ); ?></p>
				<?php if ( '' !== $daily_label ) : ?>
					<div class="ap-roi-calculator__daily">
						<p class="ap-roi-calculator__daily-label"><?php echo esc_html( $daily_label ); ?></p>
						<p class="ap-roi-calculator__daily-value" data-ap-roicalc-output="daily"><?php echo esc_html( $currency . $results['daily'] ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
				<a class="ap-roi-calculator__btn" href="<?php echo $cta_url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() above. ?>"><span><?php echo esc_html( $cta_label ); ?></span></a>
			<?php endif; ?>
		</div>
	</div>
</section>
