<?php
/**
 * demo-cta — the closing "book a demo" card.
 *
 * Matches the live `.ap-fire-footer-cta` card: copy and a pull quote on the
 * left, the demo form on the right.
 *
 * The form itself belongs to HubSpot, not to the kit. This renders a labelled
 * mount point; when HubSpot IDs are supplied, the component's behaviour.js
 * loads HubSpot's embed script and creates the form there. A component never
 * injects third-party markup handed to it as a prop — only validated IDs.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title        = trim( (string) $props['title'] );
$eyebrow      = trim( (string) $props['eyebrow'] );
$body         = trim( (string) $props['body'] );
$quote        = trim( (string) $props['quote'] );
$quote_sub    = trim( (string) $props['quote_sub'] );
$form_heading = trim( (string) $props['form_heading'] );
$form_id      = sanitize_html_class( trim( (string) $props['form_id'] ) );
$hs_portal    = preg_match( '/^\d{3,12}$/', (string) $props['hubspot_portal'] ) ? (string) $props['hubspot_portal'] : '';
$hs_form      = preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $props['hubspot_form'] ) ? (string) $props['hubspot_form'] : '';
$hs_region    = preg_match( '/^[a-z0-9]{2,6}$/', (string) $props['hubspot_region'] ) ? (string) $props['hubspot_region'] : 'na1';
if ( '' !== $hs_portal && '' !== $hs_form && '' === $form_id ) {
	static $ap_demo_cta_instance = 0;
	++$ap_demo_cta_instance;
	$form_id = 'ap-demo-cta-form-' . $ap_demo_cta_instance;
}
$cta_label    = trim( (string) $props['cta_label'] );
$cta_url      = trim( (string) $props['cta_url'] );
$has_button   = '' !== $cta_label && '' !== $cta_url;
$image        = trim( (string) $props['image'] );
$has_panel    = ( '' !== $form_id || '' !== $form_heading ) && '' === $image;
?>
<section class="ap-demo-cta">
	<div class="ap-demo-cta__inner">
		<div class="ap-demo-cta__card<?php echo $has_panel ? '' : ( '' !== $image ? ' ap-demo-cta__card--media' : ' ap-demo-cta__card--solo' ); ?>"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
			<div class="ap-demo-cta__copy">
				<div>
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="ap-demo-cta__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<h2 class="ap-demo-cta__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				</div>

				<?php if ( '' !== $body ) : ?>
					<p class="ap-demo-cta__body"><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>

				<?php if ( '' !== $quote || '' !== $quote_sub ) : ?>
					<div class="ap-demo-cta__quote">
						<?php if ( '' !== $quote ) : ?>
							<strong><?php echo esc_html( $quote ); ?></strong>
						<?php endif; ?>
						<?php if ( '' !== $quote_sub ) : ?>
							<span><?php echo esc_html( $quote_sub ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_button && ! $has_panel ) : ?>
					<a class="ap-demo-cta__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $image ) : ?>
				<div class="ap-demo-cta__media">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>

			<?php if ( $has_panel ) : ?>
				<div class="ap-demo-cta__panel">
					<?php if ( '' !== $form_heading ) : ?>
						<h3 class="ap-demo-cta__form-title"><?php echo esc_html( $form_heading ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $form_id ) : ?>
						<div class="ap-demo-cta__form" id="<?php echo esc_attr( $form_id ); ?>" data-ap-form="<?php echo esc_attr( $form_id ); ?>"<?php if ( '' !== $hs_portal && '' !== $hs_form ) : ?> data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>" data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>" data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"<?php endif; ?>></div>
					<?php elseif ( $has_button ) : ?>
						<a class="ap-demo-cta__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
