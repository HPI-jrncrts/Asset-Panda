<?php
/**
 * problem-callouts — the "here is what goes wrong today" section.
 *
 * Matches the live `.ap-problem` section: centred copy, a photo ringed by
 * floating chips, a closing note, and a CTA bar. The first five chips take the
 * five placements the design defines; any beyond that stack underneath. On
 * phones every chip sits in one column under the photo.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'indigo', 'rose', 'mint', 'slate', 'amber' );

$cards = array();
foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( ! is_array( $card ) || '' === trim( (string) ( $card['text'] ?? '' ) ) ) {
		continue;
	}
	$tone    = trim( (string) ( $card['tone'] ?? '' ) );
	$cards[] = array(
		'text'     => trim( (string) $card['text'] ),
		'icon'     => trim( (string) ( $card['icon'] ?? '' ) ),
		'icon_svg' => ap_ui_svg( $card['icon_svg'] ?? '' ),
		'icon_alt' => (string) ( $card['icon_alt'] ?? '' ),
		// Fall back to the design's own order of tones.
		'tone'     => in_array( $tone, $tones, true ) ? $tone : $tones[ count( $cards ) % count( $tones ) ],
	);
}

$title      = trim( (string) $props['title'] );
$eyebrow    = trim( (string) $props['eyebrow'] );
$lede       = trim( (string) $props['lede'] );
$kicker     = trim( (string) $props['kicker'] );
$note       = trim( (string) $props['note'] );
$image      = trim( (string) $props['image'] );
$background = trim( (string) $props['background'] );
$cta_title  = trim( (string) $props['cta_title'] );
$cta_accent = trim( (string) $props['cta_accent'] );
$cta_sub    = trim( (string) $props['cta_sub'] );
$cta_logo   = trim( (string) $props['cta_logo'] );
if ( '' !== $cta_logo && ! preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|/)#i', $cta_logo ) ) {
	$cta_logo = ap_ui_asset_url( 'problem-callouts', $cta_logo );
}
$cta_label  = trim( (string) $props['cta_label'] );
$cta_url    = trim( (string) $props['cta_url'] );
$has_cta    = '' !== $cta_title || '' !== $cta_sub || ( '' !== $cta_label && '' !== $cta_url );
?>
<section class="ap-problem-callouts">
	<?php if ( '' !== $background ) : ?>
		<img class="ap-problem-callouts__bg" src="<?php echo esc_url( $background ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
	<?php endif; ?>

	<div class="ap-problem-callouts__inner">
		<div class="ap-problem-callouts__header"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-problem-callouts__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-problem-callouts__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-problem-callouts__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<?php if ( '' !== $kicker ) : ?>
				<p class="ap-problem-callouts__kicker"><?php echo esc_html( $kicker ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $image || ! empty( $cards ) ) : ?>
			<div class="ap-problem-callouts__stage">
				<?php if ( '' !== $image ) : ?>
					<img class="ap-problem-callouts__photo" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="lazy" decoding="async" />
				<?php endif; ?>

				<?php foreach ( $cards as $card ) : ?>
					<div class="ap-problem-callouts__card ap-problem-callouts__card--<?php echo esc_attr( $card['tone'] ); ?>">
						<?php if ( '' !== $card['icon_svg'] ) : ?>
							<span class="ap-problem-callouts__ico" aria-hidden="true"><?php echo $card['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php elseif ( '' !== $card['icon'] ) : ?>
							<span class="ap-problem-callouts__ico">
								<img src="<?php echo esc_url( $card['icon'] ); ?>" alt="<?php echo esc_attr( $card['icon_alt'] ); ?>" loading="lazy" decoding="async" />
							</span>
						<?php endif; ?>
						<p><?php echo ap_ui_brandmarks( esc_html( $card['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $note ) : ?>
			<p class="ap-problem-callouts__note"><?php echo ap_ui_brandmarks( ap_ui_lines( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
		<?php endif; ?>

		<?php if ( $has_cta ) : ?>
			<div class="ap-problem-callouts__cta"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<div class="ap-problem-callouts__cta-body">
					<?php if ( '' !== $cta_logo ) : ?>
						<span class="ap-problem-callouts__cta-logo">
							<img src="<?php echo esc_url( $cta_logo ); ?>" alt="" loading="lazy" decoding="async" />
						</span>
					<?php endif; ?>
					<div>
						<?php if ( '' !== $cta_title || '' !== $cta_accent ) : ?>
							<h3 class="ap-problem-callouts__cta-title">
								<?php echo ap_ui_brandmarks( esc_html( $cta_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?>
								<?php if ( '' !== $cta_accent ) : ?>
									<span class="ap-problem-callouts__cta-accent"><?php echo ap_ui_brandmarks( esc_html( $cta_accent ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
								<?php endif; ?>
							</h3>
						<?php endif; ?>
						<?php if ( '' !== $cta_sub ) : ?>
							<p class="ap-problem-callouts__cta-sub"><?php echo esc_html( $cta_sub ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<a class="ap-problem-callouts__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
