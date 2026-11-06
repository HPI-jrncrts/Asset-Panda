<?php
/**
 * integration-directory — the Integrations hub directory.
 *
 * Figma: Desktop - Integrations > Features Section (7310:158798). A section
 * head (eyebrow, H2 with a gradient slice, lede) over two columns: the
 * Categories radio list (239px) and the main column — search field, a
 * three-column grid of logo cards and the Previous / 1 2 3 … / Next pager.
 *
 * Every card is rendered here, visible, so without JS the whole directory is
 * readable and every card reachable. behaviour.js then pages the grid (12 per
 * page), filters it by category and search, announces the result count in a
 * visually hidden live region, shows the empty state and reveals the pager,
 * which is rendered hidden because it means nothing without the script.
 *
 * A card is one link: the name's anchor stretches over the whole card, and the
 * "Learn More" row is decoration (aria-hidden), so each card is one tab stop.
 * Logos are decorative (alt="") — the name says the same thing in text. With
 * icon_w/icon_h the logo box is sized here: square-ish marks draw 64px tall,
 * wide wordmarks 44px tall and at most 150px wide; icon_crop shows only the
 * mark inside a padded canvas (live's 400x300 PNG logos).
 *
 * The section sits below the hub hero, so it carries no data-ap-reveal; the
 * shared motion layer brings it in on scroll.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$protocols = array( 'http', 'https', 'mailto', 'tel' );
$lower     = static function ( $s ) {
	$s = trim( (string) $s );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
};
$key_of    = static function ( $s ) use ( $lower ) {
	return trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', $lower( $s ) ), '-' );
};

/*
 * Logo box: a mark at most 150 wide and 64 tall (44 tall once it is 1.6x wider
 * than high, so wordmarks sit with the square icons instead of shouting).
 * Returns [display width, display height] in px, or null without a size.
 */
$fit = static function ( $w, $h ) {
	if ( $w <= 0 || $h <= 0 ) {
		return null;
	}
	$max_h = ( $w / $h ) >= 1.6 ? 44 : 64;
	$s     = min( 150 / $w, $max_h / $h );
	return array( max( 1, (int) round( $w * $s ) ), max( 1, (int) round( $h * $s ) ) );
};

// Opt-in Compare hub layouts: the given size is the logo's display size (Figma's), shrunk to fit 180 x 64, never grown.
$layout  = in_array( $props['layout'], array( 'compare', 'compare-band' ), true ) ? $props['layout'] : 'default';
$compare = 'default' !== $layout;
if ( $compare ) {
	$fit = static function ( $w, $h ) {
		if ( $w <= 0 || $h <= 0 ) {
			return null;
		}
		$s = min( 1, 180 / $w, 64 / $h );
		return array( max( 1, (int) round( $w * $s ) ), max( 1, (int) round( $h * $s ) ) );
	};
}

// ---- items ----
$items = array();
$used  = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) ) {
		continue;
	}
	$name = trim( (string) ( $item['name'] ?? '' ) );
	if ( '' === $name ) {
		continue;
	}
	$groups = array();
	$raw    = $item['group'] ?? '';
	foreach ( is_array( $raw ) ? $raw : explode( ',', (string) $raw ) as $g ) {
		$g = $key_of( $g );
		if ( '' !== $g && 'all' !== $g ) {
			$groups[ $g ] = true;
			$used[ $g ]   = true;
		}
	}

	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	$iw   = (float) ( $item['icon_w'] ?? 0 );
	$ih   = (float) ( $item['icon_h'] ?? 0 );
	$logo = null;
	if ( '' !== $icon ) {
		$logo = array(
			'src'  => $icon,
			'w'    => $iw > 0 ? (int) round( $iw ) : 64,
			'h'    => $ih > 0 ? (int) round( $ih ) : 64,
			'box'  => $fit( $iw, $ih ),
			'crop' => null,
		);
		$crop = $item['icon_crop'] ?? null;
		if ( null !== $logo['box'] && is_array( $crop ) && 4 === count( $crop ) ) {
			list( $cx, $cy, $cw, $ch ) = array_map( 'floatval', array_values( $crop ) );
			if ( $cw > 0 && $ch > 0 && $cx >= 0 && $cy >= 0 && $cx + $cw <= $iw + 0.5 && $cy + $ch <= $ih + 0.5 ) {
				$logo['box']  = $fit( $cw, $ch );
				$logo['crop'] = sprintf(
					'--ix:%s;--iy:%s;--iw:%s;--ih:%s',
					round( -100 * $cx / $cw, 3 ),
					round( -100 * $cy / $ch, 3 ),
					round( 100 * $iw / $cw, 3 ),
					round( 100 * $ih / $ch, 3 )
				);
			}
		}
	}

	$items[] = array(
		'name'   => $name,
		'url'    => esc_url( trim( (string) ( $item['url'] ?? '' ) ), $protocols ),
		'logo'   => $logo,
		'tag'    => trim( (string) ( $item['tag'] ?? '' ) ),
		'desc'   => trim( (string) ( $item['description'] ?? '' ) ),
		'groups' => array_keys( $groups ),
	);
}
if ( empty( $items ) ) {
	return;
}

