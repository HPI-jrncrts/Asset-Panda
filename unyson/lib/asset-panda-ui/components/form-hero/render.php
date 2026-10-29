<?php
/**
 * form-hero — a form page's hero (or an in-page form band).
 *
 * Copy on the left (eyebrow, heading, paragraph, check list, badges), a white
 * card on the right holding the HubSpot form; stacked below 992px. The form
 * belongs to HubSpot: this renders a mount point with the portal, form and
 * region as data attributes, and behaviour.js loads HubSpot's script and draws
 * the form there (classic v2 embed, or the developer embed for forms built in
 * HubSpot's new editor). Where the visitor goes after submitting is the form's
 * own redirect, set in HubSpot.
 *
 * Live's /get-a-quote-pro/ and /barcode-labels/ show a "Form not loading?"
 * box when no form has appeared after 6 seconds (blocked script, network);
 * it is rendered hidden here and revealed by the same rule.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$variant   = in_array( $props['variant'], array( 'light', 'dark', 'section' ), true ) ? $props['variant'] : 'light';
$title_tag = 'h2' === $props['title_tag'] ? 'h2' : 'h1';
$card_tag  = 'h1' === $title_tag ? 'h2' : 'h3';
$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$eyebrow   = trim( (string) $props['eyebrow'] );
$body      = trim( (string) $props['body'] );
$bg        = trim( (string) $props['background'] );

$hs_portal = preg_match( '/^\d{3,12}$/', (string) $props['hubspot_portal'] ) ? (string) $props['hubspot_portal'] : '';
$hs_form   = preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $props['hubspot_form'] ) ? strtolower( (string) $props['hubspot_form'] ) : '';
$hs_region = preg_match( '/^[a-z0-9]{2,6}$/', (string) $props['hubspot_region'] ) ? (string) $props['hubspot_region'] : 'na1';
$hs_embed  = 'developer' === $props['form_embed'] ? 'developer' : 'v2';
$hs_submit = substr( trim( (string) $props['form_submit'] ), 0, 60 );
$has_form  = '' !== $hs_portal && '' !== $hs_form;

static $ap_form_hero_instance = 0;
++$ap_form_hero_instance;
$mount_id = 'ap-form-hero-form-' . $ap_form_hero_instance;

$fb_email = sanitize_email( trim( (string) $props['fallback_email'] ) );
$fb_phone = trim( (string) $props['fallback_phone'] );
$fb_tel   = preg_replace( '/[^0-9+]/', '', $fb_phone );

$form_title = trim( (string) $props['form_title'] );
$form_intro = trim( (string) $props['form_intro'] );
$form_note  = trim( (string) $props['form_note'] );

/*
 * Escaped text with the author's line breaks and **bold** runs. The text is
 * escaped first, so the fixed <strong> and <br> are the only markup added.
 */
$ap_rich = static function ( $text ) {
	$html = ap_ui_lines( $text );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return is_string( $bold ) ? $bold : $html;
};

$bullets = array();
foreach ( is_array( $props['bullets'] ) ? $props['bullets'] : array() as $bullet ) {
	if ( is_string( $bullet ) && '' !== trim( $bullet ) ) {
		$bullets[] = trim( $bullet );
	}
}

$badges = array();
foreach ( is_array( $props['badges'] ) ? $props['badges'] : array() as $badge ) {
	if ( is_array( $badge ) && '' !== trim( (string) ( $badge['image'] ?? '' ) ) ) {
		$badges[] = $badge;
	}
}

$classes = 'ap-form-hero ap-form-hero--' . $variant;
$style   = '' !== $bg ? '--ap-form-hero-bg:url("' . esc_url_raw( $bg ) . '")' : '';
?>
<section class="<?php echo esc_attr( $classes ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?><?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
	<div class="ap-form-hero__inner">
		<div class="ap-form-hero__copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-form-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<<?php echo tag_escape( $title_tag ); ?> class="ap-form-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo tag_escape( $title_tag ); ?>>
			<?php if ( '' !== $body ) : ?>
				<p class="ap-form-hero__body"><?php echo $ap_rich( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
			<?php endif; ?>
			<?php if ( $bullets ) : ?>
				<ul class="ap-form-hero__list">
					<?php foreach ( $bullets as $bullet ) : ?>
						<li><?php echo $ap_rich( $bullet ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $badges ) : ?>
				<div class="ap-form-hero__badges">
					<?php foreach ( $badges as $badge ) : ?>
						<?php
						$w = isset( $badge['w'] ) ? (int) $badge['w'] : 0;
						$h = isset( $badge['h'] ) ? (int) $badge['h'] : 0;
						?>
						<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( (string) ( $badge['alt'] ?? '' ) ); ?>"<?php echo $w > 0 && $h > 0 ? ' width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '"' : ''; ?> decoding="async" />
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $has_form || '' !== $form_title ) : ?>
			<div class="ap-form-hero__card">
				<?php if ( '' !== $form_title ) : ?>
					<<?php echo tag_escape( $card_tag ); ?> class="ap-form-hero__card-title"><?php echo esc_html( $form_title ); ?></<?php echo tag_escape( $card_tag ); ?>>
				<?php endif; ?>
				<?php if ( '' !== $form_intro ) : ?>
					<p class="ap-form-hero__card-intro"><?php echo esc_html( $form_intro ); ?></p>
				<?php endif; ?>
				<?php if ( $has_form ) : ?>
					<div
						id="<?php echo esc_attr( $mount_id ); ?>"
						class="ap-form-hero__form ap-form-hero__form--<?php echo esc_attr( $hs_embed ); ?>"
						data-ap-form-hero
						data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>"
						data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>"
						data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"
						data-ap-hs-embed="<?php echo esc_attr( $hs_embed ); ?>"
						<?php if ( '' !== $hs_submit ) : ?>data-ap-hs-submit="<?php echo esc_attr( $hs_submit ); ?>"<?php endif; ?>
					>
						<?php if ( 'developer' === $hs_embed ) : ?>
							<div class="hs-form-html" data-region="<?php echo esc_attr( $hs_region ); ?>" data-form-id="<?php echo esc_attr( $hs_form ); ?>" data-portal-id="<?php echo esc_attr( $hs_portal ); ?>"></div>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $fb_email || '' !== $fb_phone ) : ?>
						<div class="ap-form-hero__fallback" data-ap-form-fallback hidden>
							<strong>Form not loading?</strong>
							<?php if ( '' !== $fb_email ) : ?>
								Email <a href="<?php echo esc_url( 'mailto:' . $fb_email ); ?>"><?php echo esc_html( $fb_email ); ?></a><?php endif; ?><?php if ( '' !== $fb_phone && '' !== $fb_tel ) : ?><span class="ap-form-hero__fb-phone"> <?php echo '' !== $fb_email ? 'or call' : 'Call'; ?> <a href="<?php echo esc_url( 'tel:' . $fb_tel ); ?>"><?php echo esc_html( $fb_phone ); ?></a></span><?php endif; ?>
							and we will get right back to you.
							<a href="#" data-ap-form-reload>Reload the form</a>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( '' !== $form_note ) : ?>
					<p class="ap-form-hero__note"><?php echo esc_html( $form_note ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
