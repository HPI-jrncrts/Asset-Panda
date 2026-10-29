<?php
/**
 * demo-booking — Get a Demo booking hero (2026 Cal.com flow).
 *
 * Markup of the live #ap-demo-hero (live-pages/get-a-demo @f2006d8: blocks/hero-v1.html,
 * blocks/hero.html, blocks/widget.html, blocks/widget-details.html), class for class. The
 * first form asks for the work email and the names only; with details on, the company and
 * phone step (widget-details.html) sits inside the calendar card above the days, as live's
 * build includes it when config.details is "on" and leaves it out when "off". The widget
 * root carries its configuration as data attributes; behaviour.js reads them
 * (root.dataset), so their names are part of the contract with the script.
 * Only the first testimonial loads its logo up front: the others wait in
 * data-src until behaviour.js shows them.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_split   = 'split' === $props['layout'];
$ap_view    = 'month' === $props['view'] ? 'month' : 'week';
$ap_details = 'off' !== $props['details'];
$ap_source  = trim( (string) $props['source'] );

$ap_list = static function ( $items, $key ) {
	return array_values(
		array_filter(
			is_array( $items ) ? $items : array(),
			static function ( $item ) use ( $key ) {
				return is_array( $item ) && '' !== trim( (string) ( $item[ $key ] ?? '' ) );
			}
		)
	);
};

// A positive pixel size as an integer, or 0.
$ap_px = static function ( $value ) {
	return is_numeric( $value ) && (float) $value > 0 ? (int) round( (float) $value ) : 0;
};

$ap_compliance = $ap_list( $props['compliance'], 'image' );
$ap_awards     = $ap_list( $props['awards'], 'image' );
$ap_stats      = $ap_list( $props['stats'], 'value' );
$ap_stories    = $ap_split ? $ap_list( $props['stories'], 'quote' ) : array();

/*
 * Escaped story text. WordPress runs wptexturize() over block output, which would turn the
 * quotes' "..." into an ellipsis glyph; live shows plain dots, so a dot pair is split with an
 * entity texturize does not read.
 */
$ap_story_text = static function ( $text ) {
	return str_replace( '..', '.&#46;', esc_html( trim( (string) $text ) ) );
};

$ap_quote_icon = trim( (string) $props['quote_icon'] );
if ( '' === $ap_quote_icon ) {
	$ap_quote_icon = ap_ui_asset_url( 'demo-booking', 'quote-up.png' );
}

// esc_url_raw() strips quotes, spaces and backslashes, so the URL cannot leave url("…").
$ap_bg    = trim( (string) $props['background'] );
$ap_style = '' !== $ap_bg ? 'background-image:url("' . esc_url_raw( $ap_bg ) . '")' : '';

$ap_lead = trim( (string) $props['lead'] );

// Points under the lead (the pricing page): plain strings, a bold lead-in at most.
$ap_point_tags = array(
	'strong' => array(),
	'em'     => array(),
	'b'      => array(),
);
$ap_points     = array_values(
	array_filter(
		array_map(
			static function ( $point ) {
				return is_string( $point ) ? trim( $point ) : '';
			},
			is_array( $props['points'] ) ? $props['points'] : array()
		),
		'strlen'
	)
);

// A same-site REST path (/wp-json/...) or a full URL; esc_url_raw() drops anything that is not a URL.
$ap_guard = esc_url_raw( trim( (string) $props['guard_url'] ) );

$ap_thanks  = trim( (string) $props['thanks_url'] );
$ap_preview = trim( (string) $props['thanks_preview_url'] );
if ( '' === $ap_preview ) {
	$ap_preview = $ap_thanks;
}
$ap_max_days = $ap_px( $props['max_days'] );
if ( 0 === $ap_max_days ) {
	$ap_max_days = 60;
}

$ap_times = trim( (string) $props['times_heading'] );
$ap_hint  = '' !== $ap_times ? rtrim( $ap_times, '.' ) . '.' : '';
$ap_email = trim( (string) $props['contact_email'] );
$ap_phone = trim( (string) $props['contact_phone'] );
$ap_tel   = preg_replace( '/[^0-9+\-]/', '', str_replace( ' ', '-', $ap_phone ) ); // (888) 928-6112 -> 888-928-6112

$ap_classes = 'ap-demo-booking ap-d26-sec ap-d26-hero' . ( $ap_split ? ' ap-d26-v1' : '' );

