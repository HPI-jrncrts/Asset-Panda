<?php
/**
 * faq — accordion of questions.
 *
 * Matches the live `.ap-faq` section. Built on <details>, so it opens and
 * closes with no JavaScript at all; `name` makes the group exclusive in
 * browsers that support it and simply allows several open in those that don't.
 *
 * Deliberately NOT animated: animating ::details-content needs
 * `overflow: hidden`, which slices any superscript on a panel's first line.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$question = trim( (string) ( $item['q'] ?? '' ) );
	$answer   = trim( (string) ( $item['a'] ?? '' ) );
	if ( '' === $question || '' === $answer ) {
		continue;
	}
	$items[] = array(
		'q' => $question,
		'a' => $answer,
	);
}

if ( empty( $items ) ) {
	return;
}

$title   = trim( (string) $props['title'] );
$eyebrow = trim( (string) $props['eyebrow'] );

// One accordion group per render, so two FAQs on a page stay independent.
static $ap_faq_instance = 0;
++$ap_faq_instance;
$group = 'ap-faq-' . $ap_faq_instance;

/*
 * The industry hubs run their own FAQ sheets (live #ap-gov-faq / #ap-eduhub-faq),
 * ported whole into the hub / eduhub variants with live's class names; the rows
 * are <details> so they still open without JavaScript, and like live every
 * question starts closed.
 */
if ( in_array( $props['variant'], array( 'hub', 'eduhub' ), true ) ) {
	$ap_faq_hub    = 'hub' === $props['variant'];
	$ap_faq_class  = 'ap-faq ap-faq--' . $props['variant'];
	if ( $ap_faq_hub && 'accent' === $props['question_tone'] ) {
		$ap_faq_class .= ' ap-faq--q-accent';
	}
	?>
<section class="<?php echo esc_attr( $ap_faq_class ); ?>"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
	<?php if ( $ap_faq_hub ) : ?>
		<h2 class="apg-h2"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
		<div class="apg-faq-list">
			<?php foreach ( $items as $i => $item ) : ?>
				<details class="apg-faq-item" name="<?php echo esc_attr( $group ); ?>"<?php echo ( 0 === $i && $props['open_first'] ) ? ' open' : ''; ?>>
					<summary class="apg-faq-q"><span class="apg-q-text"><?php echo ap_ui_brandmarks( esc_html( $item['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span><span class="apg-faq-ico" aria-hidden="true"></span></summary>
					<div class="apg-faq-a"><div class="apg-faq-a-in"><p><?php echo ap_ui_brandmarks( ap_ui_lines( $item['a'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p></div></div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="apf-container">
			<h2 class="apf-h2"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<div class="apf-list">
				<?php foreach ( $items as $i => $item ) : ?>
					<details class="apf-item" name="<?php echo esc_attr( $group ); ?>"<?php echo ( 0 === $i && $props['open_first'] ) ? ' open' : ''; ?>>
						<summary class="apf-q"><?php echo ap_ui_brandmarks( esc_html( $item['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?><span class="apf-q-icon" aria-hidden="true"></span></summary>
						<div class="apf-a-wrap"><p class="apf-a"><?php echo ap_ui_brandmarks( ap_ui_lines( $item['a'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php
	if ( $props['schema'] ) {
		$entities = array();
		foreach ( $items as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['a'],
				),
			);
		}
		$graph = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped: JSON_HEX_TAG encodes < and >, so </script> cannot form. ?></script>
		<?php
	}
	?>
</section>
	<?php
	return;
}

$vertical = 'vertical' === $props['variant'];
$classes  = array( 'ap-faq' );
if ( $vertical ) {
	$classes[] = 'ap-faq--vertical';
}
if ( 'white' === $props['tone'] ) {
	$classes[] = 'ap-faq--white';
}
$gap = (int) $props['title_gap'];
$gap_m = (int) $props['title_gap_m'];
$ap_faq_vars = array();
if ( $vertical && $gap >= 0 && $gap <= 200 && 28 !== $gap ) {
	$ap_faq_vars[] = '--ap-faq-gap:' . $gap . 'px';
}
if ( $vertical && $gap_m >= 0 && $gap_m <= 200 && $gap_m !== $gap ) {
	$ap_faq_vars[] = '--ap-faq-gap-m:' . $gap_m . 'px';
}
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $ap_faq_vars ? ' style="' . esc_attr( implode( ';', $ap_faq_vars ) ) . '"' : ''; ?><?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
	<div class="ap-faq__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-faq__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h2 class="ap-faq__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>

		<div class="ap-faq__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<details
					class="ap-faq__item"
					name="<?php echo esc_attr( $group ); ?>"
					<?php echo ( 0 === $i && $props['open_first'] ) ? 'open' : ''; ?>
				>
					<summary class="ap-faq__q">
						<span class="ap-faq__q-text"><?php echo ap_ui_brandmarks( esc_html( $item['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
						<span class="ap-faq__chev" aria-hidden="true">
							<?php if ( $vertical ) : ?>
								<svg class="ap-faq__chev-closed" viewBox="0 0 10.6667 18.6667" preserveAspectRatio="none" fill="none"><path d="M1.33333 1.33333L9.33333 9.33333L1.33333 17.3333" stroke="#00A991" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<svg class="ap-faq__chev-open" viewBox="0 0 18.6667 10.6667" preserveAspectRatio="none" fill="none"><path d="M1.33333 9.33333L9.33333 1.33333L17.3333 9.33333" stroke="#5044E1" stroke-width="2.66667" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<?php else : ?>
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg>
							<?php endif; ?>
						</span>
					</summary>
					<div class="ap-faq__a">
						<p><?php echo ap_ui_brandmarks( ap_ui_lines( $item['a'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>

	<?php
	if ( $props['schema'] ) {
		$entities = array();
		foreach ( $items as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['a'],
				),
			);
		}
		$graph = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped: JSON_HEX_TAG encodes < and >, so </script> cannot form. ?></script>
		<?php
	}
	?>
</section>
