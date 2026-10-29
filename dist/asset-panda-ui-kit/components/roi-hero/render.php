<?php
/**
 * roi-hero — the ROI calculator page hero.
 *
 * Figma "Pricing - New Jun 11 2026 - Opt 1" (file VW6cE9oR0LeIeowjwJvEqY,
 * node 7169:88796), restyled per QA 2026-10-29: eyebrow, headline and lede on
 * the left of the white page, a card holding the HubSpot quote form on the
 * right (stacked on tablets and phones), drawn like the Get a Demo booking
 * form (demo-booking's .ap-dw-* card, fields, button and compliance row) with
 * no testimonial column.
 *
 * The form belongs to HubSpot, not to the kit. This renders a labelled mount
 * carrying validated IDs and plain strings only; behaviour.js loads HubSpot's
 * embed and draws the form into it. A component never injects third-party
 * markup handed to it as a prop. The optional compliance row (badges under
 * the button) is the kit's own markup, after the mount.
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

$eyebrow    = trim( (string) $props['eyebrow'] );
$body       = trim( (string) $props['body'] );
$h1_eyebrow = 'eyebrow' === (string) $props['h1'] && '' !== $eyebrow;
$form_label = trim( (string) $props['form_label'] );
$hs_portal  = preg_match( '/^\d{3,12}$/', (string) $props['hubspot_portal'] ) ? (string) $props['hubspot_portal'] : '';
$hs_form    = preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $props['hubspot_form'] ) ? (string) $props['hubspot_form'] : '';
$hs_region  = preg_match( '/^[a-z0-9]{2,6}$/', (string) $props['hubspot_region'] ) ? (string) $props['hubspot_region'] : 'na1';
$has_form   = '' !== $hs_portal && '' !== $hs_form;

// Length cap that never splits a multi-byte character.
$cap = static function ( $text, $length ) {
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
};

// Placeholder text per field name, written by the script once HubSpot has
// drawn the form. Plain strings only.
$hs_text = array();
foreach ( is_array( $props['form_placeholders'] ) ? $props['form_placeholders'] : array() as $field => $text ) {
	if ( preg_match( '/^[a-z0-9_]{1,64}$/', (string) $field ) && is_scalar( $text ) && '' !== trim( (string) $text ) ) {
		$hs_text[ (string) $field ] = $cap( trim( (string) $text ), 120 );
	}
}

// Fields held back until enrichment says the visitor has to fill them.
$hs_enrich = array();
foreach ( is_array( $props['form_enrich_fields'] ) ? $props['form_enrich_fields'] : array() as $field ) {
	if ( is_string( $field ) && preg_match( '/^[a-z0-9_]{1,64}$/', $field ) ) {
		$hs_enrich[] = $field;
	}
}
$hs_enrich = array_values( array_unique( $hs_enrich ) );

$hs_submit   = $cap( trim( (string) $props['form_submit'] ), 60 );
$hs_redirect = trim( (string) $props['form_redirect'] );

// Contact line for a visitor whose browser blocks HubSpot.
$fb_email = trim( (string) $props['fallback_email'] );
$fb_email = function_exists( 'is_email' ) ? ( is_email( $fb_email ) ? $fb_email : '' ) : ( filter_var( $fb_email, FILTER_VALIDATE_EMAIL ) ? $fb_email : '' );
$fb_phone = $cap( trim( (string) $props['fallback_phone'] ), 32 );
$fb_tel   = preg_replace( '/[^0-9+]/', '', $fb_phone );
if ( strlen( $fb_tel ) < 7 ) {
	$fb_phone = '';
	$fb_tel   = '';
}

// Compliance badges by the button (demo-booking's compliance row).
$px = static function ( $value ) {
	return is_numeric( $value ) && (float) $value > 0 ? (int) round( (float) $value ) : 0;
};
$trust_label = $cap( trim( (string) $props['compliance_label'] ), 80 );
$badges      = array();
foreach ( is_array( $props['compliance'] ) ? $props['compliance'] : array() as $badge ) {
	if ( ! is_array( $badge ) || ! is_scalar( $badge['image'] ?? null ) || '' === trim( (string) $badge['image'] ) ) {
		continue;
	}
	$shown    = $px( $badge['display_h'] ?? null );
	$badges[] = array(
		'image' => trim( (string) $badge['image'] ),
		'alt'   => is_scalar( $badge['alt'] ?? null ) ? $cap( trim( (string) $badge['alt'] ), 80 ) : '',
		'w'     => $px( $badge['w'] ?? null ),
		'h'     => $px( $badge['h'] ?? null ),
		'shown' => $shown ? $shown : $px( $badge['h'] ?? null ),
	);
}

// The row sits beside the button, as demo-booking's .ap-dw-cta does, when it
// is compact: every badge has a known size, the row is at most 240px wide (the
// logos at their shown height, or the label, whichever is wider) and 56px
// tall. Otherwise, and on narrow cards (style.css), it sits under the button.
$trust_w   = 0;
$trust_h   = 0;
$trust_fit = (bool) $badges;
foreach ( $badges as $badge ) {
	if ( ! $badge['w'] || ! $badge['h'] || ! $badge['shown'] || $badge['shown'] > 56 ) {
		$trust_fit = false;
		break;
	}
	$trust_w += (int) ceil( $badge['w'] * $badge['shown'] / $badge['h'] );
	$trust_h  = max( $trust_h, $badge['shown'] );
}
// Row height: the 18px label line, its 4px gap, the tallest badge.
$trust_h += '' !== $trust_label ? 22 : 0;
if ( $trust_fit ) {
	$trust_w += 24 * ( count( $badges ) - 1 );
	$trust_w  = max( $trust_w, (int) ceil( ( function_exists( 'mb_strlen' ) ? mb_strlen( $trust_label ) : strlen( $trust_label ) ) * 6.6 ) );
	$trust_fit = $trust_w <= 240;
}

$form_id = '';
if ( $has_form ) {
	$form_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-roi-hero-form-' ) : 'ap-roi-hero-form-' . substr( md5( uniqid( '', true ) ), 0, 8 );
}

$fallback = '';
if ( '' !== $fb_email || '' !== $fb_phone ) {
	$fallback = 'Form not loading? ';
	if ( '' !== $fb_email ) {
		$fallback .= 'Email <a href="' . esc_url( 'mailto:' . $fb_email ) . '">' . esc_html( $fb_email ) . '</a>';
	}
	if ( '' !== $fb_phone ) {
		$fallback .= '<span class="ap-roi-hero__fallback-phone">' . ( '' !== $fb_email ? ' or call ' : 'Call ' ) . '<a href="' . esc_url( 'tel:' . $fb_tel, array( 'tel' ) ) . '">' . esc_html( $fb_phone ) . '</a></span>';
	}
	$fallback .= '.';
}
?>
<section class="ap-roi-hero<?php echo $has_form ? '' : ' ap-roi-hero--copy-only'; ?>">
	<div class="ap-roi-hero__inner">
		<div class="ap-roi-hero__content">
			<?php if ( '' !== $eyebrow ) : ?>
				<<?php echo $h1_eyebrow ? 'h1' : 'p'; ?> class="ap-roi-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></<?php echo $h1_eyebrow ? 'h1' : 'p'; ?>>
			<?php endif; ?>

			<<?php echo $h1_eyebrow ? 'h2' : 'h1'; ?> class="ap-roi-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></<?php echo $h1_eyebrow ? 'h2' : 'h1'; ?>>

			<?php if ( '' !== $body ) : ?>
				<p class="ap-roi-hero__body"><?php echo ap_ui_lines( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $has_form ) : ?>
			<div class="ap-roi-hero__card<?php echo $trust_fit ? ' ap-roi-hero__card--cta' : ''; ?>"<?php echo $trust_fit ? ' style="--ap-roi-trust-w:' . (int) $trust_w . 'px;--ap-roi-trust-h:' . (int) $trust_h . 'px"' : ''; ?> data-ap-form-skin>
				<div id="<?php echo esc_attr( $form_id ); ?>" class="ap-roi-hero__form" data-ap-roi-form data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>" data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>" data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"<?php echo '' !== $form_label ? ' data-ap-hs-label="' . esc_attr( $form_label ) . '"' : ''; ?><?php echo $hs_text ? ' data-ap-hs-placeholders="' . esc_attr( wp_json_encode( $hs_text ) ) . '"' : ''; ?><?php echo $hs_enrich ? ' data-ap-hs-enrich="' . esc_attr( implode( ' ', $hs_enrich ) ) . '"' : ''; ?><?php echo '' !== $hs_submit ? ' data-ap-hs-submit="' . esc_attr( $hs_submit ) . '"' : ''; ?><?php echo '' !== $hs_redirect ? ' data-ap-hs-redirect="' . esc_url( $hs_redirect ) . '"' : ''; ?>></div>
				<?php if ( $badges ) : ?>
					<div class="ap-roi-hero__trust"<?php echo '' !== $trust_label ? ' role="group" aria-label="' . esc_attr( $trust_label ) . '"' : ''; ?>>
						<?php if ( '' !== $trust_label ) : ?>
							<p class="ap-roi-hero__trust-label"><?php echo esc_html( $trust_label ); ?></p>
						<?php endif; ?>
						<div class="ap-roi-hero__trust-logos">
							<?php foreach ( $badges as $badge ) : ?>
								<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ); ?>"<?php echo $badge['w'] ? ' width="' . (int) $badge['w'] . '"' : ''; ?><?php echo $badge['h'] ? ' height="' . (int) $badge['h'] . '"' : ''; ?><?php echo $badge['shown'] ? ' style="height:' . (int) $badge['shown'] . 'px"' : ''; ?> loading="lazy" decoding="async">
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $fallback ) : ?>
					<p class="ap-roi-hero__fallback" hidden><?php echo $fallback; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url/esc_html parts above. ?></p>
					<noscript><p class="ap-roi-hero__fallback"><?php echo $fallback; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url/esc_html parts above. ?></p></noscript>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
