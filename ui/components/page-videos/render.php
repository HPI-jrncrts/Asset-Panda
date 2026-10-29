<?php
/**
 * page-videos — a large player and a deck of video cards (/tutorials/).
 *
 * The player takes most of the dark theater panel (16:9) with the chosen
 * video's title and copy under it. Every other video is its own card in a deck
 * beside it — the next one in front with its poster, the rest stacked behind
 * it, each showing its title strip — and on tablets and phones the deck is a
 * horizontal row under the player. Order is "up next" order: when a video
 * ends (or a card is clicked) behaviour.js flies that card's poster into the
 * player, tucks the finished video's card into the back of the deck and slides
 * the rest up one place; the deck loops, so after the last video comes the
 * first. The deck's order is a CSS custom property per card (--slot), set here
 * for the first video and by behaviour.js after each swap — the DOM order of
 * the cards never changes, so screen readers and the numbering stay stable.
 *
 * Autoplay (behaviour.js): when the player scrolls into view the first video
 * starts muted (browsers block sound without a click) through the YouTube
 * IFrame API on youtube-nocookie.com, and each video's end advances the deck.
 * Every video's caption is in the markup (the inactive ones carry `hidden`).
 * Without JavaScript the first video is a poster linking to YouTube and every
 * title is visible in the deck.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$protocols = array( 'http', 'https', 'mailto', 'tel' );

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$video = trim( (string) ( $item['video'] ?? '' ) );
	$id    = '';
	if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?(?:.*&)?v=|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $video, $m ) ) {
		$id = $m[1];
	}
	$list = '';
	if ( preg_match( '#[?&]list=([A-Za-z0-9_-]{10,64})#', $video, $lm ) ) {
		$list = $lm[1];
	}
	$items[] = array(
		'title'    => trim( (string) $item['title'] ),
		'id'       => $id,
		'list'     => $list,
		'poster'   => trim( (string) ( $item['poster'] ?? '' ) ),
		'poster_w' => (int) ( $item['poster_w'] ?? 0 ),
		'poster_h' => (int) ( $item['poster_h'] ?? 0 ),
		'body'     => trim( (string) ( $item['body'] ?? '' ) ),
		'label'    => trim( (string) ( $item['play_label'] ?? '' ) ),
	);
}
if ( empty( $items ) ) {
	return;
}

$anchor         = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$tone           = 'soft' === $props['tone'] ? 'soft' : 'white';
$card_tag       = 'h2' === $props['card_tag'] ? 'h2' : 'h3';
$eyebrow        = trim( (string) $props['eyebrow'] );
$title          = trim( (string) $props['title'] );
$intro          = trim( (string) $props['intro'] );
$playlist_title = trim( (string) $props['playlist_title'] );
$count          = count( $items );
$first          = $items[0];
$first_label    = '' !== $first['label'] ? $first['label'] : 'Play video: ' . $first['title'];
$first_href     = '' !== $first['id'] ? 'https://www.youtube.com/watch?v=' . $first['id'] . ( '' !== $first['list'] ? '&list=' . $first['list'] : '' ) : '';

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
			$out .= '' !== $url ? '<a href="' . $url . '">' . esc_html( $hit[1][0] ) . '</a>' : esc_html( $hit[1][0] );
			$pos  = $hit[0][1] + strlen( $hit[0][0] );
		}
	}
	return $out . $ap_text( substr( $text, $pos ) );
};
$ap_blocks = static function ( $text ) use ( $ap_inline ) {
	$html = '';
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $block ) {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', trim( $block ) ) ), 'strlen' ) );
		if ( ! $lines ) {
			continue;
		}
		if ( count( $lines ) === count( preg_grep( '/^- /', $lines ) ) ) {
			$html .= '<ul>';
			foreach ( $lines as $line ) {
				$html .= '<li>' . $ap_inline( substr( $line, 2 ) ) . '</li>';
			}
			$html .= '</ul>';
		} else {
			$html .= '<p>' . implode( '<br>', array_map( $ap_inline, $lines ) ) . '</p>';
		}
	}
	return $html;
};
$ap_poster_size = static function ( $item ) {
	return $item['poster_w'] > 0 && $item['poster_h'] > 0 ? ' width="' . esc_attr( (string) $item['poster_w'] ) . '" height="' . esc_attr( (string) $item['poster_h'] ) . '"' : '';
};

$play  = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M8 5.6v12.8c0 .8.9 1.3 1.6.9l10-6.4c.6-.4.6-1.4 0-1.8l-10-6.4C8.9 4.3 8 4.8 8 5.6Z" fill="currentColor"></path></svg>';
$muted = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M4 9.5v5c0 .6.4 1 1 1h2.6l4 3.4c.7.5 1.6.1 1.6-.8V5.9c0-.9-.9-1.3-1.6-.8l-4 3.4H5c-.6 0-1 .4-1 1Z" fill="currentColor"></path><path d="m16.5 9.5 5 5m0-5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>';
?>
<section class="ap-page-videos ap-page-videos--<?php echo esc_attr( $tone ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-page-videos__wrap">
		<?php if ( '' !== $title || '' !== $eyebrow || '' !== $intro ) : ?>
			<div class="ap-page-videos__head" data-ap-reveal>
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-page-videos__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h2 class="ap-page-videos__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $intro ) : ?>
					<div class="ap-page-videos__intro"><?php echo $ap_blocks( $intro ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="ap-page-videos__theater<?php echo 1 === $count ? ' ap-page-videos__theater--single' : ''; ?>" data-ap-pv-gallery data-ap-reveal>
			<div class="ap-page-videos__screen" data-ap-pv-screen>
				<?php if ( '' !== $first_href ) : ?>
					<a class="ap-page-videos__play" href="<?php echo esc_url( $first_href ); ?>" target="_blank" rel="noopener" data-ap-pv="<?php echo esc_attr( $first['id'] ); ?>"<?php echo '' !== $first['list'] ? ' data-ap-pv-list="' . esc_attr( $first['list'] ) . '"' : ''; ?> data-ap-pv-title="<?php echo esc_attr( $first['title'] ); ?>" aria-label="<?php echo esc_attr( $first_label ); ?>">
						<?php if ( '' !== $first['poster'] ) : ?>
							<img class="ap-page-videos__poster" src="<?php echo esc_url( $first['poster'] ); ?>" alt=""<?php echo $ap_poster_size( $first ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> decoding="async" />
						<?php endif; ?>
						<span class="ap-page-videos__btn" aria-hidden="true"><?php echo $play; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
					</a>
				<?php elseif ( '' !== $first['poster'] ) : ?>
					<div class="ap-page-videos__cover" data-ap-pv-cover>
						<img class="ap-page-videos__poster" src="<?php echo esc_url( $first['poster'] ); ?>" alt=""<?php echo $ap_poster_size( $first ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> decoding="async" />
					</div>
				<?php endif; ?>
				<button type="button" class="ap-page-videos__sound" data-ap-pv-sound hidden><?php echo $muted; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span>Unmute</span></button>
			</div>
			<div class="ap-page-videos__captions">
				<?php foreach ( $items as $i => $item ) : ?>
					<div class="ap-page-videos__caption" data-ap-pv-caption="<?php echo esc_attr( (string) $i ); ?>"<?php echo 0 !== $i ? ' hidden' : ''; ?>>
						<p class="ap-page-videos__count">Video <?php echo esc_html( (string) ( $i + 1 ) ); ?> of <?php echo esc_html( (string) $count ); ?></p>
						<<?php echo tag_escape( $card_tag ); ?> class="ap-page-videos__name"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></<?php echo tag_escape( $card_tag ); ?>>
						<?php if ( '' !== $item['body'] ) : ?>
							<div class="ap-page-videos__body"><?php echo $ap_blocks( $item['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="ap-page-videos__status" role="status" aria-live="polite" data-ap-pv-status></p>
			<?php if ( $count > 1 ) : ?>
				<div class="ap-page-videos__deck">
					<div class="ap-page-videos__deck-head">
						<p class="ap-page-videos__deck-title"><?php echo esc_html( '' !== $playlist_title ? $playlist_title : 'Playlist' ); ?></p>
						<p class="ap-page-videos__deck-count"><?php echo esc_html( (string) $count ); ?> videos</p>
					</div>
					<ol class="ap-page-videos__cards" aria-label="Video playlist" data-ap-pv-cards>
						<?php
						foreach ( $items as $i => $item ) :
							// Up-next order: the first video is in the player, the second is the front card.
							$slot = 0 === $i ? $count - 1 : $i - 1;
							?>
							<li class="ap-page-videos__slot<?php echo 0 === $i ? ' is-active' : ( 1 === $i ? ' is-next' : '' ); ?>" style="--slot:<?php echo esc_attr( (string) $slot ); ?>" data-ap-pv-slot>
								<button type="button" class="ap-page-videos__card" data-ap-pv-card="<?php echo esc_attr( (string) $i ); ?>" data-ap-pv-id="<?php echo esc_attr( $item['id'] ); ?>"<?php echo '' !== $item['list'] ? ' data-ap-pv-list="' . esc_attr( $item['list'] ) . '"' : ''; ?> data-ap-pv-title="<?php echo esc_attr( $item['title'] ); ?>"<?php echo 0 === $i ? ' aria-current="true" tabindex="-1"' : ''; ?>>
									<span class="ap-page-videos__thumb" data-ap-pv-thumb>
										<?php if ( '' !== $item['poster'] ) : ?>
											<img src="<?php echo esc_url( $item['poster'] ); ?>" alt=""<?php echo $ap_poster_size( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?> loading="lazy" decoding="async" />
										<?php endif; ?>
										<span class="ap-page-videos__thumb-play" aria-hidden="true"><?php echo $play; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
										<span class="ap-page-videos__next">Up next</span>
									</span>
									<span class="ap-page-videos__card-text">
										<span class="ap-page-videos__index" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
										<span class="ap-page-videos__card-title"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></span>
									</span>
								</button>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