/*
 * Award badges: the set, then an aria-hidden copy the marquee scrolls into
 * (CSS shows the copy only while the badges move).
 */
$ap_award_imgs = static function ( $awards, $decorative ) use ( $ap_px ) {
	$out = '';
	foreach ( $awards as $award ) {
		$w    = $ap_px( $award['w'] ?? null );
		$h    = $ap_px( $award['h'] ?? null );
		$out .= '<img src="' . esc_url( $award['image'] ) . '" alt="' . ( $decorative ? '' : esc_attr( $award['alt'] ?? '' ) ) . '"'
			. ( $w ? ' width="' . $w . '"' : '' ) . ( $h ? ' height="' . $h . '" style="height:' . $h . 'px"' : '' )
			. ' loading="lazy" decoding="async" />';
	}
	return $out;
};
$ap_awards_html = '';
if ( $ap_awards ) {
	$ap_awards_html = '<div class="ap-d26-awards"><div class="ap-d26-awards-track">' . $ap_award_imgs( $ap_awards, false )
		. '<span class="ap-d26-awards-dup" aria-hidden="true">' . $ap_award_imgs( $ap_awards, true ) . '</span></div></div>';
}
?>
<section class="<?php echo esc_attr( $ap_classes ); ?>"<?php echo $ap_split ? ' data-ap-fold' : ''; ?><?php echo '' !== $ap_style ? ' style="' . esc_attr( $ap_style ) . '"' : ''; ?>>
	<div class="ap-d26-fade" aria-hidden="true"></div>
	<?php if ( $ap_split ) : ?>
	<div class="ap-d26-v1-row">
		<div class="ap-d26-v1-main">
	<?php else : ?>
	<div class="ap-d26-content">
	<?php endif; ?>
		<?php // No title and no lead: the widget is embedded in another hero (plans-hero split), which owns the page's h1. ?>
		<?php if ( '' !== trim( (string) $props['title'] ) || '' !== $ap_lead || $ap_points ) : ?>
		<div class="ap-d26-head">
			<h1><?php echo esc_html( $props['title'] ); ?></h1>
			<?php if ( '' !== $ap_lead ) : ?>
				<p class="ap-d26-lead"><?php echo esc_html( $ap_lead ); ?></p>
			<?php endif; ?>
			<?php if ( $ap_points ) : ?>
				<ul class="ap-d26-points">
					<?php foreach ( $ap_points as $ap_point ) : ?>
						<li><?php echo wp_kses( $ap_point, $ap_point_tags ); // phpcs:ignore WordPress.Security.EscapeOutput -- kses with a three-tag allowlist. ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<div class="ap-dw ap-dw--v1" data-ap-demo-widget data-mode="picker" data-view="<?php echo esc_attr( $ap_view ); ?>" data-team="<?php echo esc_attr( trim( (string) $props['cal_team'] ) ); ?>" data-user="<?php echo esc_attr( trim( (string) $props['cal_user'] ) ); ?>" data-event="<?php echo esc_attr( trim( (string) $props['cal_event'] ) ); ?>" data-source="<?php echo esc_attr( $ap_source ); ?>" data-max-days="<?php echo esc_attr( (string) $ap_max_days ); ?>" data-track-path="<?php echo esc_attr( trim( (string) $props['track_path'] ) ); ?>" data-hs-portal="<?php echo esc_attr( trim( (string) $props['hs_portal'] ) ); ?>" data-hs-form="<?php echo esc_attr( trim( (string) $props['hs_form'] ) ); ?>" data-guard="<?php echo esc_attr( $ap_guard ); ?>" data-work-email="<?php echo esc_attr( (string) $props['work_email'] ); ?>" data-thanks-url="<?php echo esc_url( $ap_thanks ); ?>" data-thanks-preview-url="<?php echo esc_url( $ap_preview ); ?>">
			<p class="ap-dw-ribbon" hidden></p>
			<form class="ap-dw-card ap-dw-form" novalidate onsubmit="return false">
				<div class="ap-dw-fields">
					<label class="ap-dw-field"><span class="ap-dw-label">Work email*</span><input type="email" name="email" autocomplete="email" inputmode="email" required></label>
					<p class="ap-dw-err" data-err="email" role="alert" hidden></p>
					<div class="ap-dw-names" hidden>
						<div class="ap-dw-pair">
							<label class="ap-dw-field"><span class="ap-dw-label">First name*</span><input type="text" name="firstname" autocomplete="given-name" required></label>
							<label class="ap-dw-field"><span class="ap-dw-label">Last name*</span><input type="text" name="lastname" autocomplete="family-name" required></label>
						</div>
					</div>
					<p class="ap-dw-err" data-err="name" role="alert" hidden></p>
				</div>
				<div class="ap-dw-cta"><button type="submit" class="ap-dw-btn"><?php echo esc_html( $props['button_label'] ); ?></button><?php if ( $ap_compliance ) : ?><div class="ap-dw-trust" role="group" aria-label="<?php echo esc_attr( $props['compliance_label'] ); ?>">
						<p class="ap-dw-trust-label"><?php echo esc_html( $props['compliance_label'] ); ?></p>
						<div class="ap-dw-trust-logos">
							<?php foreach ( $ap_compliance as $ap_badge ) : ?>
								<?php
								$ap_bw = $ap_px( $ap_badge['w'] ?? null );
								$ap_bh = $ap_px( $ap_badge['h'] ?? null );
								$ap_bd = $ap_px( $ap_badge['display_h'] ?? null );
								if ( 0 === $ap_bd ) {
									$ap_bd = $ap_bh;
								}
								?>
								<img src="<?php echo esc_url( $ap_badge['image'] ); ?>" alt="<?php echo esc_attr( $ap_badge['alt'] ?? '' ); ?>"<?php echo $ap_bw ? ' width="' . $ap_bw . '"' : ''; ?><?php echo $ap_bh ? ' height="' . $ap_bh . '"' : ''; ?><?php echo $ap_bd ? ' style="height:' . $ap_bd . 'px"' : ''; ?> decoding="async">
							<?php endforeach; ?>
						</div>
					</div><?php endif; ?></div>
			</form>
			<div class="ap-dw-card ap-dw-cal" hidden>
				<p class="ap-dw-who"><span data-who></span> · <button type="button" class="ap-dw-link" data-action="change">Change</button></p>
				<p class="ap-dw-alert" role="alert"></p>
				<?php if ( $ap_details ) : ?>
				<?php // Step 2 (live blocks/widget-details.html): company and phone, checked when a time is clicked. ?>
				<div class="ap-dw-details">
					<div class="ap-dw-pair">
						<label class="ap-dw-field"><span class="ap-dw-label">Company name*</span><input type="text" name="company" autocomplete="organization" required></label>
						<label class="ap-dw-field"><span class="ap-dw-label">Phone*</span><input type="tel" name="phone" autocomplete="tel" inputmode="tel" required></label>
					</div>
					<p class="ap-dw-err" data-err="details" role="alert" hidden></p>
				</div>
				<?php endif; ?>
				<div class="ap-dw-week">
					<div class="ap-dw-week-head">
						<h2 class="ap-dw-h" tabindex="-1"><?php echo esc_html( $props['days_heading'] ); ?></h2>
						<span class="ap-dw-navs">
							<span class="ap-dw-month" data-month hidden></span>
							<button type="button" class="ap-dw-nav" data-action="prev" aria-label="Previous week"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
							<button type="button" class="ap-dw-nav" data-action="next" aria-label="Next week"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
						</span>
					</div>
					<div class="ap-dw-days" role="group" aria-label="Days this week"></div>
				</div>
				<div class="ap-dw-times">
					<h3 class="ap-dw-h2"><?php echo esc_html( $ap_times ); ?></h3>
					<p class="ap-dw-hint" hidden><?php echo esc_html( $ap_hint ); ?></p>
					<p class="ap-dw-meta"><span><?php echo esc_html( $props['duration_label'] ); ?></span><span aria-hidden="true">•</span><label class="ap-dw-tz"><span class="ap-dw-sr">Time zone</span><select data-tz></select></label><span data-now></span></p>
					<div class="ap-dw-slots" role="group" aria-label="Open times"></div>
					<p class="ap-dw-empty" hidden>No open times this week. Try the next week.</p>
				</div>
			</div>
			<div class="ap-dw-card ap-dw-fallback" hidden><p class="ap-dw-note" data-fallback-note></p><div class="ap-dw-fallback-cal"></div></div>
			<div class="ap-dw-card ap-dw-none" hidden><p>No open times right now. Email <a href="<?php echo esc_url( 'mailto:' . $ap_email ); ?>"><?php echo esc_html( $ap_email ); ?></a> or call <a href="<?php echo esc_url( 'tel:' . $ap_tel ); ?>"><?php echo esc_html( $ap_phone ); ?></a> and we will get right back to you.</p></div>
			<div class="ap-dw-card ap-dw-done" hidden><p>You’re booked. Check your email for the confirmation and calendar invite.</p></div>
			<div class="ap-dw-card ap-dw-reach" hidden><p class="ap-dw-reach-h">Thanks, <span data-reach-name></span>. A specialist will be in touch shortly.</p><p>Want to pick a time now? Book with your work email.</p><button type="button" class="ap-dw-link" data-action="work-email">Use a work email</button></div>
			<p class="ap-dw-status ap-dw-sr" role="status" aria-live="polite"></p>
		</div>
	<?php if ( $ap_split ) : ?>
		</div>
		<div class="ap-d26-v1-side">
			<?php if ( $ap_stories ) : ?>
				<?php $ap_count = count( $ap_stories ); ?>
				<div class="ap-d26-qcard" data-ap-cases aria-roledescription="carousel" aria-label="What customers say">
					<div class="ap-d26-qslides" aria-live="polite">
						<?php foreach ( $ap_stories as $ap_i => $ap_story ) : ?>
							<?php
							$ap_eager = 0 === $ap_i;
							$ap_lw    = $ap_px( $ap_story['logo_w'] ?? null );
							$ap_lh    = $ap_px( $ap_story['logo_h'] ?? null );
							$ap_logo  = trim( (string) ( $ap_story['logo'] ?? '' ) );
							?>
							<div class="ap-d26-qslide<?php echo $ap_eager ? ' is-active' : ''; ?>" data-case aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $ap_i + 1 ) . ' of ' . $ap_count ); ?>"><img class="ap-d26-qicon" src="<?php echo esc_url( $ap_quote_icon ); ?>" alt="" width="32" height="32" decoding="async"><p class="ap-d26-qtitle"><?php echo $ap_story_text( $ap_story['title'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p><p class="ap-d26-qtext"><?php echo $ap_story_text( $ap_story['quote'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p><div class="ap-d26-qauthor"><div class="ap-d26-qwho"><p class="ap-d26-qname"><?php echo esc_html( $ap_story['author'] ?? '' ); ?></p><p class="ap-d26-qrole"><?php echo esc_html( $ap_story['role'] ?? '' ); ?></p></div><span class="ap-d26-qlogo"><?php if ( '' !== $ap_logo ) : ?><img <?php echo $ap_eager ? 'src' : 'data-src'; ?>="<?php echo esc_url( $ap_logo ); ?>" alt="<?php echo esc_attr( $ap_story['logo_alt'] ?? '' ); ?>"<?php echo $ap_lw ? ' width="' . $ap_lw . '"' : ''; ?><?php echo $ap_lh ? ' height="' . $ap_lh . '"' : ''; ?><?php echo $ap_lw ? ' style="width:' . $ap_lw . 'px"' : ''; ?> decoding="async"><?php endif; ?></span></div></div>
						<?php endforeach; ?>
					</div>
					<div class="ap-d26-dots" role="group" aria-label="Choose a testimonial"><?php for ( $ap_d = 0; $ap_d < $ap_count; $ap_d++ ) : ?><button type="button" class="ap-d26-dot<?php echo 0 === $ap_d ? ' is-active' : ''; ?>" data-case-dot aria-label="<?php echo esc_attr( 'Testimonial ' . ( $ap_d + 1 ) . ' of ' . $ap_count ); ?>"<?php echo 0 === $ap_d ? ' aria-current="true"' : ''; ?>></button><?php endfor; ?></div>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $ap_awards_html ) : ?>
				<div class="ap-d26-v1-awards"><?php echo $ap_awards_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built above from escaped parts. ?></div>
			<?php endif; ?>
		</div>
	</div>
	<?php else : ?>
		<?php if ( $ap_stats ) : ?>
			<ul class="ap-d26-stats" data-ap-stats>
				<?php foreach ( $ap_stats as $ap_stat ) : ?>
					<li><span class="ap-d26-stat-num"><span class="ap-d26-stat-val" data-ap-count><?php echo esc_html( $ap_stat['value'] ); ?></span><span class="ap-d26-stat-ghost" aria-hidden="true"><?php echo esc_html( $ap_stat['value'] ); ?></span></span><span class="ap-d26-stat-label"><?php echo esc_html( $ap_stat['label'] ?? '' ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php echo $ap_awards_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built above from escaped parts. ?>
	<?php endif; ?>
</section>
