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
?>
<section class="ap-faq"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
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
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg>
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
		<script type="application/ld+json"><?php echo wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode escapes. ?></script>
		<?php
	}
	?>
</section>
