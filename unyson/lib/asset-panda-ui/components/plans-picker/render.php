<?php
/**
 * plans-picker — the pricing v2 plans section.
 *
 * Built from the Asset Panda Pricing v2 mockup: a centred heading, a row of
 * chips asking how many records the visitor needs, the plan cards, and a strip
 * explaining what counts as a record.
 *
 * The chips are progressive enhancement. Every card is visible without
 * JavaScript; behaviour.js only flags the card a chip points at, writes the
 * answer line, and tells the compare table which plan was picked. Chips and
 * cards are paired by a slug of the plan name, so "Growth" and "growth" match.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plan name -> the key chips and cards are paired by.
$ap_key = static function ( $name ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( trim( (string) $name ) ) ), '-' );
};

// A one-per-line text field -> its non-empty lines.
$ap_lines = static function ( $text ) {
	if ( is_array( $text ) ) {
		$text = implode( "\n", array_filter( $text, 'is_scalar' ) );
	}
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ), 'strlen' ) );
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
	$plans[] = array(
		'key'           => $ap_key( $name ),
		'name'          => $name,
		'who'           => trim( (string) ( $row['who'] ?? '' ) ),
		'price'         => trim( (string) ( $row['price'] ?? '' ) ),
		'price_suffix'  => trim( (string) ( $row['price_suffix'] ?? '' ) ),
		'per'           => trim( (string) ( $row['per'] ?? '' ) ),
		'annual'        => trim( (string) ( $row['annual'] ?? '' ) ),
		'cta_label'     => trim( (string) ( $row['cta_label'] ?? '' ) ),
		'cta_url'       => trim( (string) ( $row['cta_url'] ?? '' ) ),
		'records'       => trim( (string) ( $row['records'] ?? '' ) ),
		'records_label' => trim( (string) ( $row['records_label'] ?? '' ) ),
		'plus'          => trim( (string) ( $row['plus'] ?? '' ) ),
		'includes'      => $ap_lines( $row['includes'] ?? '' ),
		'flag'          => trim( (string) ( $row['flag'] ?? '' ) ),
	);
}

$title = trim( (string) $props['title'] );
if ( empty( $plans ) ) {
	return;
}

// The first plan is the lead (entry) plan; the clean variant draws it as a
// wide card. lead_position "bottom" moves it after the others, in the DOM too,
// so reading and tab order follow what is seen; "none" has no lead card.
$lead_key = 'none' === $props['lead_position'] ? '' : $plans[0]['key'];
if ( 'bottom' === $props['lead_position'] && count( $plans ) > 1 ) {
	$plans[] = array_shift( $plans );
}

// Chips only make sense when they point at a card that exists.
$plan_keys = array_column( $plans, 'key' );
$chips     = array();
foreach ( is_array( $props['chips'] ) ? $props['chips'] : array() as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	$label = trim( (string) ( $row['label'] ?? '' ) );
	$plan  = trim( (string) ( $row['plan'] ?? '' ) );
	if ( '' === $label || ! in_array( $ap_key( $plan ), $plan_keys, true ) ) {
		continue;
	}
	$chips[] = array(
		'label' => $label,
		'key'   => $ap_key( $plan ),
		'plan'  => $plan,
		'note'  => trim( (string) ( $row['note'] ?? '' ) ),
	);
}

// A chip pressed on load (rendered pressed here, so it needs no script): the
// first chip pointing at default_plan.
$default_chip = null;
$default_key  = $ap_key( $props['default_plan'] );
foreach ( $chips as $chip ) {
	if ( '' !== $default_key && $chip['key'] === $default_key ) {
		$default_chip = $chip;
		break;
	}
}

$anchor       = $ap_key( $props['section_id'] );
$eyebrow      = trim( (string) $props['eyebrow'] );
$accent       = trim( (string) $props['title_accent'] );
$question     = trim( (string) $props['question'] );
$hint         = trim( (string) $props['hint'] );
$fit_label    = trim( (string) $props['fit_label'] );
$records_head = trim( (string) $props['records_title'] );
$records_body = trim( (string) $props['records_body'] );
$counts_label = trim( (string) $props['counts_label'] );
$never_label  = trim( (string) $props['never_label'] );
$counts       = $ap_lines( $props['counts'] );
$never        = $ap_lines( $props['never'] );
$has_strip    = '' !== $records_head || '' !== $records_body || ! empty( $counts ) || ! empty( $never );
$notice_label = trim( (string) $props['notice_label'] );
$notice       = trim( (string) $props['notice'] );
$cmp_label    = trim( (string) $props['compare_label'] );
$cmp_url      = trim( (string) $props['compare_url'] );
$has_cmp      = '' !== $cmp_label && '' !== $cmp_url;

// Heading illustration: a bare file name is one of this component's assets.
$art = trim( (string) $props['art'] );
if ( preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:svg|webp|png|jpe?g)$/i', $art ) ) {
	$art = ap_ui_asset_url( 'plans-picker', $art );
}
$art_alt  = trim( (string) $props['art_alt'] );
$art_dims = ( (int) $props['art_width'] > 0 && (int) $props['art_height'] > 0 )
	? ' width="' . (int) $props['art_width'] . '" height="' . (int) $props['art_height'] . '"'
	: '';

// head_style "hero": heading, question, chips and answer line sit in a
// full-width banner on this art. They stay inside this section, so the chips
// keep flagging the cards below with no extra wiring.
$hero_head = 'hero' === $props['head_style'];
$hero_bg   = $hero_head ? trim( (string) $props['hero_background'] ) : '';
if ( preg_match( '/^[a-z0-9][a-z0-9._-]*\.(?:svg|webp|png|jpe?g)$/i', $hero_bg ) ) {
	$hero_bg = ap_ui_asset_url( 'plans-picker', $hero_bg );
}
$hero_style = '' !== $hero_bg ? ' style="' . esc_attr( '--ap-plans-picker-hero-bg:url("' . esc_url( $hero_bg ) . '")' ) . '"' : '';

// The section can open the page: its heading is then the page's h1.
$heading = 'h1' === $props['heading_level'] ? 'h1' : 'h2';
$variant = 'clean' === $props['variant'] ? 'clean' : 'classic';
$classes = array( 'ap-plans-picker', 'ap-plans-picker--' . $variant );
if ( 'h1' === $heading ) {
	$classes[] = 'ap-plans-picker--opener';
}
if ( '' !== $art ) {
	$classes[] = 'ap-plans-picker--has-art';
}
if ( $hero_head ) {
	$classes[] = 'ap-plans-picker--hero-head';
}
if ( '' === $lead_key ) {
	$classes[] = 'ap-plans-picker--no-lead';
}

$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-plans-picker-' ) : 'ap-plans-picker-' . substr( md5( uniqid( '', true ) ), 0, 8 );
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?> data-ap-plans-picker>
	<?php if ( '' !== $notice_label || '' !== $notice ) : ?>
		<p class="ap-plans-picker__notice">
			<?php if ( '' !== $notice_label ) : ?>
				<b><?php echo esc_html( $notice_label ); ?></b>
			<?php endif; ?>
			<?php if ( '' !== $notice_label && '' !== $notice ) : ?>
				<span class="ap-plans-picker__notice-dot" aria-hidden="true">&middot;</span>
			<?php endif; ?>
			<?php echo esc_html( $notice ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $hero_head ) : ?>
	<div class="ap-plans-picker__hero"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>>
		<div class="ap-plans-picker__hero-inner">
	<?php else : ?>
	<div class="ap-plans-picker__inner">
	<?php endif; ?>
		<?php if ( '' !== $art ) : ?>
			<img class="ap-plans-picker__art" src="<?php echo esc_url( $art ); ?>" alt="<?php echo esc_attr( $art_alt ); ?>"<?php echo $art_dims; // phpcs:ignore WordPress.Security.EscapeOutput -- integers only. ?><?php echo '' === $art_alt ? ' aria-hidden="true"' : ''; ?> decoding="async" />
		<?php endif; ?>
		<?php if ( '' !== $title || '' !== $eyebrow ) : ?>
		<div class="ap-plans-picker__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-plans-picker__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
			<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h1 or h2. ?> class="ap-plans-picker__title">
				<span><?php echo esc_html( $title ); ?></span>
				<?php if ( '' !== $accent ) : ?>
					<span class="ap-plans-picker__accent"><?php echo esc_html( $accent ); ?></span>
				<?php endif; ?>
			</<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h1 or h2. ?>>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $chips ) ) : ?>
			<div class="ap-plans-picker__picker">
				<?php if ( '' !== $question ) : ?>
					<p class="ap-plans-picker__q" id="<?php echo esc_attr( $uid ); ?>-q"><?php echo esc_html( $question ); ?></p>
				<?php endif; ?>
				<div class="ap-plans-picker__chips" role="group"<?php echo '' !== $question ? ' aria-labelledby="' . esc_attr( $uid ) . '-q"' : ''; ?>>
					<?php foreach ( $chips as $chip ) : ?>
						<button type="button" class="ap-plans-picker__chip" aria-pressed="<?php echo $chip === $default_chip ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $uid . '-' . $chip['key'] ); ?>" data-ap-plan="<?php echo esc_attr( $chip['key'] ); ?>" data-ap-plan-name="<?php echo esc_attr( $chip['plan'] ); ?>" data-ap-note="<?php echo esc_attr( $chip['note'] ); ?>"><?php echo esc_html( $chip['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="ap-plans-picker__out" aria-live="polite" data-ap-plans-out data-ap-hint="<?php echo esc_attr( $hint ); ?>">
					<?php if ( $default_chip ) : ?>
						<b><?php echo esc_html( $default_chip['plan'] ); ?></b> <?php echo esc_html( $default_chip['note'] ); ?>
					<?php else : ?>
						<?php echo esc_html( $hint ); ?>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
	<?php if ( $hero_head ) : ?>
		</div>
	</div>
	<div class="ap-plans-picker__inner">
	<?php endif; ?>

		<div class="ap-plans-picker__grid">
			<?php foreach ( $plans as $plan ) : ?>
				<?php $featured = '' !== $plan['flag']; ?>
				<article class="ap-plans-picker__card<?php echo $featured ? ' is-featured' : ''; ?><?php echo $plan['key'] === $lead_key ? ' is-lead' : ''; ?><?php echo ( $default_chip && $plan['key'] === $default_chip['key'] ) ? ' is-picked' : ''; ?>" id="<?php echo esc_attr( $uid . '-' . $plan['key'] ); ?>" data-ap-plan="<?php echo esc_attr( $plan['key'] ); ?>">
					<?php if ( $featured ) : ?>
						<span class="ap-plans-picker__flag"><?php echo esc_html( $plan['flag'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $fit_label ) : ?>
						<span class="ap-plans-picker__fit"><?php echo esc_html( $fit_label ); ?></span>
					<?php endif; ?>
					<div class="ap-plans-picker__summary">
						<h3 class="ap-plans-picker__name"><?php echo esc_html( $plan['name'] ); ?></h3>
						<?php if ( '' !== $plan['who'] ) : ?>
							<p class="ap-plans-picker__who"><?php echo esc_html( $plan['who'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $plan['price'] ) : ?>
							<p class="ap-plans-picker__amt"><?php echo esc_html( $plan['price'] ); ?><?php if ( '' !== $plan['price_suffix'] ) : ?><small>&nbsp;<?php echo esc_html( $plan['price_suffix'] ); ?></small><?php endif; ?></p>
						<?php endif; ?>
						<p class="ap-plans-picker__per"><?php echo esc_html( $plan['per'] ); ?></p>
						<p class="ap-plans-picker__annual"><?php echo esc_html( $plan['annual'] ); ?></p>
						<?php if ( '' !== $plan['cta_label'] && '' !== $plan['cta_url'] ) : ?>
							<a class="ap-plans-picker__btn<?php echo $featured ? ' ap-plans-picker__btn--primary' : ''; ?>" href="<?php echo esc_url( $plan['cta_url'] ); ?>"><?php echo esc_html( $plan['cta_label'] ); ?></a>
						<?php endif; ?>
					</div>
					<div class="ap-plans-picker__details">
						<?php if ( '' !== $plan['records'] ) : ?>
							<p class="ap-plans-picker__recs"><b><?php echo esc_html( $plan['records'] ); ?></b><span><?php echo esc_html( $plan['records_label'] ); ?></span></p>
						<?php endif; ?>
						<?php if ( '' !== $plan['plus'] ) : ?>
							<p class="ap-plans-picker__plus"><?php echo esc_html( $plan['plus'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $plan['includes'] ) ) : ?>
							<ul class="ap-plans-picker__list">
								<?php foreach ( $plan['includes'] as $line ) : ?>
									<li><span class="ap-plans-picker__tick" aria-hidden="true">&#10003;</span><span><?php echo esc_html( $line ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( $has_cmp ) : ?>
							<a class="ap-plans-picker__more" href="<?php echo esc_url( $cmp_url ); ?>"><?php echo esc_html( $cmp_label ); ?><span aria-hidden="true"> &darr;</span></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $has_strip ) : ?>
			<div class="ap-plans-picker__strip">
				<div>
					<?php if ( '' !== $records_head ) : ?>
						<h3 class="ap-plans-picker__strip-title"><?php echo esc_html( $records_head ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $records_body ) : ?>
						<p class="ap-plans-picker__strip-body"><?php echo esc_html( $records_body ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $counts ) ) : ?>
					<div>
						<?php if ( '' !== $counts_label ) : ?>
							<p class="ap-plans-picker__lbl"><?php echo esc_html( $counts_label ); ?></p>
						<?php endif; ?>
						<ul class="ap-plans-picker__mini">
							<?php foreach ( $counts as $line ) : ?>
								<li><span class="ap-plans-picker__tick" aria-hidden="true">&#10003;</span><span><?php echo esc_html( $line ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $never ) ) : ?>
					<div>
						<?php if ( '' !== $never_label ) : ?>
							<p class="ap-plans-picker__lbl"><?php echo esc_html( $never_label ); ?></p>
						<?php endif; ?>
						<ul class="ap-plans-picker__mini">
							<?php foreach ( $never as $line ) : ?>
								<li><span class="ap-plans-picker__no" aria-hidden="true">&mdash;</span><span><?php echo esc_html( $line ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