// ---- categories: 'all' first (added when missing), then the ones some card belongs to ----
$cats    = array();
$has_all = false;
foreach ( is_array( $props['categories'] ) ? $props['categories'] : array() as $cat ) {
	if ( ! is_array( $cat ) ) {
		continue;
	}
	$key   = $key_of( $cat['key'] ?? '' );
	$label = trim( (string) ( $cat['label'] ?? '' ) );
	if ( '' === $key || '' === $label || isset( $cats[ $key ] ) ) {
		continue;
	}
	if ( 'all' === $key ) {
		$has_all = true;
	} elseif ( empty( $used[ $key ] ) ) {
		continue;
	}
	$cats[ $key ] = $label;
}
if ( count( $cats ) > 0 && ! $has_all ) {
	$cats = array( 'all' => 'All' ) + $cats;
}
if ( count( $cats ) < 2 ) {
	$cats = array();
} elseif ( isset( $cats['all'] ) ) {
	$cats = array( 'all' => $cats['all'] ) + $cats;
}

// ---- section copy and settings ----
$anchor      = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$eyebrow     = trim( (string) $props['eyebrow'] );
$accent      = trim( (string) $props['title_accent'] );
$lede        = trim( (string) $props['lede'] );
$placeholder = trim( (string) $props['search_placeholder'] );
$search_name = trim( (string) $props['search_label'] );
$link_label  = trim( (string) $props['link_label'] );
$per_page    = max( 0, min( 60, (int) $props['per_page'] ) );
$empty_label = trim( (string) $props['empty_link_label'] );
$empty_url   = '' !== $empty_label ? esc_url( trim( (string) $props['empty_link_url'] ), $protocols ) : '';
$reset_label = trim( (string) $props['reset_label'] );

// A static counter would restart in every render closure, so ids come from WordPress's request-wide counter.
$uid = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'ap-intdir-' ) : 'ap-intdir-' . substr( md5( $title . count( $items ) ), 0, 8 );

// Heading: escape first, then wrap the first occurrence of the gradient slice.
$title_html = ap_ui_lines( $title );
if ( '' !== $accent ) {
	$needle = esc_html( $accent );
	$at     = strpos( $title_html, $needle );
	if ( false !== $at ) {
		$title_html = substr_replace( $title_html, '<span class="ap-integration-directory__accent">' . $needle . '</span>', $at, strlen( $needle ) );
	}
}

$classes = array( 'ap-integration-directory' );
if ( empty( $cats ) ) {
	$classes[] = 'ap-integration-directory--no-filter';
}
if ( $compare ) {
	$classes[] = 'ap-integration-directory--compare';
	if ( 'compare-band' === $layout ) {
		$classes[] = 'ap-integration-directory--compare-band';
	}
}

