<?php
/**
 * thanks-steps — "What happens next" step cards.
 *
 * Matches live #ap-demo-next-steps, the second section of the seven 2026 demo
 * thank-you pages: a centred eyebrow and heading over a row of step cards
 * (three across from 992px, stacked below). Each card opens with a pill label
 * whose colour comes from the item's tone. Live marks the heading group up as
 * a <header>; the theme pins every <header> on phones, so this is a <div>.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'yellow', 'purple', 'green' );
$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$title = trim( (string) ( $item['title'] ?? '' ) );
	$body  = trim( (string) ( $item['body'] ?? '' ) );
	if ( '' === $title && '' === $body ) {
		continue;
	}
	$tone    = (string) ( $item['tone'] ?? '' );
	$items[] = array(
		'label' => trim( (string) ( $item['label'] ?? '' ) ),
		// Without a tone the pills cycle through the three colours, as on live.
		'tone'  => in_array( $tone, $tones, true ) ? $tone : $tones[ count( $items ) % 3 ],
		'title' => $title,
		'body'  => $body,
	);
}

$eyebrow = trim( (string) $props['eyebrow'] );
?>
<section class="ap-thanks-steps">
	<div class="ap-thanks-steps__inner">
		<div class="ap-thanks-steps__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-thanks-steps__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-thanks-steps__title"><?php echo ap_ui_lines( $props['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>
		</div>
		<?php if ( $items ) : ?>
			<div class="ap-thanks-steps__grid">
				<?php foreach ( $items as $item ) : ?>
					<article class="ap-thanks-steps__card">
						<?php if ( '' !== $item['label'] ) : ?>
							<span class="ap-thanks-steps__label ap-thanks-steps__label--<?php echo esc_attr( $item['tone'] ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
						<?php endif; ?>
						<div class="ap-thanks-steps__text">
							<?php if ( '' !== $item['title'] ) : ?>
								<h3 class="ap-thanks-steps__card-title"><?php echo esc_html( $item['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( '' !== $item['body'] ) : ?>
								<p class="ap-thanks-steps__card-body"><?php echo esc_html( $item['body'] ); ?></p>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
