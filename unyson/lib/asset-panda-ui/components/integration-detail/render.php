<?php
/**
 * integration-detail — the body of an integration detail page.
 *
 * Figma: Integrations Detail Page Template > Platform Section (7318:168656).
 * A section head (eyebrow, H2, lede) over two columns: a 282px numbered index
 * that sticks under the site header from 1025px, and a 780px column of parts:
 *   Overview      eyebrow, H3, paragraphs, product video (poster + play)
 *   Capabilities  eyebrow, H3, lede, tinted cards (icon, H4, text)
 *   Data          eyebrow, H3, lede, a table (first cell of a row = its row
 *                 header), small-print note; stacked cards below 640px
 *   Steps         eyebrow, H3, numbered steps on a coloured left rule, an
 *                 optional guide link under them ("Detailed setup: ...")
 *   Requirements  eyebrow, H3, text, checklist + note, help card (panda,
 *                 title, text, button)
 *
 * Every part is optional and renders only when it has content; the index and
 * its numbers (01, 02 ...) come from the parts present, so a page with an
 * overview paragraph, steps and requirements reads 01-03 with no holes. The
 * index needs two or more parts; with one, the column simply runs alone.
 *
 * The parts and the index are direct children of one grid, so motion.js picks
 * each part as its own scroll-in block (the sticky index is never moved).
 * Anchors are readable and stable across pages (#integration-overview,
 * #integration-capabilities, #integration-how-it-works,
 * #integration-requirements; a second instance on a page gets a -2 suffix).
 * id_prefix swaps the "integration" start (the Compare hub uses "guides").
 *
 * A step may carry links (label + url): arrow links under its text, used by
 * the Compare hub's buying guides. Without links a step renders as before.
 *
 * Video: the poster carries a play link to the video itself, so it works with
 * no script; behaviour.js swaps the player in place on click (YouTube through
 * youtube-nocookie, Vimeo with do-not-track, or a <video controls> for a file)
 * and moves focus to it. Without a recognisable video_url the poster is a
 * plain image.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A trimmed string from an untrusted object prop ('' for anything else).
$ap_idet_str = static function ( $obj, $key ) {
	return ( is_array( $obj ) && isset( $obj[ $key ] ) && is_scalar( $obj[ $key ] ) ) ? trim( (string) $obj[ $key ] ) : '';
};

// The player URL for a video link: YouTube (nocookie), Vimeo, or a file.
$ap_idet_embed = static function ( $url ) {
	if ( '' === $url ) {
		return null;
	}
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m ) ) {
		return array(
			'kind' => 'iframe',
			'src'  => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0&playsinline=1',
		);
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?([0-9]{6,12})(?:/([0-9a-f]{6,20}))?~i', $url, $m ) ) {
		return array(
			'kind' => 'iframe',
			'src'  => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1' . ( ! empty( $m[2] ) ? '&h=' . $m[2] : '' ),
		);
	}
	if ( preg_match( '~^(?:https?://|/)[^\s"\'<>]+\.(?:mp4|webm|m4v|mov)(?:[?#][^\s"\'<>]*)?$~i', $url ) ) {
		return array(
			'kind' => 'video',
			'src'  => $url,
		);
	}
	return null;
};

$ap_idet_tone = static function ( $tone, $cycle, $i ) {
	$tone = strtolower( trim( (string) $tone ) );
	return in_array( $tone, array( 'indigo', 'teal', 'amber', 'rose' ), true ) ? $tone : $cycle[ $i % count( $cycle ) ];
};

$parts = array();

// ---- Overview ----
$ov    = is_array( $props['overview'] ) ? $props['overview'] : array();
$paras = array();
$raw   = $ov['paragraphs'] ?? array();
foreach ( is_array( $raw ) ? $raw : array( $raw ) as $p ) {
	$p = is_scalar( $p ) ? trim( (string) $p ) : '';
	if ( '' !== $p ) {
		$paras[] = $p;
	}
}
$ov_poster = $ap_idet_str( $ov, 'video_poster' );
$ov_url    = $ap_idet_str( $ov, 'video_url' );
$ov_embed  = $ap_idet_embed( $ov_url );
$ov_logo   = $ap_idet_str( $ov, 'logo' );
if ( '' !== $ap_idet_str( $ov, 'title' ) || $paras || '' !== $ov_poster || $ov_embed || '' !== $ov_logo ) {
	$parts[] = array(
		'key'      => 'overview',
		'slug'     => 'overview',
		'label'    => 'Overview',
		'data'     => $ov,
		'paras'    => $paras,
		'poster'   => $ov_poster,
		'url'      => $ov_url,
		'embed'    => $ov_embed,
		'v_title'  => $ap_idet_str( $ov, 'video_title' ),
		'logo'     => $ov_logo,
		'logo_alt' => $ap_idet_str( $ov, 'logo_alt' ),
		'brand'    => $ap_idet_str( $ov, 'brand_logo' ),
	);
}

// ---- Key Capabilities ----
$cap   = is_array( $props['capabilities'] ) ? $props['capabilities'] : array();
$cards = array();
foreach ( ( is_array( $cap['items'] ?? null ) ? $cap['items'] : array() ) as $item ) {
	$t = $ap_idet_str( $item, 'title' );
	if ( '' === $t ) {
		continue;
	}
	$cards[] = array(
		'title'    => $t,
		'text'     => $ap_idet_str( $item, 'text' ),
		'icon'     => $ap_idet_str( $item, 'icon' ),
		'icon_svg' => ap_ui_svg( is_array( $item ) && isset( $item['icon_svg'] ) && is_string( $item['icon_svg'] ) ? $item['icon_svg'] : '' ),
		'tone'     => $ap_idet_tone( $item['tone'] ?? '', array( 'indigo', 'teal', 'amber', 'rose' ), count( $cards ) ),
	);
}
if ( $cards || '' !== $ap_idet_str( $cap, 'title' ) ) {
	$parts[] = array(
		'key'   => 'capabilities',
		'slug'  => 'capabilities',
		'label' => 'Key Capabilities',
		'data'  => $cap,
		'cards' => $cards,
	);
}

// ---- Supported Data (a table) ----
$dat     = is_array( $props['data'] ) ? $props['data'] : array();
$d_cols  = array();
$d_rows  = array();
$raw_c   = $dat['columns'] ?? array();
foreach ( is_array( $raw_c ) ? $raw_c : array() as $c ) {
	$d_cols[] = is_scalar( $c ) ? trim( (string) $c ) : '';
}
$raw_r = $dat['rows'] ?? array();
foreach ( is_array( $raw_r ) ? $raw_r : array() as $row ) {
	if ( ! is_array( $row ) ) {
		continue;
	}
	$cells = array();
	foreach ( array_values( $row ) as $cell ) {
		$cells[] = is_scalar( $cell ) ? trim( (string) $cell ) : '';
	}
	if ( '' !== implode( '', $cells ) ) {
		$d_rows[] = $cells;
	}
}
// The table is as wide as its widest row (or its header); short rows are padded.
$d_width = count( $d_cols );
foreach ( $d_rows as $cells ) {
	$d_width = max( $d_width, count( $cells ) );
}
if ( $d_rows || '' !== $ap_idet_str( $dat, 'title' ) ) {
	$parts[] = array(
		'key'   => 'data',
		'slug'  => 'data',
		'label' => 'Supported Data',
		'data'  => $dat,
		'cols'  => array_pad( $d_cols, $d_width, '' ),
		'rows'  => $d_rows,
		'width' => $d_width,
	);
}

// ---- How It Works ----
$stp   = is_array( $props['steps'] ) ? $props['steps'] : array();
$steps = array();
foreach ( ( is_array( $stp['items'] ?? null ) ? $stp['items'] : array() ) as $item ) {
	$t = $ap_idet_str( $item, 'title' );
	if ( '' === $t ) {
		continue;
	}
	// Optional links under a step (label + url each), e.g. the Compare hub's guides.
	$s_links = array();
	foreach ( ( is_array( $item ) && is_array( $item['links'] ?? null ) ? $item['links'] : array() ) as $link ) {
		$l_label = $ap_idet_str( $link, 'label' );
		$l_url   = $ap_idet_str( $link, 'url' );
		if ( '' !== $l_label && '' !== $l_url && '' !== esc_url( $l_url ) ) {
			$s_links[] = array(
				'label' => $l_label,
				'url'   => $l_url,
			);
		}
	}
	$steps[] = array(
		'title' => $t,
		'text'  => $ap_idet_str( $item, 'text' ),
		'tone'  => $ap_idet_tone( $item['tone'] ?? '', array( 'indigo', 'rose', 'teal', 'amber' ), count( $steps ) ),
		'links' => $s_links,
	);
}
// Optional single link under the steps (e.g. "Detailed setup:" + the knowledge-base article).
$s_guide = is_array( $stp['guide'] ?? null ) ? $stp['guide'] : array();
$s_guide = array(
	'intro' => $ap_idet_str( $s_guide, 'intro' ),
	'label' => $ap_idet_str( $s_guide, 'label' ),
	'url'   => $ap_idet_str( $s_guide, 'url' ),
);
$has_guide = '' !== $s_guide['label'] && '' !== $s_guide['url'] && '' !== esc_url( $s_guide['url'] );
if ( $steps || '' !== $ap_idet_str( $stp, 'title' ) ) {
	$parts[] = array(
		'key'   => 'steps',
		'slug'  => 'how-it-works',
		'label' => 'How It Works',
		'data'  => $stp,
		'steps' => $steps,
		'guide' => $has_guide ? $s_guide : null,
	);
}

// ---- Requirements ----
$req  = is_array( $props['requirements'] ) ? $props['requirements'] : array();
$help = is_array( $req['help'] ?? null ) ? $req['help'] : array();
$help = array(
	'title' => $ap_idet_str( $help, 'title' ),
	'text'  => $ap_idet_str( $help, 'text' ),
	'label' => $ap_idet_str( $help, 'cta_label' ),
	'url'   => $ap_idet_str( $help, 'cta_url' ),
	'icon'  => $ap_idet_str( $help, 'icon' ),
);
$has_help = '' !== $help['title'] || '' !== $help['text'];
// Optional checklist + small print under the requirements copy.
$r_items = array();
$raw_i   = $req['items'] ?? array();
foreach ( is_array( $raw_i ) ? $raw_i : array() as $it ) {
	$it = is_scalar( $it ) ? trim( (string) $it ) : '';
	if ( '' !== $it ) {
		$r_items[] = $it;
	}
}
$r_note = $ap_idet_str( $req, 'note' );
// Optional guide link after the checklist (a doc may place "Detailed setup: ..." here instead of under the steps).
$r_guide = is_array( $req['guide'] ?? null ) ? $req['guide'] : array();
$r_guide = array(
	'intro' => $ap_idet_str( $r_guide, 'intro' ),
	'label' => $ap_idet_str( $r_guide, 'label' ),
	'url'   => $ap_idet_str( $r_guide, 'url' ),
);
$r_guide = ( '' !== $r_guide['label'] && '' !== $r_guide['url'] && '' !== esc_url( $r_guide['url'] ) ) ? $r_guide : null;
if ( $has_help || $r_items || '' !== $ap_idet_str( $req, 'title' ) || '' !== $ap_idet_str( $req, 'text' ) ) {
	$parts[] = array(
		'key'      => 'requirements',
		'slug'     => 'requirements',
		'label'    => 'Requirements',
		'data'     => $req,
		'help'     => $help,
		'has_help' => $has_help,
		'items'    => $r_items,
		'note'     => $r_note,
		'guide'    => $r_guide,
	);
}

$eyebrow = trim( (string) $props['eyebrow'] );
$title   = trim( (string) $props['title'] );
$lede    = trim( (string) $props['lede'] );

if ( ! $parts && '' === $title && '' === $lede ) {
	return;
}

static $ap_idet_instance = 0;
++$ap_idet_instance;
// Anchor prefix: 'integration' unless the page names its own (e.g. 'guides' on /compare/).
$id_prefix = strtolower( trim( (string) $props['id_prefix'] ) );
$id_prefix = preg_match( '/^[a-z][a-z0-9-]{0,39}$/', $id_prefix ) ? $id_prefix : 'integration';
$suffix    = $ap_idet_instance > 1 ? '-' . $ap_idet_instance : '';
$title_id  = $id_prefix . '-details-title' . $suffix;
$has_nav   = count( $parts ) >= 2 && false !== $props['index'];
// Heading levels: under a section heading the parts are H3 (items H4); with no section heading
// the parts carry the page's H2s (items H3), so the outline still steps down from the H1.
$h_part = '' !== $title ? 'h3' : 'h2';
$h_item = '' !== $title ? 'h4' : 'h3';
foreach ( $parts as $i => $part ) {
	$parts[ $i ]['id']  = $id_prefix . '-' . $part['slug'] . $suffix;
	$parts[ $i ]['num'] = sprintf( '%02d', $i + 1 );
	$nav                = $ap_idet_str( $part['data'], 'nav' );
	if ( '' === $nav ) {
		$nav = $ap_idet_str( $part['data'], 'eyebrow' );
	}
	$parts[ $i ]['nav'] = '' !== $nav ? $nav : $part['label'];
}

// Escaped text with the kit's brand-mark styling (only a fixed span is added).
$ap_idet_text = static function ( $text ) {
	return ap_ui_brandmarks( ap_ui_lines( $text ) );
};

$panda     = ap_ui_asset_url( 'integration-detail', 'panda.svg' );
$nav_label = trim( (string) $props['nav_label'] );
$nav_label = '' !== $nav_label ? $nav_label : 'On this page';

// Each index dot wears its part's colour: overview indigo, capabilities teal,
// steps rose, requirements amber (Figma: "the active dot color should match
// the corresponding card accent color").
$nav_tones = array(
	'overview'     => 'indigo',
	'capabilities' => 'teal',
	'data'         => 'indigo',
	'steps'        => 'rose',
	'requirements' => 'amber',
);
?>
<section class="ap-integration-detail<?php echo 'full' === $props['head_width'] ? ' ap-integration-detail--head-full' : ''; ?><?php echo 'compact' === $props['size'] ? ' ap-integration-detail--compact' : ''; ?>" data-ap-integration-detail<?php echo '' !== $title ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : ''; ?>>
	<div class="ap-integration-detail__inner">
		<?php if ( '' !== $eyebrow || '' !== $title || '' !== $lede ) : ?>
			<div class="ap-integration-detail__head">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-integration-detail__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h2 class="ap-integration-detail__title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo $ap_idet_text( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $lede ) : ?>
					<p class="ap-integration-detail__lede"><?php echo $ap_idet_text( $lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $parts ) : ?>
			<div class="ap-integration-detail__layout<?php echo $has_nav ? '' : ' ap-integration-detail__layout--single'; ?><?php echo false === $props['index'] ? ' ap-integration-detail__layout--wide' : ''; ?>"<?php echo $has_nav ? ' style="' . esc_attr( '--ap-idet-parts:' . count( $parts ) ) . '"' : ''; ?>>
				<?php if ( $has_nav ) : ?>
					<nav class="ap-integration-detail__nav" aria-label="<?php echo esc_attr( $nav_label ); ?>">
						<ol role="list">
							<?php foreach ( $parts as $i => $part ) : ?>
								<li class="ap-integration-detail--tone-<?php echo esc_attr( $nav_tones[ $part['key'] ] ); ?>">
									<a href="#<?php echo esc_attr( $part['id'] ); ?>"<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>>
										<span class="ap-integration-detail__nav-dot" aria-hidden="true"></span>
										<span class="ap-integration-detail__nav-text"><span class="ap-integration-detail__nav-num"><?php echo esc_html( $part['num'] ); ?></span> <?php echo esc_html( $part['nav'] ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					</nav>
				<?php endif; ?>

				<?php foreach ( $parts as $part ) : ?>
					<?php
					$d      = $part['data'];
					$p_eye  = $ap_idet_str( $d, 'eyebrow' );
					$p_head = $ap_idet_str( $d, 'title' );
					$p_lede = $ap_idet_str( $d, 'lede' );
					?>
					<div class="ap-integration-detail__part ap-integration-detail__part--<?php echo esc_attr( $part['key'] ); ?>" id="<?php echo esc_attr( $part['id'] ); ?>">
						<?php if ( '' !== $p_eye || '' !== $p_head || '' !== $p_lede || ! empty( $part['paras'] ) || ( 'requirements' === $part['key'] && '' !== $ap_idet_str( $d, 'text' ) ) ) : ?>
							<div class="ap-integration-detail__part-head">
								<?php if ( '' !== $p_eye ) : ?>
									<p class="ap-integration-detail__part-eyebrow"><?php echo esc_html( $p_eye ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $p_head ) : ?>
									<<?php echo esc_html( $h_part ); ?> class="ap-integration-detail__h3"><?php echo $ap_idet_text( $p_head ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></<?php echo esc_html( $h_part ); ?>>
								<?php endif; ?>
								<?php if ( 'overview' === $part['key'] && $part['paras'] ) : ?>
									<div class="ap-integration-detail__copy">
										<?php foreach ( $part['paras'] as $para ) : ?>
											<p><?php echo $ap_idet_text( $para ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
										<?php endforeach; ?>
									</div>
								<?php elseif ( 'requirements' === $part['key'] && '' !== $ap_idet_str( $d, 'text' ) ) : ?>
									<div class="ap-integration-detail__copy">
										<p><?php echo $ap_idet_text( $ap_idet_str( $d, 'text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
									</div>
								<?php elseif ( '' !== $p_lede ) : ?>
									<div class="ap-integration-detail__copy">
										<p><?php echo $ap_idet_text( $p_lede ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( 'overview' === $part['key'] && ( '' !== $part['poster'] || $part['embed'] ) ) : ?>
							<?php $v_title = $part['v_title']; ?>
							<div class="ap-integration-detail__media<?php echo '' === $part['poster'] ? ' ap-integration-detail__media--bare' : ''; ?>">
								<?php if ( '' !== $part['poster'] ) : ?>
									<img class="ap-integration-detail__poster" src="<?php echo esc_url( $part['poster'] ); ?>" alt="<?php echo esc_attr( $part['embed'] ? '' : $v_title ); ?>" width="780" height="437" loading="lazy" decoding="async" />
								<?php endif; ?>
								<?php if ( $part['embed'] ) : ?>
									<a class="ap-integration-detail__play" href="<?php echo esc_url( $part['url'] ); ?>" data-ap-idet-play data-kind="<?php echo esc_attr( $part['embed']['kind'] ); ?>" data-embed="<?php echo esc_url( $part['embed']['src'] ); ?>" data-title="<?php echo esc_attr( '' !== $v_title ? $v_title : 'Product video' ); ?>" aria-label="<?php echo esc_attr( '' !== $v_title ? 'Play video: ' . $v_title : 'Play video' ); ?>">
										<span class="ap-integration-detail__play-btn" aria-hidden="true">
											<img src="<?php echo esc_url( ap_ui_asset_url( 'integration-detail', 'play-triangle.svg' ) ); ?>" alt="" width="20" height="16" decoding="async" />
										</span>
									</a>
								<?php endif; ?>
							</div>
						<?php elseif ( 'overview' === $part['key'] && '' !== $part['logo'] ) : ?>
							<?php
							// The partner + Asset Panda PRO logo card on the hero's colour wash, in the
							// video's place (the split hero carries the video). Decoration unless the
							// logo has alt text — the hero H1 already names both products.
							$pair_alt   = $part['logo_alt'];
							$pair_brand = '' !== $part['brand'] ? $part['brand'] : ap_ui_asset_url( 'integration-detail', 'panda-pro.svg' );
							?>
							<div class="ap-integration-detail__pair">
								<div class="ap-integration-detail__pair-card"<?php echo '' === $pair_alt ? ' aria-hidden="true"' : ''; ?>>
									<img class="ap-integration-detail__pair-logo" src="<?php echo esc_url( $part['logo'] ); ?>" alt="<?php echo esc_attr( $pair_alt ); ?>" width="80" height="80" loading="lazy" decoding="async" />
									<img class="ap-integration-detail__pair-plus" src="<?php echo esc_url( ap_ui_asset_url( 'integration-detail', 'plus.svg' ) ); ?>" alt="<?php echo '' !== $pair_alt ? '+' : ''; ?>" width="12" height="12" loading="lazy" decoding="async" />
									<img class="ap-integration-detail__pair-brand" src="<?php echo esc_url( $pair_brand ); ?>" alt="<?php echo '' !== $pair_alt ? 'Asset Panda Pro' : ''; ?>" width="76" height="80" loading="lazy" decoding="async" />
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 'capabilities' === $part['key'] && $part['cards'] ) : ?>
							<ul class="ap-integration-detail__cards" role="list">
								<?php foreach ( $part['cards'] as $card ) : ?>
									<li class="ap-integration-detail__card ap-integration-detail--tone-<?php echo esc_attr( $card['tone'] ); ?>">
										<div class="ap-integration-detail__card-lead">
											<?php if ( '' !== $card['icon_svg'] ) : ?>
												<span class="ap-integration-detail__card-icon" aria-hidden="true"><?php echo $card['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
											<?php elseif ( '' !== $card['icon'] ) : ?>
												<span class="ap-integration-detail__card-icon" aria-hidden="true"><img src="<?php echo esc_url( $card['icon'] ); ?>" alt="" width="36" height="36" loading="lazy" decoding="async" /></span>
											<?php endif; ?>
											<<?php echo esc_html( $h_item ); ?> class="ap-integration-detail__card-title"><?php echo $ap_idet_text( $card['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></<?php echo esc_html( $h_item ); ?>>
										</div>
										<?php if ( '' !== $card['text'] ) : ?>
											<p class="ap-integration-detail__card-text"><?php echo $ap_idet_text( $card['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( 'data' === $part['key'] && $part['rows'] ) : ?>
							<?php
							$t_label = '' !== $p_head ? $p_head : ( '' !== $p_eye ? $p_eye : $part['label'] );
							$t_has_h = '' !== implode( '', $part['cols'] );
							?>
							<div class="ap-integration-detail__table-wrap" role="region" aria-label="<?php echo esc_attr( $t_label ); ?>" tabindex="0">
								<table class="ap-integration-detail__table ap-integration-detail__table--cols-<?php echo esc_attr( (string) min( 4, $part['width'] ) ); ?>">
									<?php if ( $t_has_h ) : ?>
										<thead>
											<tr>
												<?php foreach ( $part['cols'] as $col ) : ?>
													<th scope="col"><?php echo esc_html( $col ); ?></th>
												<?php endforeach; ?>
											</tr>
										</thead>
									<?php endif; ?>
									<tbody>
										<?php foreach ( $part['rows'] as $cells ) : ?>
											<tr>
												<?php foreach ( array_pad( $cells, $part['width'], '' ) as $c => $cell ) : ?>
													<?php if ( 0 === $c ) : ?>
														<th scope="row"><?php echo $ap_idet_text( $cell ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></th>
													<?php else : ?>
														<td<?php echo '' !== $part['cols'][ $c ] ? ' data-label="' . esc_attr( $part['cols'][ $c ] ) . '"' : ''; ?>><?php echo $ap_idet_text( $cell ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></td>
													<?php endif; ?>
												<?php endforeach; ?>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php if ( '' !== $ap_idet_str( $d, 'note' ) ) : ?>
								<p class="ap-integration-detail__note"><?php echo $ap_idet_text( $ap_idet_str( $d, 'note' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( 'steps' === $part['key'] && $part['steps'] ) : ?>
							<ol class="ap-integration-detail__steps" role="list">
								<?php foreach ( $part['steps'] as $n => $step ) : ?>
									<li class="ap-integration-detail__step ap-integration-detail--tone-<?php echo esc_attr( $step['tone'] ); ?>">
										<div class="ap-integration-detail__step-top">
											<span class="ap-integration-detail__step-num" aria-hidden="true"><?php echo esc_html( (string) ( $n + 1 ) ); ?></span>
											<<?php echo esc_html( $h_item ); ?> class="ap-integration-detail__step-title"><?php echo $ap_idet_text( $step['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></<?php echo esc_html( $h_item ); ?>>
										</div>
										<?php if ( '' !== $step['text'] ) : ?>
											<p class="ap-integration-detail__step-text"><?php echo $ap_idet_text( $step['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
										<?php endif; /* one tag, so a step without links prints exactly as before */ if ( $step['links'] ) : ?>
											<ul class="ap-integration-detail__links<?php echo count( $step['links'] ) > 4 ? ' ap-integration-detail__links--cols' : ''; ?>" role="list">
												<?php foreach ( $step['links'] as $link ) : ?>
													<li><a class="ap-integration-detail__link" href="<?php echo esc_url( $link['url'] ); ?>"><span class="ap-integration-detail__link-text"><?php echo esc_html( $link['label'] ); ?></span><span class="ap-integration-detail__link-arrow" aria-hidden="true"></span></a></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ol>
							<?php if ( ! empty( $part['guide'] ) ) : ?>
								<?php $g = $part['guide']; ?>
								<p class="ap-integration-detail__guide">
									<?php if ( '' !== $g['intro'] ) : ?>
										<span class="ap-integration-detail__guide-intro"><?php echo esc_html( $g['intro'] ); ?></span>
									<?php endif; ?>
									<a class="ap-integration-detail__guide-link" href="<?php echo esc_url( $g['url'] ); ?>"><span class="ap-integration-detail__link-text"><?php echo esc_html( $g['label'] ); ?></span><span class="ap-integration-detail__link-arrow" aria-hidden="true"></span></a>
								</p>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( 'requirements' === $part['key'] && $part['items'] ) : ?>
							<ul class="ap-integration-detail__checks" role="list">
								<?php foreach ( $part['items'] as $it ) : ?>
									<li><?php echo $ap_idet_text( $it ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( 'requirements' === $part['key'] && '' !== $part['note'] ) : ?>
							<p class="ap-integration-detail__note"><?php echo $ap_idet_text( $part['note'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
						<?php endif; ?>
						<?php if ( 'requirements' === $part['key'] && ! empty( $part['guide'] ) ) : ?>
							<?php $g = $part['guide']; ?>
							<p class="ap-integration-detail__guide">
								<?php if ( '' !== $g['intro'] ) : ?>
									<span class="ap-integration-detail__guide-intro"><?php echo esc_html( $g['intro'] ); ?></span>
								<?php endif; ?>
								<a class="ap-integration-detail__guide-link" href="<?php echo esc_url( $g['url'] ); ?>"><span class="ap-integration-detail__link-text"><?php echo esc_html( $g['label'] ); ?></span><span class="ap-integration-detail__link-arrow" aria-hidden="true"></span></a>
							</p>
						<?php endif; ?>

						<?php if ( 'requirements' === $part['key'] && $part['has_help'] ) : ?>
							<?php $h = $part['help']; ?>
							<div class="ap-integration-detail__help">
								<div class="ap-integration-detail__help-main">
									<img class="ap-integration-detail__help-icon" src="<?php echo esc_url( '' !== $h['icon'] ? $h['icon'] : $panda ); ?>" alt="" width="41" height="44" loading="lazy" decoding="async" />
									<div class="ap-integration-detail__help-copy">
										<?php if ( '' !== $h['title'] ) : ?>
											<p class="ap-integration-detail__help-title"><?php echo $ap_idet_text( $h['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
										<?php endif; ?>
										<?php if ( '' !== $h['text'] ) : ?>
											<p class="ap-integration-detail__help-text"><?php echo $ap_idet_text( $h['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by ap_ui_lines(). ?></p>
										<?php endif; ?>
									</div>
								</div>
								<?php if ( '' !== $h['label'] && '' !== $h['url'] ) : ?>
									<a class="ap-integration-detail__btn" href="<?php echo esc_url( $h['url'] ); ?>"><?php echo esc_html( $h['label'] ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
