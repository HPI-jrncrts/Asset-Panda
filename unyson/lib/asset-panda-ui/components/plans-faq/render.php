<?php
/**
 * plans-faq — the pricing v2 FAQ.
 *
 * Built from the Asset Panda Pricing v2 mockup: bordered cards on the grey
 * band, each a <details> that opens in place with no JavaScript. Several can
 * be open at once, as in the mockup. A blank line in an answer starts a new
 * paragraph.
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
		'q'     => $question,
		'paras' => array_values( array_filter( array_map( 'trim', preg_split( '/(?:\r\n|\r|\n)\s*(?:\r\n|\r|\n)/', $answer ) ), 'strlen' ) ),
	);
}

$title = trim( (string) $props['title'] );
if ( '' === $title || empty( $items ) ) {
	return;
}
?>
<section class="ap-plans-faq ap-plans-faq--<?php echo 'clean' === $props['variant'] ? 'clean' : 'classic'; ?>">
	<div class="ap-plans-faq__inner">
		<h2 class="ap-plans-faq__title"><?php echo ap_ui_lines( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></h2>

		<div class="ap-plans-faq__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<details class="ap-plans-faq__item"<?php echo ( 0 === $i && $props['open_first'] ) ? ' open' : ''; ?>>
					<summary class="ap-plans-faq__q"><?php echo esc_html( $item['q'] ); ?></summary>
					<div class="ap-plans-faq__a">
						<?php foreach ( $item['paras'] as $para ) : ?>
							<p><?php echo ap_ui_lines( $para ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> is added. ?></p>
						<?php endforeach; ?>
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
					'text'  => implode( ' ', $item['paras'] ),
				),
			);
		}
		$graph = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode escapes; JSON_HEX_TAG keeps </script> out. ?></script>
		<?php
	}
	?>
</section>
