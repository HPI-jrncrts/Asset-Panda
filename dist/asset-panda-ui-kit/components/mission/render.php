<?php
/**
 * mission — values section with photo and pull quote.
 *
 * Figma: Home Page > The Heart Behind Asset Panda (5118:60737). NOTE: the
 * designer annotated this section "On hold. No confirmation to dev yet."
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

$eyebrow   = trim( (string) $props['eyebrow'] );
$image     = trim( (string) $props['image'] );
$statement = trim( (string) $props['statement'] );
$body      = trim( (string) $props['body'] );
$reveal    = (bool) $props['reveal'];
$has_cta   = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
?>
<section class="ap-mission">
	<div class="ap-mission__inner">
		<div class="ap-mission__head"<?php echo $reveal ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-mission__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-mission__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
		</div>

		<div class="ap-mission__row<?php echo '' === $image ? ' ap-mission__row--no-media' : ''; ?>">
			<?php if ( '' !== $image ) : ?>
				<div class="ap-mission__media"<?php echo $reveal ? ' data-ap-reveal="fade"' : ''; ?>>
					<img class="ap-mission__img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>

			<div class="ap-mission__copy"<?php echo $reveal ? ' data-ap-reveal data-ap-reveal-delay="2"' : ''; ?>>
				<?php if ( '' !== $statement ) : ?>
					<figure class="ap-mission__quote">
						<img class="ap-mission__quote-mark" src="<?php echo esc_url( ap_ui_asset_url( 'mission', 'quote-mark.svg' ) ); ?>" alt="" width="19" height="16" />
						<blockquote class="ap-mission__statement"><p><?php echo esc_html( $statement ); ?></p></blockquote>
						<figcaption class="ap-mission__brand">
							<img src="<?php echo esc_url( ap_ui_asset_url( 'mission', 'panda.svg' ) ); ?>" alt="" width="15" height="16" />
							<img src="<?php echo esc_url( ap_ui_asset_url( 'mission', 'wordmark.svg' ) ); ?>" alt="Asset Panda" width="29" height="17" />
						</figcaption>
					</figure>
				<?php endif; ?>
				<?php if ( '' !== $body ) : ?>
					<p class="ap-mission__body"><?php echo ap_ui_lines( $body ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
				<?php endif; ?>
				<?php if ( $has_cta ) : ?>
					<a class="ap-mission__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
