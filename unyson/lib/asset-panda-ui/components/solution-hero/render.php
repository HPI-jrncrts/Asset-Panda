<?php
/**
 * solution-hero — the hero on the September 2026 solution pages.
 *
 * Matches the live .epx-hero on /solutions/{asset-management, inventory-
 * management, fixed-asset-tracking, it-asset-tracking, tool-tracking,
 * equipment-management}/: the copy column beside a visual box (sized per page)
 * holding a cut-out photo and floating mock UI cards, with a chip strip below.
 *
 * The six pages share one stylesheet but each draws different mock cards
 * (a request bubble, an assistant answer, a record, a status chip…). They are
 * decoration, so each card's body arrives as markup and is sanitised by
 * ap_ui_markup(); every geometry value is built from numbers alone. Inner class
 * names are the live ones, so this stays diffable against the ported sheet.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item ) {
	$svg = ap_ui_svg( $item['icon_svg'] ?? '' );
	if ( '' !== $svg ) {
		return $svg;
	}
	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	return '' !== $icon ? '<img src="' . esc_url( $icon ) . '" alt="" width="24" height="24" decoding="async" />' : '';
};

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="epx-g">' . $escaped . '</span>', $html );
	}
	return $html;
};

// "left:103px;top:0px;width:318.5px" from whichever keys hold numbers.
$ap_px = static function ( $geo, array $map ) {
	$out = array();
	foreach ( $map as $key => $property ) {
		if ( is_array( $geo ) && isset( $geo[ $key ] ) && is_numeric( $geo[ $key ] ) ) {
			$out[] = $property . ':' . round( (float) $geo[ $key ], 2 ) . 'px';
		}
	}
	return implode( ';', $out );
};

$slots  = array( 'c1', 'c2', 'c3', 'f1', 'f2', 'f3' );
$cards  = array_values(
	array_filter(
		is_array( $props['cards'] ) ? $props['cards'] : array(),
		static function ( $c ) {
			return is_array( $c ) && '' !== trim( (string) ( $c['markup'] ?? '' ) );
		}
	)
);
$badges = array_values(
	array_filter(
		is_array( $props['badges'] ) ? $props['badges'] : array(),
		static function ( $b ) {
			return is_array( $b ) && ! empty( $b['image'] );
		}
	)
);
$feats  = array_values(
	array_filter(
		is_array( $props['features'] ) ? $props['features'] : array(),
		static function ( $f ) {
			return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) );
		}
	)
);
$bg     = trim( (string) $props['background'] );

// The visual box differs per page (live sizes it to each page's photo), and so
// does the phone scale table that shrinks it. Both reach CSS as custom
// properties built from numbers alone; anything left out keeps the stylesheet's
// asset-management values. Scale steps exist only at the breakpoints the
// stylesheet declares, so other keys are ignored. The box's x places it on
// desktop (see below); its y and right keys are accepted for compatibility
// but not read: the box stands on the stage's foot.
$ap_style  = array();
if ( '' !== $bg ) {
	$ap_style[] = '--ap-solution-bg:url("' . esc_url_raw( $bg ) . '")';
}
// The backdrop is a CSS background the browser would find only after parsing the stylesheet; it is
// the hero's largest paint, so ask for it from <head> (PageSpeed LCP).
if ( '' !== $bg && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $bg ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url() in the loader.
}
$ap_visual = is_array( $props['visual'] ) ? $props['visual'] : array();
foreach ( array( 'w' => '--ap-sh-vw', 'h' => '--ap-sh-vh' ) as $key => $var ) {
	if ( isset( $ap_visual[ $key ] ) && is_numeric( $ap_visual[ $key ] ) && (float) $ap_visual[ $key ] > 0 && (float) $ap_visual[ $key ] <= 2000 ) {
		$ap_style[] = $var . ':' . round( (float) $ap_visual[ $key ], 2 ) . 'px';
	}
}
// The box's x (its left edge in the page's 1440px Figma hero frame) places it on desktop again (QA 2026-10-29
// evening, "exactly as figma dictates"): the stylesheet draws the box at its Figma size standing on the stage's
// foot (every page's y + h is the frame's 640px foot), so y is still not read.
if ( isset( $ap_visual['x'] ) && is_numeric( $ap_visual['x'] ) && (float) $ap_visual['x'] >= 0 && (float) $ap_visual['x'] <= 1440 ) {
	$ap_style[] = '--ap-sh-vx:' . round( (float) $ap_visual['x'], 2 ) . 'px';
}
// Normalised art size (QA 2026-10-29, "make sure the image sizes are consistent"; no longer read on desktop since
// the evening pass, which draws the box at its Figma size): the stylesheet scaled
// the visual box (photo + cards, laid out per page in w x h) as one unit so it renders 520px tall on every
// desktop solution page, or 700px wide if that comes first, so an unusually wide box cannot run into the
// copy column. CSS calc() cannot divide two lengths, so the factor is computed here; the 637 x 518px
// fallbacks are the stylesheet's asset-management defaults for a page that sends no size.
$ap_dim     = static function ( $key, $fallback ) use ( $ap_visual ) {
	return isset( $ap_visual[ $key ] ) && is_numeric( $ap_visual[ $key ] ) && (float) $ap_visual[ $key ] > 0 && (float) $ap_visual[ $key ] <= 2000 ? (float) $ap_visual[ $key ] : $fallback;
};
$ap_style[] = '--ap-sh-k:' . round( min( 520 / $ap_dim( 'h', 518.0 ), 700 / $ap_dim( 'w', 637.0 ) ), 4 );
$ap_scale = isset( $ap_visual['scale'] ) && is_array( $ap_visual['scale'] ) ? $ap_visual['scale'] : array();
foreach ( array( 754, 736, 717, 667, 640, 614, 480, 430, 400, 360 ) as $bp ) {
	$v = $ap_scale[ (string) $bp ] ?? null;
	if ( is_numeric( $v ) && (float) $v > 0 && (float) $v <= 2 ) {
		$ap_style[] = '--ap-sh-vs-' . $bp . ':' . round( (float) $v, 3 );
	}
}
?>
<?php
// Optional second button and a badge row of its own under the buttons (Partners page). Both are opt-in:
// without them the markup below is exactly what every solution page already renders.
$ap_second = '' !== trim( (string) $props['secondary_label'] ) && '' !== trim( (string) $props['secondary_url'] );
$ap_below  = ! empty( $props['badges_below'] );
// Optional form card in place of the art (QA 2026-10-30, /barcode-labels/: "we're moving the form into the hero
// section"). Drawn by form-hero's card variant through the loader, so its stylesheet and form script come with it.
$ap_form     = is_array( $props['form'] ) ? $props['form'] : array();
$ap_has_form = '' !== trim( (string) ( $ap_form['hubspot_portal'] ?? '' ) ) && '' !== trim( (string) ( $ap_form['hubspot_form'] ?? '' ) );
// Optional call-out cards along the bottom of the hero (Partners, 2026-10-31). Opt-in, like the extras above.
$ap_choices = array();
foreach ( is_array( $props['choices'] ) ? $props['choices'] : array() as $ap_choice ) {
	if ( is_array( $ap_choice ) && '' !== trim( (string) ( $ap_choice['title'] ?? '' ) ) ) {
		$ap_choices[] = array(
			'title' => trim( (string) $ap_choice['title'] ),
			'text'  => trim( (string) ( $ap_choice['text'] ?? '' ) ),
			'icon'  => ap_ui_svg( $ap_choice['icon_svg'] ?? '' ),
			'label' => trim( (string) ( $ap_choice['cta_label'] ?? '' ) ),
			'url'   => trim( (string) ( $ap_choice['cta_url'] ?? '' ) ),
		);
	}
}
?>
<section class="ap-solution-hero<?php echo $ap_below ? ' ap-solution-hero--badges-below' : ''; ?><?php echo $ap_has_form ? ' ap-solution-hero--form' : ''; ?><?php echo 'heading' === $props['title_accent_fill'] ? ' ap-solution-hero--accent-heading' : ''; ?><?php echo $ap_choices ? ' ap-solution-hero--choices' : ''; ?>"<?php echo $ap_style ? ' style="' . esc_attr( implode( ';', $ap_style ) ) . '"' : ''; ?>>
	<div class="ap-solution-hero__orbs" aria-hidden="true"></div>
	<div class="ap-solution-hero__stage">
		<?php if ( $ap_has_form ) : ?>
			<div class="ap-solution-hero__formwrap">
				<?php
				$ap_form_props = array_intersect_key( $ap_form, array_flip( array( 'anchor', 'form_title', 'form_intro', 'form_note', 'hubspot_portal', 'hubspot_form', 'hubspot_region', 'form_embed', 'form_submit', 'fallback_email', 'fallback_phone' ) ) );
				$ap_form_props = array_map( static function ( $v ) { return is_scalar( $v ) ? (string) $v : ''; }, $ap_form_props );
				// The card heading is an h2 (title_tag h1 makes form-hero use h2 for its card); the hero keeps the page's h1.
				echo ap_render_component( 'form-hero', array_merge( $ap_form_props, array( 'variant' => 'card', 'title' => '' !== trim( $ap_form_props['form_title'] ?? '' ) ? $ap_form_props['form_title'] : 'Talk with an expert', 'title_tag' => 'h1' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- the component escapes its own output.
				?>
			</div>
		<?php else : ?>
		<div class="ap-solution-hero__vwrap" aria-hidden="true">
			<div class="ap-solution-hero__visual">
				<?php if ( '' !== trim( (string) $props['photo'] ) ) : ?>
					<img class="ap-solution-hero__photo" src="<?php echo esc_url( $props['photo'] ); ?>" alt="<?php echo esc_attr( $props['photo_alt'] ); ?>" decoding="async" fetchpriority="high"<?php echo is_array( $props['photo_box'] ) && isset( $props['photo_box']['w'], $props['photo_box']['h'] ) && is_numeric( $props['photo_box']['w'] ) && is_numeric( $props['photo_box']['h'] ) ? ' width="' . (int) round( (float) $props['photo_box']['w'] ) . '" height="' . (int) round( (float) $props['photo_box']['h'] ) . '"' : ''; ?> style="<?php echo esc_attr( $ap_px( $props['photo_box'], array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ) ); ?>" />
				<?php endif; ?>
				<?php foreach ( array_slice( $cards, 0, 6 ) as $i => $card ) : ?>
					<?php
					$slot  = in_array( $card['slot'] ?? '', $slots, true ) ? $card['slot'] : $slots[ $i ];
					$flo   = implode( ' ', array_filter( preg_split( '/\s+/', (string) ( $card['flo_class'] ?? '' ) ), static function ( $c ) { return (bool) preg_match( '/^[a-z][a-z0-9_-]*$/i', $c ); } ) );
					$class = 'epx-hv epx-hv--' . $slot . ( ! empty( $card['below'] ) ? ' epx-hv--below' : '' );
					?>
					<div class="<?php echo esc_attr( $class ); ?>" style="<?php echo esc_attr( $ap_px( $card, array( 'x' => 'left', 'y' => 'top', 'w' => 'width' ) ) ); ?>">
						<div class="epx-hv__rev">
							<div class="<?php echo esc_attr( trim( 'epx-hv__flo ' . $flo ) ); ?>"><?php echo ap_ui_markup( $card['markup'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_markup(). ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="ap-solution-hero__veil" aria-hidden="true"></div>
		<div class="ap-solution-hero__content">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="ap-solution-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h1 class="ap-solution-hero__h1"><?php echo $ap_accent( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h1>
			<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
				<p class="ap-solution-hero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<div class="ap-solution-hero__cta">
				<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
					<a class="epx-btn epx-btn--primary" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
				<?php endif; ?>
<?php if ( $ap_second ) : ?>
				<a class="epx-btn epx-btn--outline" href="<?php echo esc_url( $props['secondary_url'] ); ?>"><?php echo esc_html( $props['secondary_label'] ); ?></a>
<?php endif; ?>
				<?php if ( $badges && ! $ap_below ) : ?>
					<span class="ap-solution-hero__badges">
						<?php foreach ( $badges as $badge ) : ?>
							<?php
							// Live sizes some marks off their natural size (55x70 for a 48x60 file).
							$ap_dims = '';
							foreach ( array( 'w' => 'width', 'h' => 'height' ) as $key => $attr ) {
								if ( isset( $badge[ $key ] ) && is_numeric( $badge[ $key ] ) && (int) $badge[ $key ] > 0 && (int) $badge[ $key ] <= 400 ) {
									$ap_dims .= ' ' . $attr . '="' . (int) $badge[ $key ] . '"';
								}
							}
							?>
							<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>"<?php echo $ap_dims; // phpcs:ignore WordPress.Security.EscapeOutput -- integers only. ?> decoding="async" />
						<?php endforeach; ?>
					</span>
				<?php endif; ?>
			</div>
<?php if ( $badges && $ap_below ) : ?>
			<div class="ap-solution-hero__badges ap-solution-hero__badges--row">
	<?php foreach ( $badges as $badge ) : ?>
		<?php
		$ap_dims = '';
		foreach ( array( 'w' => 'width', 'h' => 'height' ) as $key => $attr ) {
			if ( isset( $badge[ $key ] ) && is_numeric( $badge[ $key ] ) && (int) $badge[ $key ] > 0 && (int) $badge[ $key ] <= 400 ) {
				$ap_dims .= ' ' . $attr . '="' . (int) $badge[ $key ] . '"';
			}
		}
		?>
				<img src="<?php echo esc_url( $badge['image'] ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>"<?php echo $ap_dims; // phpcs:ignore WordPress.Security.EscapeOutput -- integers only. ?> decoding="async" />
	<?php endforeach; ?>
			</div>
<?php endif; ?>
		</div>
		<?php if ( $feats ) : ?>
			<div class="ap-solution-hero__ticker">
				<div class="ap-solution-hero__strip">
					<?php foreach ( array( false, true ) as $dup ) : ?>
						<?php foreach ( $feats as $feat ) : ?>
							<span class="ap-solution-hero__pill<?php echo $dup ? ' ap-solution-hero__pill--dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>><?php echo $ap_icon( $feat ); // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised or escaped in the helper. ?><?php echo esc_html( $feat['label'] ); ?></span>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $ap_choices ) : ?>
		<div class="ap-solution-hero__choices">
			<?php foreach ( $ap_choices as $ap_choice ) : ?>
				<div class="ap-solution-hero__choice">
					<div class="ap-solution-hero__choice-body">
						<?php if ( '' !== $ap_choice['icon'] ) : ?>
							<span class="ap-solution-hero__choice-icon" aria-hidden="true"><?php echo $ap_choice['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php endif; ?>
						<div class="ap-solution-hero__choice-copy">
							<h2 class="ap-solution-hero__choice-title"><?php echo esc_html( $ap_choice['title'] ); ?></h2>
							<?php if ( '' !== $ap_choice['text'] ) : ?>
								<p class="ap-solution-hero__choice-text"><?php echo preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html( $ap_choice['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <strong> is added. ?></p>
							<?php endif; ?>
							<?php if ( '' !== $ap_choice['label'] && '' !== $ap_choice['url'] ) : ?>
								<a class="ap-solution-hero__choice-link" href="<?php echo esc_url( $ap_choice['url'] ); ?>"><span><?php echo esc_html( $ap_choice['label'] ); ?></span><svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false"><path d="M17.25 15.75L21 12L17.25 8.25M21 12H3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
