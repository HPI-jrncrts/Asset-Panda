<?php
/**
 * pricing-hero — the pricing page hero.
 *
 * Matches the live `.ap-pricing-hero` section on /new-pricing/: eyebrow,
 * headline and body copy on the left, a white card holding the quote form on
 * the right (stacked below 992px).
 *
 * The form itself belongs to HubSpot, not to the kit. This renders a labelled
 * mount point carrying validated IDs; behaviour.js loads HubSpot's embed script
 * and draws the form there. A component never injects third-party markup handed
 * to it as a prop — only validated IDs and plain strings.
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
$form_label = trim( (string) $props['form_label'] );
$hs_portal  = preg_match( '/^\d{3,12}$/', (string) $props['hubspot_portal'] ) ? (string) $props['hubspot_portal'] : '';
$hs_form    = preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $props['hubspot_form'] ) ? (string) $props['hubspot_form'] : '';
$hs_region  = preg_match( '/^[a-z0-9]{2,6}$/', (string) $props['hubspot_region'] ) ? (string) $props['hubspot_region'] : 'na1';
$has_form   = '' !== $hs_portal && '' !== $hs_form;

// The mount id HubSpot targets; unique across the page.
$form_id = '';
if ( $has_form ) {
	$form_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-pricing-hero-form-' ) : 'ap-pricing-hero-form-' . substr( md5( uniqid( '', true ) ), 0, 8 );
}

// Length cap that never splits a multi-byte character.
$cap = static function ( $text, $length ) {
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
};

// Placeholder text per field name, set by the script once HubSpot has drawn
// the form. Plain strings only; the script writes them as attributes.
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
?>
<section class="ap-pricing-hero">
	<div class="ap-pricing-hero__inner">
		<div class="ap-pricing-hero__content">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-pricing-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>

			<h1 class="ap-pricing-hero__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h1>

			<?php if ( '' !== $body ) : ?>
				<p class="ap-pricing-hero__body"><?php echo ap_ui_lines( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $has_form ) : ?>
			<div class="ap-pricing-hero__form-card" data-ap-form-skin>
				<div
					id="<?php echo esc_attr( $form_id ); ?>"
					class="ap-pricing-hero__hubspot-form"
					<?php if ( '' !== $form_label ) : ?>aria-label="<?php echo esc_attr( $form_label ); ?>"<?php endif; ?>
					data-ap-pricing-form
					data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>"
					data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>"
					data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"
					<?php if ( $hs_text ) : ?>data-ap-hs-placeholders="<?php echo esc_attr( wp_json_encode( $hs_text ) ); ?>"<?php endif; ?>
					<?php if ( $hs_enrich ) : ?>data-ap-hs-enrich="<?php echo esc_attr( implode( ' ', $hs_enrich ) ); ?>"<?php endif; ?>
					<?php if ( '' !== $hs_submit ) : ?>data-ap-hs-submit="<?php echo esc_attr( $hs_submit ); ?>"<?php endif; ?>
					<?php if ( '' !== $hs_redirect ) : ?>data-ap-hs-redirect="<?php echo esc_url( $hs_redirect ); ?>"<?php endif; ?>
				></div>
			</div>
		<?php endif; ?>
	</div>
</section>
