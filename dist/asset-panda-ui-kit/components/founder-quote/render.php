<?php
/**
 * founder-quote — "Why we built it".
 *
 * Matches the live `.apx-fdr` section: eyebrow and title, then a rounded
 * portrait with the Asset Panda logo beside a blockquote holding the pull quote
 * (second line in the brand gradient), the note and the attribution.
 * Paragraph text is escaped first; only **bold** runs and the Ursa mark are
 * turned back into fixed markup afterwards.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ap_para = static function ( $text ) {
	$html = esc_html( trim( (string) $text ) );
	$html = preg_replace( '/\*\*(.+?)\*\*/', '<b>$1</b>', $html );
	return preg_replace( '/\bUrsa\s?AI\b/', 'Ursa<span class="apx-sup">AI</span>', $html );
};

$body = array_values(
	array_filter(
		array_map(
			static function ( $p ) {
				return trim( (string) $p );
			},
			is_array( $props['body'] ) ? $props['body'] : array()
		),
		static function ( $p ) {
			return '' !== $p;
		}
	)
);

// The quote mark is styled by its own class, so set it on the sanitised SVG.
$qmark = ap_ui_svg( $props['quote_svg'] );
if ( '' !== $qmark ) {
	$qmark = preg_replace( '/^<svg\b([^>]*?)\sclass="[^"]*"/', '<svg$1', $qmark );
	$qmark = preg_replace( '/^<svg\b/', '<svg class="ap-founder-quote__qmark"', $qmark );
}

$photo  = trim( (string) $props['photo'] );
$logo   = trim( (string) $props['logo'] );
$pull   = trim( (string) $props['pull'] );
$accent = trim( (string) $props['pull_accent'] );
// Opt-in extras (Partners): a button under the attribution, and a portrait without the corner fade.
$ap_cta_label = trim( (string) $props['cta_label'] );
$ap_cta_url   = trim( (string) $props['cta_url'] );
?>
<section class="ap-founder-quote<?php echo false === $props['photo_fade'] ? ' ap-founder-quote--nofade' : ''; ?>"<?php echo '' !== trim( (string) $props['eyebrow'] ) ? ' aria-label="' . esc_attr( $props['eyebrow'] ) . '"' : ''; ?>>
	<div class="ap-founder-quote__glow" aria-hidden="true"></div>
	<div class="ap-founder-quote__wrap">
		<div class="ap-founder-quote__head">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-founder-quote__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-founder-quote__title"><?php echo ap_ui_lines( $props['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
		</div>
		<div class="ap-founder-quote__row">
			<?php if ( '' !== $photo ) : ?>
				<figure class="ap-founder-quote__photo">
					<img class="ap-founder-quote__pic" src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" loading="lazy" decoding="async" />
					<?php if ( '' !== $logo ) : ?>
						<img class="ap-founder-quote__logo" src="<?php echo esc_url( $logo ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
					<?php endif; ?>
				</figure>
			<?php endif; ?>
			<blockquote class="ap-founder-quote__quote">
				<?php echo $qmark; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(); only a fixed class is set. ?>
				<?php if ( '' !== $pull || '' !== $accent ) : ?>
					<p class="ap-founder-quote__pull"><?php echo esc_html( $pull ); ?><?php echo '' !== $pull && '' !== $accent ? '<br />' : ''; ?><?php echo '' !== $accent ? '<span class="g">' . esc_html( $accent ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; fixed span. ?></p>
				<?php endif; ?>
				<?php if ( $body ) : ?>
					<div class="ap-founder-quote__body">
						<?php foreach ( $body as $p ) : ?>
							<p><?php echo $ap_para( $p ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <b> and a fixed span are added. ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== trim( (string) $props['author'] ) || '' !== trim( (string) $props['author_role'] ) ) : ?>
					<div class="ap-founder-quote__author">
						<?php if ( '' !== trim( (string) $props['author'] ) ) : ?>
							<p class="n">&mdash; <?php echo esc_html( $props['author'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) $props['author_role'] ) ) : ?>
							<p class="r"><?php echo esc_html( $props['author_role'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
<?php if ( '' !== $ap_cta_label && '' !== $ap_cta_url ) : ?>
				<p class="ap-founder-quote__cta"><a class="ap-founder-quote__btn" href="<?php echo esc_url( $ap_cta_url ); ?>"><?php echo esc_html( $ap_cta_label ); ?></a></p>
<?php endif; ?>
			</blockquote>
		</div>
	</div>
</section>
