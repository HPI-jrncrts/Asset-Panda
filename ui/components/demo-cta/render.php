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
	// Unique across the page: a `static` counter restarts on every render (the
	// loader includes this file inside a fresh closure).
	$form_id = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-demo-cta-form-' ) : 'ap-demo-cta-form-' . substr( md5( uniqid( '', true ) ), 0, 8 );
}
$cta_label    = trim( (string) $props['cta_label'] );
$cta_url      = trim( (string) $props['cta_url'] );
$has_button   = '' !== $cta_label && '' !== $cta_url;
$image        = trim( (string) $props['image'] );
$has_panel    = ( '' !== $form_id || '' !== $form_heading ) && '' === $image;

// Placeholder text and the submit label the form takes once HubSpot has drawn
// it. Plain strings keyed by field name; the script sets them as attributes.
$hs_text = array();
foreach ( is_array( $props['form_placeholders'] ) ? $props['form_placeholders'] : array() as $field => $text ) {
	if ( preg_match( '/^[a-z0-9_]{1,64}$/', (string) $field ) && is_scalar( $text ) && '' !== trim( (string) $text ) ) {
		$hs_text[ (string) $field ] = substr( trim( (string) $text ), 0, 120 );
	}
}
$hs_submit = substr( trim( (string) $props['form_submit'] ), 0, 60 );

/*
 * The industry hubs close on their own CTA bands (live #ap-gov-fcta and
 * #ap-eduhub-fcta), ported whole into the hub / eduhub variants with live's
 * class names: copy and one button over the gradient card, the product art
 * bottom-right.
 */
if ( in_array( $props['variant'], array( 'hub', 'eduhub' ), true ) ) {
	$ap_img_w   = (int) $props['image_width'];
	$ap_img_h   = (int) $props['image_height'];
	$ap_img_dim = ( $ap_img_w > 0 && $ap_img_h > 0 ) ? ' width="' . $ap_img_w . '" height="' . $ap_img_h . '"' : '';
	$ap_img_tag = '' !== $image ? '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $props['image_alt'] ) . '"' . $ap_img_dim . ' loading="lazy" decoding="async" />' : '';
	if ( 'hub' === $props['variant'] ) :
		?>
<section class="ap-demo-cta ap-demo-cta--hub">
	<div class="apg-fcta-card"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
		<div class="apg-fcta-copy">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="apg-fcta-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="apg-h2"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $body ) : ?>
				<p class="apg-fcta-body"><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<?php if ( $has_button ) : ?>
				<a class="apg-btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $ap_img_tag ) : ?>
			<div class="apg-fcta-media"><?php echo $ap_img_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above. ?></div>
		<?php endif; ?>
	</div>
</section>
		<?php
	else :
		?>
<section class="ap-demo-cta ap-demo-cta--eduhub">
	<div class="ap-final-cta-card"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
		<div class="ap-final-cta-copy">
			<div class="ap-text-group">
				<div class="ap-heading-group">
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="ap-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>
					<h2><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				</div>
				<?php if ( '' !== $body ) : ?>
					<div class="ap-paragraph-group"><p><?php echo ap_ui_brandmarks( ap_ui_lines( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p></div>
				<?php endif; ?>
			</div>
			<?php if ( $has_button ) : ?>
				<div class="ap-btn-group"><a class="ap-btn ap-btn-primary" href="<?php echo esc_url( $cta_url ); ?>"><span class="ap-btn-lbl"><?php echo esc_html( $cta_label ); ?></span></a></div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $ap_img_tag ) : ?>
			<div class="ap-final-cta-art" aria-hidden="true"><?php echo $ap_img_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above. ?></div>
		<?php endif; ?>
	</div>
</section>
		<?php
	endif;
	return;
}

$classes = array( 'ap-demo-cta' );
if ( 'platform' === $props['variant'] ) {
	$classes[] = 'ap-demo-cta--platform';
}
// The form card closes the vertical pages, inside live's footer wrapper.
if ( $has_panel ) {
	$classes[] = 'ap-demo-cta--form';
}
$bg = trim( (string) $props['background'] );
// Opt-in extras (Partners): an id the page's buttons can jump to, a check list under the paragraph, and the
// form in a white card. Without them the card renders exactly as on the product pages.
$ap_anchor  = preg_match( '/^[A-Za-z][A-Za-z0-9-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$ap_bullets = array_values(
	array_filter(
		array_map(
			static function ( $b ) {
				return is_scalar( $b ) ? trim( (string) $b ) : '';
			},
			is_array( $props['bullets'] ) ? $props['bullets'] : array()
		),
		static function ( $b ) {
			return '' !== $b;
		}
	)
);
$ap_card    = 'card' === $props['panel'] && $has_panel;
if ( $ap_card ) {
	$classes[] = 'ap-demo-cta--card';
}
// Figma's circle-check (20px, brand green stroke), drawn inline beside each list item.
$ap_check = '<svg class="ap-demo-cta__check" viewBox="0 0 20 20" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><path d="M7.5 10L9.16667 11.6667L12.5 8.33333M2.5 10C2.5 10.9849 2.69399 11.9602 3.0709 12.8701C3.44781 13.7801 4.00026 14.6069 4.6967 15.3033C5.39314 15.9997 6.21993 16.5522 7.12987 16.9291C8.03982 17.306 9.01509 17.5 10 17.5C10.9849 17.5 11.9602 17.306 12.8701 16.9291C13.7801 16.5522 14.6069 15.9997 15.3033 15.3033C15.9997 14.6069 16.5522 13.7801 16.9291 12.8701C17.306 11.9602 17.5 10.9849 17.5 10C17.5 9.01509 17.306 8.03982 16.9291 7.12987C16.5522 6.21993 15.9997 5.39314 15.3033 4.6967C14.6069 4.00026 13.7801 3.44781 12.8701 3.0709C11.9602 2.69399 10.9849 2.5 10 2.5C9.01509 2.5 8.03982 2.69399 7.12987 3.0709C6.21993 3.44781 5.39314 4.00026 4.6967 4.6967C4.00026 5.39314 3.44781 6.21993 3.0709 7.12987C2.69399 8.03982 2.5 9.01509 2.5 10Z" stroke="#00A991" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $ap_anchor ? ' id="' . esc_attr( $ap_anchor ) . '"' : ''; ?><?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-demo-cta-bg:url("' . esc_url_raw( $bg ) . '")' ) . '"' : ''; ?>>
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

