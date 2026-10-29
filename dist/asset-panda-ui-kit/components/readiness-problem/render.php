<?php
/**
 * readiness-problem — "readiness fails in the gaps".
 *
 * Matches the live .epx-prob section of Emergency Preparedness and School
 * Reunification: centred heading, the product photo with four issue pills at
 * the design's fixed places (re-anchored to the right edge below 1181px,
 * stacked under the photo below 901px), and a CTA strip. Pieces reveal
 * through a CSS scroll timeline, as on live.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'red', 'green', 'blue', 'yellow' );
$chips = array();
foreach ( is_array( $props['chips'] ) ? $props['chips'] : array() as $chip ) {
	if ( count( $chips ) >= 4 || ! is_array( $chip ) || '' === trim( (string) ( $chip['text'] ?? '' ) ) ) {
		continue;
	}
	$chips[] = array(
		'text'     => trim( (string) $chip['text'] ),
		'icon_svg' => ap_ui_svg( $chip['icon_svg'] ?? '' ),
		'tone'     => $tones[ count( $chips ) ],
	);
}

// Only plain http(s) or root-relative URLs reach the style attribute.
$bg      = trim( (string) $props['background'] );
$bg      = preg_match( '#^(?:https?://|/)[^\s"\'()\\\\<>]+$#i', $bg ) ? $bg : '';
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
$image   = trim( (string) $props['image'] );
$icon    = trim( (string) $props['cta_icon'] );
$has_cta = '' !== trim( (string) $props['cta_title'] ) || '' !== trim( (string) $props['cta_body'] );
$has_btn = '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] );
?>
<section class="ap-readiness-problem"<?php echo '' !== $bg ? ' style="' . esc_attr( '--ap-rp-bg:url("' . $bg . '")' ) . '"' : ''; ?>>
	<div class="ap-readiness-problem__orbs" aria-hidden="true"></div>
	<div class="ap-readiness-problem__wrap">
		<div class="ap-readiness-problem__head ap-readiness-problem__rev">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-readiness-problem__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-readiness-problem__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-readiness-problem__lede"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $image || $chips ) : ?>
			<div class="ap-readiness-problem__cards">
				<?php if ( '' !== $image ) : ?>
					<img class="ap-readiness-problem__img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" width="547" height="364" loading="lazy" decoding="async" />
				<?php endif; ?>
				<?php foreach ( $chips as $i => $chip ) : ?>
					<span class="ap-readiness-problem__pill ap-readiness-problem__pill--<?php echo esc_attr( $chip['tone'] ); ?> ap-readiness-problem__rev ap-readiness-problem__rev--d<?php echo esc_attr( (string) ( $i + 1 ) ); ?>"><?php echo $chip['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?><?php echo esc_html( $chip['text'] ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $has_cta || $has_btn ) : ?>
			<div class="ap-readiness-problem__cta ap-readiness-problem__rev">
				<?php if ( '' !== $icon ) : ?>
					<img class="ap-readiness-problem__panda" src="<?php echo esc_url( $icon ); ?>" alt="" width="42" height="46" loading="lazy" decoding="async" />
				<?php endif; ?>
				<div class="ap-readiness-problem__ctatext">
					<?php if ( '' !== trim( (string) $props['cta_title'] ) ) : ?>
						<h3><?php echo ap_ui_brandmarks( esc_html( $props['cta_title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['cta_body'] ) ) : ?>
						<p><?php echo ap_ui_brandmarks( esc_html( $props['cta_body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					<?php endif; ?>
				</div>
				<?php if ( $has_btn ) : ?>
					<a class="ap-readiness-problem__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
