<?php
/**
 * tab-showcase — the tabbed tracking/intelligence panel.
 *
 * Matches the live .apx-bat on /asset-intelligence-platform/: radio inputs drive
 * the tabs in CSS alone (no script), each panel pairing an asset record card
 * with the intelligence that record gains, then an AI insight. A closing band
 * defines the platform.
 *
 * The radio group needs ids, and a page can hold more than one of these, so the
 * ids are numbered per instance. The inner class names are the live ones.
 *
 * As on live, no radio starts checked: until a tab is clicked the two panels
 * crossfade on their own (wide screens) and the first panel shows (narrow ones).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

static $ap_tabs_instance = 0;
++$ap_tabs_instance;
$group = 'ap-tabs-' . $ap_tabs_instance;

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="g">' . $escaped . '</span>', $html );
	}
	return $html;
};

$slots  = array( 'fire', 'police' );
$tones  = array( 'due', 'over', 'ok', 'info' );
$panels = array_values(
	array_filter(
		is_array( $props['panels'] ) ? $props['panels'] : array(),
		static function ( $p ) {
			return is_array( $p ) && '' !== trim( (string) ( $p['tab_label'] ?? '' ) );
		}
	)
);

if ( empty( $panels ) ) {
	return;
}

$panels = array_slice( $panels, 0, 2 );
foreach ( $panels as $i => $panel ) {
	$panels[ $i ]['slot'] = in_array( $panel['slot'] ?? '', $slots, true ) ? $panel['slot'] : $slots[ $i ];
	$panels[ $i ]['id']   = $group . '-' . $panels[ $i ]['slot'];
}

$arr = static function ( $value ) {
	return is_array( $value ) ? $value : array();
};

// Fixed chevrons (decorative) for the side arrows and the arrow between the cards.
$ap_chevrons = array(
	'prev' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="15 18 9 12 15 6"></polyline></svg>',
	'next' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="9 18 15 12 9 6"></polyline></svg>',
	'mid'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="7 7 12 12 7 17"></polyline><polyline points="13 7 18 12 13 17"></polyline></svg>',
);

$ap_label = '' !== trim( (string) $props['eyebrow'] ) ? $props['eyebrow'] : $props['title'];
?>
<section class="ap-tab-showcase" aria-label="<?php echo esc_attr( wp_strip_all_tags( $ap_label ) ); ?>">
	<div class="ap-tab-showcase__bg" aria-hidden="true"></div>
	<div class="ap-tab-showcase__wrap">
		<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
			<p class="ap-tab-showcase__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h2 class="ap-tab-showcase__title"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
		<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
			<p class="ap-tab-showcase__lead"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
		<?php endif; ?>

		<div class="ap-tab-showcase__tabs">
			<?php foreach ( $panels as $i => $panel ) : ?>
				<input type="radio" name="<?php echo esc_attr( $group ); ?>" id="<?php echo esc_attr( $panel['id'] ); ?>" class="ap-tab-showcase__radio ap-tab-showcase__radio--<?php echo esc_attr( $panel['slot'] ); ?>" />
			<?php endforeach; ?>
			<?php foreach ( $panels as $panel ) : ?>
				<?php // Prefix and label run together, the space inside the prefix span, exactly as live. ?>
				<label class="ap-tab-showcase__tab ap-tab-showcase__tab--<?php echo esc_attr( $panel['slot'] ); ?>" for="<?php echo esc_attr( $panel['id'] ); ?>"><?php if ( ! empty( $panel['tab_prefix'] ) ) : ?><span class="lg"><?php echo esc_html( trim( (string) $panel['tab_prefix'] ) ); ?> </span><?php endif; ?><?php echo esc_html( $panel['tab_label'] ); ?></label>
			<?php endforeach; ?>
		</div>

		<div class="ap-tab-showcase__stage">
			<?php foreach ( $panels as $i => $panel ) : ?>
				<label class="ap-tab-showcase__nav ap-tab-showcase__nav--<?php echo 0 === $i ? 'prev' : 'next'; ?>" for="<?php echo esc_attr( $panel['id'] ); ?>" aria-label="<?php echo esc_attr( trim( ( $panel['tab_prefix'] ?? '' ) . ' ' . $panel['tab_label'] ) ); ?>"><?php echo $ap_chevrons[ 0 === $i ? 'prev' : 'next' ]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></label>
			<?php endforeach; ?>

			<?php foreach ( $panels as $panel ) : ?>
				<?php
				$asset    = $arr( $panel['asset'] ?? null );
				$features = array_values( array_filter( $arr( $panel['features'] ?? null ), static function ( $f ) { return is_array( $f ) && '' !== trim( (string) ( $f['title'] ?? '' ) ); } ) );
				$insight  = $arr( $panel['insight'] ?? null );
				$rows     = array_values( array_filter( $arr( $asset['rows'] ?? null ), static function ( $r ) { return is_array( $r ) && '' !== trim( (string) ( $r['label'] ?? '' ) ); } ) );
				?>
				<div class="ap-tab-showcase__panel ap-tab-showcase__panel--<?php echo esc_attr( $panel['slot'] ); ?>">
					<div class="ap-tab-showcase__cards">
						<div class="ap-tab-showcase__track">
							<?php if ( '' !== trim( (string) $props['track_label'] ) ) : ?>
								<img class="ap-tab-showcase__label ap-tab-showcase__label--track" src="<?php echo esc_url( $props['track_label'] ); ?>" alt="<?php echo esc_attr( $props['track_label_alt'] ); ?>" loading="lazy" decoding="async" />
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $props['track_connector'] ) ) : ?>
								<img class="ap-tab-showcase__conn ap-tab-showcase__conn--track" src="<?php echo esc_url( $props['track_connector'] ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
							<?php endif; ?>
							<div class="ap-tab-showcase__card ap-tab-showcase__trackcard">
								<?php if ( ! empty( $asset['image'] ) ) : ?>
									<div class="ap-tab-showcase__veh"><img src="<?php echo esc_url( $asset['image'] ); ?>" alt="<?php echo esc_attr( $asset['image_alt'] ?? '' ); ?>" width="259" height="173" loading="lazy" decoding="async" /></div>
								<?php endif; ?>
								<?php if ( ! empty( $asset['name'] ) ) : ?>
									<h3 class="ap-tab-showcase__vehname"><?php echo esc_html( $asset['name'] ); ?></h3>
								<?php endif; ?>
								<?php if ( ! empty( $asset['tag'] ) ) : ?>
									<span class="ap-tab-showcase__vehtag"><?php echo esc_html( $asset['tag'] ); ?></span>
								<?php endif; ?>
								<?php if ( $rows ) : ?>
									<div class="ap-tab-showcase__dl">
										<?php foreach ( $rows as $row ) : ?>
											<div class="ap-tab-showcase__row">
												<span class="k"><?php if ( ! empty( $row['icon'] ) ) : ?><img class="i" src="<?php echo esc_url( $row['icon'] ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" /><?php endif; ?><?php echo esc_html( $row['label'] ); ?></span>
												<span class="v<?php echo 'ok' === ( $row['tone'] ?? '' ) ? ' ok' : ''; ?>"><?php echo esc_html( $row['value'] ?? '' ); ?></span>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="ap-tab-showcase__mid" aria-hidden="true"><span><?php echo $ap_chevrons['mid']; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span></div>

						<div class="ap-tab-showcase__intel">
							<?php if ( '' !== trim( (string) $props['intel_label'] ) ) : ?>
								<img class="ap-tab-showcase__label ap-tab-showcase__label--intel" src="<?php echo esc_url( $props['intel_label'] ); ?>" alt="<?php echo esc_attr( $props['intel_label_alt'] ); ?>" loading="lazy" decoding="async" />
							<?php endif; ?>
							<?php if ( '' !== trim( (string) $props['intel_connector'] ) ) : ?>
								<img class="ap-tab-showcase__conn ap-tab-showcase__conn--intel" src="<?php echo esc_url( $props['intel_connector'] ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
							<?php endif; ?>
							<div class="ap-tab-showcase__card ap-tab-showcase__intelcard">
								<?php if ( $features ) : ?>
									<ul class="ap-tab-showcase__feat">
										<?php foreach ( $features as $feature ) : ?>
											<?php $tone = in_array( $feature['tone'] ?? '', $tones, true ) ? $feature['tone'] : 'info'; ?>
											<li>
												<span class="ap-tab-showcase__ficon"><?php if ( ! empty( $feature['icon'] ) ) : ?><img src="<?php echo esc_url( $feature['icon'] ); ?>" alt="" loading="lazy" decoding="async" /><?php endif; ?></span>
												<span class="ap-tab-showcase__ftext"><b><?php echo esc_html( $feature['title'] ); ?></b><span><?php echo esc_html( $feature['note'] ?? '' ); ?></span></span>
												<?php if ( ! empty( $feature['chip'] ) ) : ?>
													<span class="ap-tab-showcase__badge b-<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $feature['chip'] ); ?></span>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<?php if ( $insight ) : ?>
									<div class="ap-tab-showcase__ai">
										<?php if ( ! empty( $insight['spark'] ) ) : ?>
											<img class="spark" src="<?php echo esc_url( $insight['spark'] ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
										<?php endif; ?>
										<h4><?php echo esc_html( $insight['title'] ?? 'AI Insight' ); ?></h4>
										<?php if ( ! empty( $insight['body'] ) ) : ?>
											<p class="t"><?php echo ap_ui_brandmarks( esc_html( $insight['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $insight['note'] ) ) : ?>
											<p class="d"><?php echo esc_html( $insight['note'] ); ?></p>
										<?php endif; ?>
										<div class="act">
											<?php if ( ! empty( $insight['cta_label'] ) && ! empty( $insight['cta_url'] ) ) : ?>
												<a class="cta" href="<?php echo esc_url( $insight['cta_url'] ); ?>"><?php echo esc_html( $insight['cta_label'] ); ?></a>
											<?php endif; ?>
											<?php if ( ! empty( $insight['confidence'] ) ) : ?>
												<span class="conf"><?php echo esc_html( $insight['confidence'] ); ?></span>
											<?php endif; ?>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( '' !== trim( (string) $props['definition_title'] ) || '' !== trim( (string) $props['definition_body'] ) ) : ?>
			<div class="ap-tab-showcase__cta">
				<?php if ( '' !== trim( (string) $props['definition_orb'] ) ) : ?>
					<?php if ( '' !== trim( (string) $props['definition_orb_alt'] ) ) : ?>
						<img src="<?php echo esc_url( $props['definition_orb'] ); ?>" alt="<?php echo esc_attr( $props['definition_orb_alt'] ); ?>" loading="lazy" decoding="async" />
					<?php else : ?>
						<img src="<?php echo esc_url( $props['definition_orb'] ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
					<?php endif; ?>
				<?php endif; ?>
				<div>
					<?php if ( '' !== trim( (string) $props['definition_title'] ) ) : ?>
						<h3><?php echo ap_ui_brandmarks( esc_html( $props['definition_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['definition_body'] ) ) : ?>
						<p><?php echo ap_ui_brandmarks( esc_html( $props['definition_body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
