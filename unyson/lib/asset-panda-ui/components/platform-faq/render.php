<?php
/**
 * platform-faq — the FAQ on the Asset Intelligence Platform page.
 *
 * Matches the live .apx-faq: the heading in a 259px left column beside ruled
 * <details> rows; the open row's question turns indigo and its plus becomes a
 * minus. Inner class names mirror live's (.apx-faq__x → .ap-platform-faq__x).
 * UrsaAI keeps live's own mark (.g + .apx-sup).
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
	$q = trim( (string) ( $item['q'] ?? '' ) );
	$a = trim( (string) ( $item['a'] ?? '' ) );
	if ( '' !== $q && '' !== $a ) {
		$items[] = array(
			'q' => $q,
			'a' => $a,
		);
	}
}
if ( empty( $items ) ) {
	return;
}

$ap_ursa = static function ( $escaped ) {
	return preg_replace( '/\bUrsa\s?AI\b/', '<span class="g">Ursa<span class="apx-sup">AI</span></span>', (string) $escaped );
};
?>
<section class="ap-platform-faq" aria-label="<?php echo esc_attr( $props['title'] ); ?>">
	<div class="ap-platform-faq__wrap">
		<h2 class="ap-platform-faq__title"><?php echo esc_html( $props['title'] ); ?></h2>
		<div class="ap-platform-faq__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<details class="ap-platform-faq__item"<?php echo ( 0 === $i && $props['open_first'] ) ? ' open' : ''; ?>>
					<summary class="ap-platform-faq__q">
						<span class="ap-platform-faq__qt"><?php echo $ap_ursa( esc_html( $item['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only fixed spans are added. ?></span>
						<span class="ap-platform-faq__ic" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none"><path d="M2.5 10h15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path class="v" d="M10 2.5v15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
					</summary>
					<p class="ap-platform-faq__a"><?php echo $ap_ursa( esc_html( $item['a'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only fixed spans are added. ?></p>
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
