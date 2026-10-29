<?php
/**
 * page-faq-hub — the help-centre FAQ (/faq/).
 *
 * Live's /faq/ is six stacked accordions (Using Asset Panda, Barcodes,
 * Supported Devices and Browsers, Security, About Asset Panda, Benefits) with
 * h2 topics and h5 questions. Here the topics stay h2, questions become h3
 * (inside <summary>, which allows one heading), and a sticky topic list plus
 * an instant search sit beside them. Answers keep live's step lists and links:
 * a block of '- ' lines is a <ul>, '1. ' lines an <ol>, anything else a
 * paragraph; **bold** and [label](url) inline. Every piece is escaped; links
 * go through esc_url with an explicit protocol list.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protocols = array( 'http', 'https', 'mailto', 'tel' );

$groups = array();
$used   = array();
foreach ( is_array( $props['groups'] ) ? $props['groups'] : array() as $g ) {
	if ( ! is_array( $g ) || '' === trim( (string) ( $g['title'] ?? '' ) ) ) {
		continue;
	}
	$items = array();
	foreach ( is_array( $g['items'] ?? null ) ? $g['items'] : array() as $it ) {
		if ( is_array( $it ) && '' !== trim( (string) ( $it['q'] ?? '' ) ) && '' !== trim( (string) ( $it['a'] ?? '' ) ) ) {
			$items[] = array(
				'q' => trim( (string) $it['q'] ),
				'a' => trim( (string) $it['a'] ),
			);
		}
	}
	if ( ! $items ) {
		continue;
	}
	$id = trim( (string) ( $g['id'] ?? '' ) );
	if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $id ) ) {
		$id = 'faq-' . trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $g['title'] ) ), '-' );
	}
	while ( isset( $used[ $id ] ) ) {
		$id .= '-2';
	}
	$used[ $id ] = true;
	$groups[]    = array(
		'title' => trim( (string) $g['title'] ),
		'id'    => $id,
		'items' => $items,
	);
}
if ( empty( $groups ) ) {
	return;
}

$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$uid    = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-faqhub-' ) : 'ap-faqhub-' . substr( md5( $groups[0]['title'] ), 0, 8 );

$ap_text = static function ( $text ) {
	$html = esc_html( $text );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};
$ap_inline = static function ( $text ) use ( $ap_text, $protocols ) {
	$text = (string) $text;
	$out  = '';
	$pos  = 0;
	if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$out .= $ap_text( substr( $text, $pos, $hit[0][1] - $pos ) );
			$url  = esc_url( $hit[2][0], $protocols );
			$ext  = preg_match( '#^https?://#i', $hit[2][0] ) && false === strpos( $hit[2][0], 'assetpanda.com' );
			$out .= '' !== $url ? '<a href="' . $url . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};
// Answer blocks: lists and paragraphs.
$ap_answer = static function ( $text ) use ( $ap_inline ) {
	$html = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $block ) {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', trim( $block ) ) ), 'strlen' ) );
		if ( ! $lines ) {
			continue;
		}
		$ul = count( $lines ) === count( preg_grep( '/^- /', $lines ) );
		$ol = count( $lines ) === count( preg_grep( '/^\d+\.\s/', $lines ) );
		if ( $ul || $ol ) {
			$tag   = $ul ? 'ul' : 'ol';
			$html .= '<' . $tag . '>';
			foreach ( $lines as $line ) {
				$html .= '<li>' . $ap_inline( preg_replace( $ul ? '/^- /' : '/^\d+\.\s+/', '', $line ) ) . '</li>';
			}
			$html .= '</' . $tag . '>';
		} else {
			$html .= '<p>' . implode( '<br>', array_map( $ap_inline, $lines ) ) . '</p>';
		}
	}
	return $html;
};
// Plain text for search and JSON-LD.
$ap_plain = static function ( $text ) {
	$t = preg_replace( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', '$1', (string) $text );
	$t = preg_replace( '/^(- |\d+\.\s+)/m', '', $t );
	$t = str_replace( '**', '', $t );
	return trim( preg_replace( '/\s+/', ' ', $t ) );
};
$ap_lower = static function ( $s ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
};

$search     = ! empty( $props['search'] );
$search_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
$total      = 0;
foreach ( $groups as $g ) {
	$total += count( $g['items'] );
}
?>
<section class="ap-page-faq-hub"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?> data-ap-faqhub>
	<div class="ap-page-faq-hub__wrap">
		<aside class="ap-page-faq-hub__side">
			<?php if ( $search ) : ?>
				<div class="ap-page-faq-hub__search">
					<label class="ap-page-faq-hub__sr" for="<?php echo esc_attr( $uid ); ?>-q"><?php echo esc_html( (string) $props['search_label'] ); ?></label>
					<?php echo $search_svg; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
					<input type="search" id="<?php echo esc_attr( $uid ); ?>-q" placeholder="<?php echo esc_attr( (string) $props['search_label'] ); ?>" autocomplete="off" spellcheck="false" data-ap-faq-search />
				</div>
			<?php endif; ?>
			<nav class="ap-page-faq-hub__nav" aria-label="<?php echo esc_attr( trim( (string) $props['nav_title'] ) ); ?>">
				<p class="ap-page-faq-hub__nav-title"><?php echo esc_html( (string) $props['nav_title'] ); ?><span><?php echo esc_html( (string) $total ); ?></span></p>
				<ul>
					<?php foreach ( $groups as $i => $g ) : ?>
						<li><a href="#<?php echo esc_attr( $g['id'] ); ?>" data-ap-faq-link="<?php echo esc_attr( $g['id'] ); ?>"<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>><span><?php echo esc_html( $g['title'] ); ?></span><span class="ap-page-faq-hub__n" data-ap-faq-n><?php echo esc_html( (string) count( $g['items'] ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</aside>
		<div class="ap-page-faq-hub__main">
			<?php foreach ( $groups as $gi => $g ) : ?>
				<div class="ap-page-faq-hub__group" id="<?php echo esc_attr( $g['id'] ); ?>" data-ap-faq-group>
					<h2 class="ap-page-faq-hub__topic"><?php echo esc_html( $g['title'] ); ?></h2>
					<div class="ap-page-faq-hub__list" data-ap-accordion>
						<?php foreach ( $g['items'] as $ii => $it ) : ?>
							<details class="ap-page-faq-hub__item" data-ap-faq-item data-text="<?php echo esc_attr( $ap_lower( $it['q'] . ' ' . $ap_plain( $it['a'] ) ) ); ?>"<?php echo ! empty( $props['open_first'] ) && 0 === $gi && 0 === $ii ? ' open' : ''; ?>>
								<summary class="ap-page-faq-hub__q">
									<h3><?php echo ap_ui_brandmarks( esc_html( $it['q'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
									<span class="ap-page-faq-hub__icon" aria-hidden="true"></span>
								</summary>
								<div class="ap-page-faq-hub__a"><?php echo $ap_answer( $it['a'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
							</details>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<p class="ap-page-faq-hub__empty" data-ap-faq-empty hidden><?php echo $ap_inline( (string) $props['empty_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
		</div>
	</div>
	<?php
	if ( ! empty( $props['schema'] ) ) {
		$entities = array();
		foreach ( $groups as $g ) {
			foreach ( $g['items'] as $it ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $it['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $ap_plain( $it['a'] ),
					),
				);
			}
		}
		$graph = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG encodes < and >, so </script> cannot form. ?></script>
		<?php
	}
	?>
</section>