<?php if ( $ap_bullets ) : ?>
				<ul class="ap-demo-cta__list">
	<?php foreach ( $ap_bullets as $ap_bullet ) : ?>
					<li><?php echo $ap_check; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo esc_html( $ap_bullet ); ?></span></li>
	<?php endforeach; ?>
				</ul>
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
				<div class="ap-demo-cta__panel<?php echo $ap_card ? ' ap-demo-cta__panel--card' : ''; ?>" data-ap-form-skin>
					<?php if ( '' !== $form_heading ) : ?>
						<h3 class="ap-demo-cta__form-title"><?php echo esc_html( $form_heading ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $form_id ) : ?>
						<div class="ap-demo-cta__form" id="<?php echo esc_attr( $form_id ); ?>" data-ap-form="<?php echo esc_attr( $form_id ); ?>"<?php if ( '' !== $hs_portal && '' !== $hs_form ) : ?> data-ap-hs-portal="<?php echo esc_attr( $hs_portal ); ?>" data-ap-hs-form="<?php echo esc_attr( $hs_form ); ?>" data-ap-hs-region="<?php echo esc_attr( $hs_region ); ?>"<?php if ( $hs_text ) : ?> data-ap-hs-placeholders="<?php echo esc_attr( wp_json_encode( $hs_text ) ); ?>"<?php endif; ?><?php if ( '' !== $hs_submit ) : ?> data-ap-hs-submit="<?php echo esc_attr( $hs_submit ); ?>"<?php endif; ?><?php endif; ?>></div>
					<?php elseif ( $has_button ) : ?>
						<a class="ap-demo-cta__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
