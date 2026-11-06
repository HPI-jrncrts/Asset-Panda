<?php
/**
 * page-timeline — the "Our Journey" section of the 2026 company pages (Our Company, Figma 7385:186215).
 *
 * The intro column stays in view (sticky) while the milestones scroll beside it on desktop. The rule colour
 * steps from soft to deep down the list: first milestone indigo-200, last indigo-600, any between indigo-400.
 * Body copy takes the same light markup as the other page-* components: a blank line starts a paragraph,
 * [label](url) makes a link and **double asterisks** bold.
 *
 * look "steps" (Our Technology, Figma 7460:109053 "How it works"): the year label becomes a large thin step
 * number beside the heading (aria-hidden: the ordered list already numbers the steps) and the rules cycle the
 * brand colours; cta_label / cta_url add a gradient button under the intro.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protocols = array( 'http', 'https', 'mailto', 'tel' );

$ap_text  = static function ( $text ) {
	$html = nl2br( esc_html( $text ), false );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};
$ap_rich  = static function ( $text ) use ( $ap_text, $protocols ) {
	$text = trim( (string) $text );
	$out  = '';
	$pos  = 0;
	if ( preg_match_all( '/\[([^\]\n]+)\]\(([^)\s]+)\)/', $text, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
		foreach ( $hits as $hit ) {
			$out .= $ap_text( substr( $text, $pos, $hit[0][1] - $pos ) );
			$url  = esc_url( $hit[2][0], $protocols );
			$out .= '' !== $url ? '<a href="' . $url . '">' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};
$ap_paras = static function ( $text ) use ( $ap_rich ) {
	$out = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $para ) {
		if ( '' !== trim( $para ) ) {
			$out .= '<p>' . $ap_rich( trim( $para ) ) . '</p>';
		}
	}
	return $out;
};

$title = trim( (string) $props['title'] );
$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$links = array();
	foreach ( is_array( $item['links'] ?? null ) ? $item['links'] : array() as $link ) {
		if ( is_array( $link ) && '' !== trim( (string) ( $link['label'] ?? '' ) ) && '' !== trim( (string) ( $link['url'] ?? '' ) ) ) {
			$links[] = array(
				'label' => trim( (string) $link['label'] ),
				'url'   => trim( (string) $link['url'] ),
			);
		}
	}
	$entry = array(
		'year'  => trim( (string) ( $item['year'] ?? '' ) ),
		'title' => trim( (string) ( $item['title'] ?? '' ) ),
		'body'  => trim( (string) ( $item['body'] ?? '' ) ),
		'note'  => trim( (string) ( $item['note'] ?? '' ) ),
		'links' => $links,
	);
	if ( '' !== $entry['title'] || '' !== $entry['body'] ) {
		$items[] = $entry;
	}
}
if ( '' === $title || ! $items ) {
	return;
}

$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$accent = trim( (string) $props['title_accent'] );

$title_html = esc_html( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr( $title_html, 0, $at ) . '<span class="ap-page-timeline__accent">' . $needle . '</span>' . substr( $title_html, $at + strlen( $needle ) );
	}
}
$title_html = ap_ui_brandmarks( nl2br( $title_html, false ) );

$closing_lead = trim( (string) $props['closing_lead'] );
$closing_text = trim( (string) $props['closing_text'] );
$last         = count( $items ) - 1;
$look         = 'steps' === $props['look'] ? 'steps' : 'journey';
$cta_label    = trim( (string) $props['cta_label'] );
$cta_url      = trim( (string) $props['cta_url'] );
$arrow        = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
?>
<section class="ap-page-timeline ap-page-timeline--<?php echo esc_attr( $look ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-timeline__wrap">
		<div class="ap-page-timeline__intro">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-page-timeline__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="ap-page-timeline__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></h2>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<div class="ap-page-timeline__lede"><?php echo $ap_paras( $props['lede'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
			<?php endif; ?>
			<?php if ( '' !== $cta_label && '' !== esc_url( $cta_url, $protocols ) ) : ?>
				<p class="ap-page-timeline__cta"><a class="ap-page-timeline__btn" href="<?php echo esc_url( $cta_url, $protocols ); ?>"><?php echo esc_html( $cta_label ); ?></a></p>
			<?php endif; ?>
		</div>
		<ol class="ap-page-timeline__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<?php $tone = 0 === $i ? 'soft' : ( $last === $i ? 'deep' : 'mid' ); ?>
				<li class="ap-page-timeline__item ap-page-timeline__item--<?php echo esc_attr( $tone ); ?>" data-ap-reveal>
					<?php if ( 'steps' === $look && '' !== $item['year'] && '' !== $item['title'] ) : ?>
						<div class="ap-page-timeline__step">
							<span class="ap-page-timeline__num" aria-hidden="true"><?php echo esc_html( $item['year'] ); ?></span>
							<h3 class="ap-page-timeline__item-title"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						</div>
					<?php else : ?>
						<?php if ( '' !== $item['year'] ) : ?>
							<p class="ap-page-timeline__year"><?php echo esc_html( $item['year'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $item['title'] ) : ?>
							<h3 class="ap-page-timeline__item-title"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( '' !== $item['body'] ) : ?>
						<div class="ap-page-timeline__body"><?php echo $ap_paras( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></div>
					<?php endif; ?>
					<?php if ( '' !== $item['note'] ) : ?>
						<p class="ap-page-timeline__note"><?php echo $ap_rich( $item['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helpers. ?></p>
					<?php endif; ?>
					<?php if ( $item['links'] ) : ?>
						<ul class="ap-page-timeline__links">
							<?php foreach ( $item['links'] as $link ) : ?>
								<li><a href="<?php echo esc_url( $link['url'], $protocols ); ?>"><span><?php echo ap_ui_brandmarks( esc_html( $link['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $last === $i && ( '' !== $closing_lead || '' !== $closing_text ) ) : ?>
						<div class="ap-page-timeline__closing">
							<?php if ( '' !== $closing_lead ) : ?>
								<p class="ap-page-timeline__closing-lead"><?php echo esc_html( $closing_lead ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $closing_text ) : ?>
								<p class="ap-page-timeline__closing-text"><?php echo ap_ui_brandmarks( esc_html( $closing_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
