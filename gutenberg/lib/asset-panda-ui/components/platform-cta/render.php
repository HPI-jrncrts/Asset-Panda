<?php
/**
 * platform-cta — the closing band on the Asset Intelligence Platform page.
 *
 * Matches live's .apx-cta-bleed band: a dark gradient card holding the eyebrow,
 * heading, paragraph and two buttons, with the product art pinned to the
 * bottom-right (stacked under the copy below 992px). Inner class names are
 * live's, so the ported sheet stays diffable.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eyebrow   = trim( (string) $props['eyebrow'] );
$title     = trim( (string) $props['title'] );
$body      = trim( (string) $props['body'] );
$cta_label = trim( (string) $props['cta_label'] );
$cta_url   = trim( (string) $props['cta_url'] );
$alt_label = trim( (string) $props['secondary_label'] );
$alt_url   = trim( (string) $props['secondary_url'] );
$image     = trim( (string) $props['image'] );
$has_cta   = '' !== $cta_label && '' !== $cta_url;
$has_alt   = '' !== $alt_label && '' !== $alt_url;

// Live sets UrsaAI with its own superscript (.apx-cta-sup), not the shared mark.
$ap_ursa = static function ( $escaped ) {
	return preg_replace( '/\bUrsa\s?AI\b/', 'Ursa<sup class="apx-cta-sup">AI</sup>', (string) $escaped );
};
?>
<section class="ap-platform-cta">
	<div class="ap-final-cta-section">
		<div class="ap-final-cta-card">
			<div class="ap-final-cta-copy">
				<div class="ap-text-group">
					<div class="ap-heading-group">
						<?php if ( '' !== $eyebrow ) : ?>
							<p class="ap-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
						<?php endif; ?>
						<h2><?php echo esc_html( $title ); ?></h2>
					</div>
					<?php if ( '' !== $body ) : ?>
						<div class="ap-paragraph-group">
							<p><?php echo $ap_ursa( esc_html( $body ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only a fixed <sup> is added. ?></p>
						</div>
					<?php endif; ?>
				</div>
				<?php if ( $has_cta || $has_alt ) : ?>
					<div class="ap-btn-group">
						<?php if ( $has_cta ) : ?>
							<a class="ap-btn ap-btn-primary" href="<?php echo esc_url( $cta_url ); ?>"><span class="ap-btn-lbl"><?php echo esc_html( $cta_label ); ?></span></a>
						<?php endif; ?>
						<?php if ( $has_alt ) : ?>
							<a class="ap-btn ap-btn-outline" href="<?php echo esc_url( $alt_url ); ?>"><?php echo esc_html( $alt_label ); ?></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $image ) : ?>
				<div class="ap-final-cta-art"<?php echo '' === trim( (string) $props['image_alt'] ) ? ' aria-hidden="true"' : ''; ?>>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="607" height="392" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
