<?php
/**
 * page-directory — logo-card directory with category chips and instant search.
 *
 * Live /integrations/ hid every description behind a click-to-expand gridder;
 * here each card shows its logo, name, category pills and description at
 * once, and the chips (built from the cards' tags, in first-seen order, each
 * with its count) and the search box narrow the grid as you type. The same
 * card carries the /compare/ guides (competitor logo, "Asset Panda vs X",
 * summary, arrow link) — a card with a url is one big link (the heading's
 * link stretches over the card; the arrow is decoration, not a second link).
 *
 * Without behaviour.js every card shows and the chips/search do nothing.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
$tags  = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['name'] ?? '' ) ) ) {
		continue;
	}
	$item_tags = array();
	foreach ( is_array( $item['tags'] ?? null ) ? $item['tags'] : array() as $t ) {
		if ( is_string( $t ) && '' !== trim( $t ) ) {
			$item_tags[] = trim( $t );
			$tags[ trim( $t ) ] = ( $tags[ trim( $t ) ] ?? 0 ) + 1;
		}
	}
	// logo_crop [x, y, w, h]: the logo's own box inside a padded canvas (live's 400x300 PNG logos), so it is
	// drawn at full size instead of a thumbnail lost in white space. Needs logo_w and logo_h.
	$crop = '';
	$lw   = (int) ( $item['logo_w'] ?? 0 );
	$lh   = (int) ( $item['logo_h'] ?? 0 );
	if ( $lw > 0 && $lh > 0 && is_array( $item['logo_crop'] ?? null ) && 4 === count( $item['logo_crop'] ) ) {
		list( $cx, $cy, $cw, $ch ) = array_map( 'intval', array_values( $item['logo_crop'] ) );
		if ( $cw > 0 && $ch > 0 && $cx >= 0 && $cy >= 0 && $cx + $cw <= $lw && $cy + $ch <= $lh ) {
			$crop = sprintf( '--lw:%d;--lh:%d;--cx:%d;--cy:%d;--cw:%d;--ch:%d', $lw, $lh, $cx, $cy, $cw, $ch );
		}
	}
	$items[] = array(
		'name'       => trim( (string) $item['name'] ),
		'logo'       => trim( (string) ( $item['logo'] ?? '' ) ),
		'logo_alt'   => (string) ( $item['logo_alt'] ?? '' ),
		'logo_w'     => $lw,
		'logo_h'     => $lh,
		'crop'       => $crop,
		'tags'       => $item_tags,
		'body'       => trim( (string) ( $item['body'] ?? '' ) ),
		'url'        => trim( (string) ( $item['url'] ?? '' ) ),
		'link_label' => trim( (string) ( $item['link_label'] ?? '' ) ),
		'badge'      => trim( (string) ( $item['badge'] ?? '' ) ),
	);
}
if ( empty( $items ) ) {
	return;
}
// Chips: biggest categories first; ties keep first-seen order (uasort is stable from PHP 8).
uasort(
	$tags,
	static function ( $a, $b ) {
		return $b <=> $a;
	}
);

$anchor     = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$tone       = 'soft' === $props['tone'] ? 'soft' : 'white';
$columns    = '3' === $props['columns'] ? '3' : '4';
$eyebrow    = trim( (string) $props['eyebrow'] );
$lede       = trim( (string) $props['lede'] );
$filter     = ! empty( $props['filter'] ) && count( $tags ) > 1;
$search     = ! empty( $props['search'] );
$count_tpl  = trim( (string) $props['count_label'] );
$def_link   = trim( (string) $props['link_label'] );
$soon_title = trim( (string) $props['soon_title'] );
$soon_image = trim( (string) $props['soon_image'] );
$foot_text  = trim( (string) $props['foot_text'] );
$foot_label = trim( (string) $props['foot_label'] );
$foot_url   = trim( (string) $props['foot_url'] );
$protocols  = array( 'http', 'https', 'mailto', 'tel' );

// A static counter would reset on every render (each one runs in a fresh closure), so ids come from
// WordPress's request-wide counter, or from the content when it is missing.
$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-dir-' ) : 'ap-dir-' . substr( md5( (string) $props['title'] . count( $items ) ), 0, 8 );

$ap_text = static function ( $text ) {
	$html = nl2br( esc_html( $text ), false );
	$bold = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	return ap_ui_brandmarks( is_string( $bold ) ? $bold : $html );
};
$ap_rich = static function ( $text ) use ( $ap_text, $protocols ) {
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
$ap_lower = static function ( $s ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
};

$arrow  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
$search_svg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
?>
<section class="ap-page-directory ap-page-directory--<?php echo esc_attr( $tone ); ?> ap-page-directory--cols-<?php echo esc_attr( $columns ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?> data-ap-directory>
	<?php if ( 'soft' === $tone ) : ?>
		<div class="ap-page-directory__wash" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="ap-page-directory__wrap">
		<div class="ap-page-directory__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-page-directory__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-page-directory__title" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-page-directory__lede"><?php echo $ap_rich( $lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $filter || $search ) : ?>
			<div class="ap-page-directory__controls">
				<?php if ( $filter ) : ?>
					<div class="ap-page-directory__chips" role="group" aria-label="<?php echo esc_attr( (string) $props['filter_label'] ); ?>">
						<button type="button" class="ap-page-directory__chip" data-ap-filter="*" aria-pressed="true"><?php echo esc_html( (string) $props['all_label'] ); ?><span class="ap-page-directory__chip-n"><?php echo esc_html( (string) count( $items ) ); ?></span></button>
						<?php foreach ( $tags as $t => $n ) : ?>
							<button type="button" class="ap-page-directory__chip" data-ap-filter="<?php echo esc_attr( $t ); ?>" aria-pressed="false"><?php echo esc_html( $t ); ?><span class="ap-page-directory__chip-n"><?php echo esc_html( (string) $n ); ?></span></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $search ) : ?>
					<div class="ap-page-directory__search">
						<label class="ap-page-directory__sr" for="<?php echo esc_attr( $uid ); ?>-q"><?php echo esc_html( (string) $props['search_label'] ); ?></label>
						<?php echo $search_svg; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
						<input type="search" id="<?php echo esc_attr( $uid ); ?>-q" placeholder="<?php echo esc_attr( (string) $props['search_label'] ); ?>" autocomplete="off" spellcheck="false" data-ap-search />
					</div>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $count_tpl ) : ?>
				<p class="ap-page-directory__count" aria-live="polite" data-ap-count="<?php echo esc_attr( $count_tpl ); ?>"><?php echo esc_html( str_replace( '{n}', (string) count( $items ), $count_tpl ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<ul class="ap-page-directory__grid" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$url        = '' !== $item['url'] ? esc_url( $item['url'], $protocols ) : '';
				$link_label = '' !== $item['link_label'] ? $item['link_label'] : $def_link;
				$haystack   = $ap_lower( $item['name'] . ' ' . implode( ' ', $item['tags'] ) . ' ' . $item['body'] );
				?>
				<li class="ap-page-directory__card<?php echo '' !== $url ? ' ap-page-directory__card--link' : ''; ?>" data-ap-card data-tags="<?php echo esc_attr( implode( '|', $item['tags'] ) ); ?>" data-text="<?php echo esc_attr( $haystack ); ?>">
					<div class="ap-page-directory__logo">
						<?php if ( '' !== $item['logo'] && '' !== $item['crop'] ) : ?>
							<span class="ap-page-directory__crop" style="<?php echo esc_attr( $item['crop'] ); ?>"><img src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ); ?>" width="<?php echo esc_attr( (string) $item['logo_w'] ); ?>" height="<?php echo esc_attr( (string) $item['logo_h'] ); ?>" loading="lazy" decoding="async" /></span>
						<?php elseif ( '' !== $item['logo'] ) : ?>
							<img src="<?php echo esc_url( $item['logo'] ); ?>" alt="<?php echo esc_attr( $item['logo_alt'] ); ?>"<?php echo $item['logo_w'] > 0 && $item['logo_h'] > 0 ? ' width="' . esc_attr( (string) $item['logo_w'] ) . '" height="' . esc_attr( (string) $item['logo_h'] ) . '"' : ''; ?> loading="lazy" decoding="async" />
						<?php endif; ?>
					</div>
					<div class="ap-page-directory__text">
						<h3 class="ap-page-directory__name">
							<?php if ( '' !== $url ) : ?>
								<a class="ap-page-directory__stretch" href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>"><?php echo ap_ui_brandmarks( esc_html( $item['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></a>
							<?php else : ?>
								<?php echo ap_ui_brandmarks( esc_html( $item['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?>
							<?php endif; ?>
						</h3>
						<?php if ( $item['tags'] ) : ?>
							<ul class="ap-page-directory__tags">
								<?php foreach ( $item['tags'] as $t ) : ?>
									<li><?php echo esc_html( $t ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( '' !== $item['body'] ) : ?>
							<p class="ap-page-directory__body"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
						<?php endif; ?>
						<?php if ( '' !== $url && '' !== $link_label ) : ?>
							<span class="ap-page-directory__more" aria-hidden="true"><?php echo esc_html( $link_label ); ?><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
						<?php endif; ?>
					</div>
					<?php if ( '' !== $item['badge'] ) : ?>
						<span class="ap-page-directory__badge"><?php echo esc_html( $item['badge'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			<?php if ( '' !== $soon_title ) : ?>
				<li class="ap-page-directory__card ap-page-directory__card--soon" data-ap-soon>
					<?php if ( '' !== $soon_image ) : ?>
						<img src="<?php echo esc_url( $soon_image ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async" />
					<?php endif; ?>
					<p class="ap-page-directory__soon"><?php echo esc_html( $soon_title ); ?></p>
				</li>
			<?php endif; ?>
		</ul>
		<p class="ap-page-directory__empty" data-ap-empty hidden><?php echo esc_html( (string) $props['empty_text'] ); ?></p>

		<?php if ( '' !== $foot_text || ( '' !== $foot_label && '' !== $foot_url ) ) : ?>
			<div class="ap-page-directory__foot">
				<?php if ( '' !== $foot_text ) : ?>
					<p><?php echo $ap_rich( $foot_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
				<?php endif; ?>
				<?php if ( '' !== $foot_label && '' !== $foot_url ) : ?>
					<a class="ap-page-directory__btn" href="<?php echo esc_url( $foot_url, $protocols ); ?>"><?php echo esc_html( $foot_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
