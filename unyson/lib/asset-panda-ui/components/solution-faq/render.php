<?php
/**
 * solution-faq — the solution pages' FAQ accordion.
 *
 * Matches the live .epx-faq section. Built on <details>, so it opens and
 * closes with no JavaScript; `name` makes the group exclusive where supported.
 * Live animates ::details-content, which needs overflow: hidden and slices a
 * superscript on a panel's first line. The kit leaves that alone; instead the
 * list carries data-ap-accordion and the shared motion runtime animates the
 * row's height (clipping the row only while it moves).
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

$variant = in_array( $props['variant'], array( 'solution', 'readiness', 'service' ), true ) ? $props['variant'] : 'solution';
// The solution pages mark each question up as an h3; the others use a span.
$q_tag = 'solution' === $variant ? 'h3' : 'span';

static $ap_sfaq_instance = 0;
++$ap_sfaq_instance;
$group = 'ap-solution-faq-' . $ap_sfaq_instance;
?>
<section class="ap-solution-faq ap-solution-faq--<?php echo esc_attr( $variant ); ?><?php echo 'white' === $props['tone'] ? ' ap-solution-faq--white' : ''; ?>">
	<div class="ap-solution-faq__wrap">
		<h2 class="ap-solution-faq__title ap-solution-faq__rev"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
		<div class="ap-solution-faq__list ap-solution-faq__rev ap-solution-faq__rev--d1" data-ap-accordion>
			<?php foreach ( $items as $i => $item ) : ?>
				<details class="ap-solution-faq__item" name="<?php echo esc_attr( $group ); ?>"<?php echo ( 0 === $i && $props['open_first'] ) ? ' open' : ''; ?>>
					<summary><<?php echo $q_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h3/span. ?> class="ap-solution-faq__q"><?php echo ap_ui_brandmarks( esc_html( $item['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></<?php echo $q_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h3/span. ?>><span class="ap-solution-faq__mark" aria-hidden="true"></span></summary>
					<div class="ap-solution-faq__panel">
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