// The search field: under the head in the main column (default), or beside the head (compare layouts).
$search_html = '';
if ( '' !== $placeholder ) {
	$search_html = '<div class="ap-integration-directory__search">'
		. '<label class="ap-integration-directory__sr" for="' . esc_attr( $uid ) . '-q">' . esc_html( '' !== $search_name ? $search_name : $placeholder ) . '</label>'
		. '<input class="ap-integration-directory__input" type="search" id="' . esc_attr( $uid ) . '-q" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" aria-controls="' . esc_attr( $uid ) . '-grid" data-ap-intdir-search />'
		. '</div>';
}
?>
<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?> aria-labelledby="<?php echo esc_attr( $uid ); ?>-title"
	data-ap-intdir
	data-ap-intdir-per-page="<?php echo esc_attr( (string) $per_page ); ?>"
	data-ap-intdir-url="<?php echo ! empty( $props['url_sync'] ) ? '1' : '0'; ?>"
	data-ap-intdir-status-text="<?php echo esc_attr( (string) $props['status_text'] ); ?>"
	data-ap-intdir-page-text="<?php echo esc_attr( (string) $props['page_label'] ); ?>">
	<div class="ap-integration-directory__inner">
		<?php echo $compare ? '<div class="ap-integration-directory__top">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. Same line as the head so the default layout's bytes stay as they were. ?><div class="ap-integration-directory__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-integration-directory__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-integration-directory__title" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(); only a fixed span is added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-integration-directory__lede"><?php echo ap_ui_lines( $lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
			<?php endif; ?>
		</div>

		<?php echo $compare ? $search_html . '</div>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr() / esc_html() above; closes __top. ?><div class="ap-integration-directory__body">
			<?php if ( ! empty( $cats ) ) : ?>
				<fieldset class="ap-integration-directory__filter">
					<legend class="ap-integration-directory__legend"><?php echo esc_html( (string) $props['categories_label'] ); ?></legend>
					<div class="ap-integration-directory__cats" data-ap-intdir-cats>
						<?php $first = true; ?>
						<?php foreach ( $cats as $key => $label ) : ?>
							<label class="ap-integration-directory__cat">
								<input class="ap-integration-directory__radio" type="radio" name="<?php echo esc_attr( $uid ); ?>-cat" value="<?php echo esc_attr( $key ); ?>" data-ap-intdir-cat<?php echo $first ? ' checked' : ''; ?> />
								<span class="ap-integration-directory__cat-icon" aria-hidden="true"></span>
								<span class="ap-integration-directory__cat-text"><?php echo esc_html( $label ); ?></span>
							</label>
							<?php $first = false; ?>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endif; ?>

			<div class="ap-integration-directory__main" data-ap-intdir-main>
				<?php if ( '' !== $placeholder && ! $compare ) : ?>
					<div class="ap-integration-directory__search">
						<label class="ap-integration-directory__sr" for="<?php echo esc_attr( $uid ); ?>-q"><?php echo esc_html( '' !== $search_name ? $search_name : $placeholder ); ?></label>
						<input class="ap-integration-directory__input" type="search" id="<?php echo esc_attr( $uid ); ?>-q" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" aria-controls="<?php echo esc_attr( $uid ); ?>-grid" data-ap-intdir-search />
					</div>
				<?php endif; ?>

				<p class="ap-integration-directory__sr" role="status" aria-live="polite" aria-atomic="true" data-ap-intdir-status></p>

				<ul class="ap-integration-directory__grid" id="<?php echo esc_attr( $uid ); ?>-grid" role="list" tabindex="-1" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title" data-ap-intdir-grid>
					<?php foreach ( $items as $item ) : ?>
						<li class="ap-integration-directory__card<?php echo '' !== $item['url'] ? ' ap-integration-directory__card--link' : ''; ?>" data-ap-intdir-card data-group="<?php echo esc_attr( implode( ' ', $item['groups'] ) ); ?>">
							<?php if ( null !== $item['logo'] ) : ?>
								<?php $logo = $item['logo']; ?>
								<div class="ap-integration-directory__logo">
									<?php if ( null !== $logo['crop'] ) : ?>
										<span class="ap-integration-directory__crop" style="<?php echo esc_attr( sprintf( '--w:%d;--h:%d;', $logo['box'][0], $logo['box'][1] ) . $logo['crop'] ); ?>"><img src="<?php echo esc_url( $logo['src'] ); ?>" alt="" width="<?php echo esc_attr( (string) $logo['w'] ); ?>" height="<?php echo esc_attr( (string) $logo['h'] ); ?>" loading="lazy" decoding="async" /></span>
									<?php elseif ( null !== $logo['box'] ) : ?>
										<img class="ap-integration-directory__mark" src="<?php echo esc_url( $logo['src'] ); ?>" alt="" width="<?php echo esc_attr( (string) $logo['box'][0] ); ?>" height="<?php echo esc_attr( (string) $logo['box'][1] ); ?>" style="<?php echo esc_attr( sprintf( '--w:%d;--h:%d', $logo['box'][0], $logo['box'][1] ) ); ?>" loading="lazy" decoding="async" />
									<?php else : ?>
										<img class="ap-integration-directory__mark ap-integration-directory__mark--auto" src="<?php echo esc_url( $logo['src'] ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async" />
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="ap-integration-directory__text">
								<h3 class="ap-integration-directory__name" data-ap-intdir-text>
									<?php if ( '' !== $item['url'] ) : ?>
										<a class="ap-integration-directory__link" href="<?php echo $item['url']; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() above. ?>"><?php echo $compare ? ap_ui_lines( $item['name'] ) : esc_html( $item['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped (ap_ui_lines() adds only <br>). ?></a>
									<?php else : ?>
										<?php echo $compare ? ap_ui_lines( $item['name'] ) : esc_html( $item['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped (ap_ui_lines() adds only <br>). ?>
									<?php endif; ?>
								</h3>
								<?php if ( '' !== $item['tag'] ) : ?>
									<p class="ap-integration-directory__tag" data-ap-intdir-text><?php echo esc_html( $item['tag'] ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $item['desc'] ) : ?>
									<p class="ap-integration-directory__desc" data-ap-intdir-text><?php echo esc_html( $item['desc'] ); ?></p>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $item['url'] && '' !== $link_label ) : ?>
								<span class="ap-integration-directory__more" aria-hidden="true"><?php echo esc_html( $link_label ); ?><span class="ap-integration-directory__arrow"></span></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="ap-integration-directory__empty" data-ap-intdir-empty hidden>
					<p class="ap-integration-directory__empty-text"><?php echo esc_html( (string) $props['empty_text'] ); ?></p>
					<?php if ( '' !== $reset_label || '' !== $empty_url ) : ?>
						<div class="ap-integration-directory__empty-actions">
							<?php if ( '' !== $reset_label ) : ?>
								<button type="button" class="ap-integration-directory__reset" data-ap-intdir-reset><?php echo esc_html( $reset_label ); ?></button>
							<?php endif; ?>
							<?php if ( '' !== $empty_url ) : ?>
								<a class="ap-integration-directory__empty-link" href="<?php echo $empty_url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() above. ?>"><?php echo esc_html( $empty_label ); ?><span class="ap-integration-directory__arrow" aria-hidden="true"></span></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $per_page > 0 ) : ?>
					<nav class="ap-integration-directory__pager" aria-label="<?php echo esc_attr( (string) $props['pager_label'] ); ?>" data-ap-intdir-pager hidden>
						<button type="button" class="ap-integration-directory__step ap-integration-directory__step--prev" data-ap-intdir-step="-1"><span class="ap-integration-directory__step-icon" aria-hidden="true"></span><span class="ap-integration-directory__step-text"><?php echo esc_html( (string) $props['prev_label'] ); ?></span></button>
						<ul class="ap-integration-directory__pages" role="list" data-ap-intdir-pages></ul>
						<p class="ap-integration-directory__page-of" data-ap-intdir-page-of></p>
						<button type="button" class="ap-integration-directory__step ap-integration-directory__step--next" data-ap-intdir-step="1"><span class="ap-integration-directory__step-text"><?php echo esc_html( (string) $props['next_label'] ); ?></span><span class="ap-integration-directory__step-icon" aria-hidden="true"></span></button>
					</nav>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
