<?php
/**
 * problem-callouts — the "here is what goes wrong today" section.
 *
 * Matches the live `.ap-problem` section: centred copy, a photo ringed by
 * floating chips, a closing note, and a CTA bar. The first five chips take the
 * five placements the design defines; any beyond that stack underneath. On
 * phones every chip sits in one column under the photo.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tones = array( 'indigo', 'rose', 'mint', 'slate', 'amber' );

$cards = array();
/*
 * Each vertical page places its cards differently in each width band (live
 * layers its own overrides at 1181, 1025, 901 and 769px). A card's bands carry
 * live's computed values for that band; they reach CSS as custom properties,
 * and only values of a known shape (a length, a keyword) get through.
 */
$ap_bands = static function ( $bands ) {
	if ( ! is_array( $bands ) ) {
		return '';
	}
	$len  = '/^(?:auto|max-content|min-content|fit-content|none|-?\d+(?:\.\d+)?(?:px|%)|0)$/';
	$keys = array(
		'pos'  => '/^(?:absolute|relative|static)$/',
		'l'    => $len,
		'r'    => $len,
		't'    => $len,
		'w'    => $len,
		'minw' => $len,
		'maxw' => $len,
		'ws'   => '/^(?:nowrap|normal)$/',
		'fg'   => '/^\d+(?:\.\d+)?$/',
		'fb'   => $len,
	);
	$out  = array();
	foreach ( array( 1181, 1025, 901, 769 ) as $bp ) {
		$band = $bands[ (string) $bp ] ?? null;
		if ( ! is_array( $band ) ) {
			continue;
		}
		foreach ( $keys as $key => $shape ) {
			$value = trim( (string) ( $band[ $key ] ?? '' ) );
			if ( '' !== $value && preg_match( $shape, $value ) ) {
				$out[] = '--b' . $bp . '-' . $key . ':' . $value;
			}
		}
	}
	return implode( ';', $out );
};

foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( ! is_array( $card ) || '' === trim( (string) ( $card['text'] ?? '' ) ) ) {
		continue;
	}
	$tone    = trim( (string) ( $card['tone'] ?? '' ) );
	$cards[] = array(
		'text'     => trim( (string) $card['text'] ),
		'icon'     => trim( (string) ( $card['icon'] ?? '' ) ),
		'icon_svg' => ap_ui_svg( $card['icon_svg'] ?? '' ),
		'icon_alt' => (string) ( $card['icon_alt'] ?? '' ),
		// Fall back to the design's own order of tones.
		'tone'     => in_array( $tone, $tones, true ) ? $tone : $tones[ count( $cards ) % count( $tones ) ],
		// A page's own placement on the stage (px), used from 1181px.
		'x'        => isset( $card['x'] ) && is_numeric( $card['x'] ) ? round( (float) $card['x'], 1 ) : null,
		'y'        => isset( $card['y'] ) && is_numeric( $card['y'] ) ? round( (float) $card['y'], 1 ) : null,
		'w'        => isset( $card['w'] ) && is_numeric( $card['w'] ) && $card['w'] > 0 ? round( (float) $card['w'], 1 ) : null,
		'bands'    => $ap_bands( $card['bands'] ?? null ),
	);
}

$title      = trim( (string) $props['title'] );
$eyebrow    = trim( (string) $props['eyebrow'] );
$lede       = trim( (string) $props['lede'] );
$kicker     = trim( (string) $props['kicker'] );
$note       = trim( (string) $props['note'] );
$image      = trim( (string) $props['image'] );
$background = trim( (string) $props['background'] );
$cta_title  = trim( (string) $props['cta_title'] );
$cta_accent = trim( (string) $props['cta_accent'] );
$cta_sub    = trim( (string) $props['cta_sub'] );
$cta_logo   = trim( (string) $props['cta_logo'] );
if ( '' !== $cta_logo && ! preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|/)#i', $cta_logo ) ) {
	$cta_logo = ap_ui_asset_url( 'problem-callouts', $cta_logo );
}
$cta_label  = trim( (string) $props['cta_label'] );
$cta_url    = trim( (string) $props['cta_url'] );
$has_cta    = '' !== $cta_title || '' !== $cta_sub || ( '' !== $cta_label && '' !== $cta_url );
?>
<?php
// Phone spacing differs by page (live splits it between the theme's grid and
// each page's flex column): whole pixel values only.
$ap_phone = array();
foreach ( array( 'stage_mt' => '--ph-stage-mt', 'gap' => '--ph-gap', 'note_mt' => '--ph-note-mt', 'pb_m' => '--ph-pb-m', 'pb_s' => '--ph-pb-s' ) as $key => $var ) {
	$value = is_array( $props['phone'] ) ? ( $props['phone'][ $key ] ?? null ) : null;
	if ( is_numeric( $value ) && abs( (float) $value ) <= 200 ) {
		$ap_phone[] = $var . ':' . round( (float) $value ) . 'px';
	}
}
?>
<section class="ap-problem-callouts<?php echo $props['cta_accent_inline'] ? ' ap-problem-callouts--accent-inline' : ''; ?><?php echo $props['cta_nowrap'] ? ' ap-problem-callouts--cta-nowrap' : ''; ?>"<?php echo $ap_phone ? ' style="' . esc_attr( implode( ';', $ap_phone ) ) . '"' : ''; ?>>
	<?php if ( '' !== $background ) : ?>
		<img class="ap-problem-callouts__bg" src="<?php echo esc_url( $background ); ?>" alt="" aria-hidden="true" loading="eager" fetchpriority="low" decoding="async" />
	<?php endif; ?>

	<div class="ap-problem-callouts__inner">
		<div class="ap-problem-callouts__header"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-problem-callouts__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-problem-callouts__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-problem-callouts__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
			<?php endif; ?>
			<?php if ( '' !== $kicker ) : ?>
				<p class="ap-problem-callouts__kicker"><?php echo esc_html( $kicker ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $image || ! empty( $cards ) ) : ?>
			<div class="ap-problem-callouts__stage">
				<?php if ( '' !== $image ) : ?>
					<img class="ap-problem-callouts__photo" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $props['image_alt'] ); ?>" loading="lazy" decoding="async" />
				<?php endif; ?>

				<?php foreach ( $cards as $card ) : ?>
					<?php
					$ap_placed = null !== $card['x'] && null !== $card['y'];
					$ap_place  = $ap_placed ? '--card-x:' . $card['x'] . 'px;--card-y:' . $card['y'] . 'px' . ( null !== $card['w'] ? ';--card-w:' . $card['w'] . 'px' : '' ) : '';
					$ap_banded = '' !== $card['bands'];
					$ap_style  = implode( ';', array_filter( array( $ap_place, $card['bands'] ) ) );
					?>
					<div class="ap-problem-callouts__card ap-problem-callouts__card--<?php echo esc_attr( $card['tone'] ); ?><?php echo $ap_placed ? ' ap-problem-callouts__card--placed' : ''; ?><?php echo $ap_banded ? ' ap-problem-callouts__card--banded' : ''; ?>"<?php echo '' !== $ap_style ? ' style="' . esc_attr( $ap_style ) . '"' : ''; ?>>
						<?php if ( '' !== $card['icon_svg'] ) : ?>
							<span class="ap-problem-callouts__ico" aria-hidden="true"><?php echo $card['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
						<?php elseif ( '' !== $card['icon'] ) : ?>
							<span class="ap-problem-callouts__ico">
								<img src="<?php echo esc_url( $card['icon'] ); ?>" alt="<?php echo esc_attr( $card['icon_alt'] ); ?>" loading="lazy" decoding="async" />
							</span>
						<?php endif; ?>
						<p><?php echo ap_ui_brandmarks( esc_html( $card['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $note ) : ?>
			<p class="ap-problem-callouts__note"><?php echo ap_ui_brandmarks( ap_ui_lines( $note ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
		<?php endif; ?>

		<?php if ( $has_cta ) : ?>
			<div class="ap-problem-callouts__cta"<?php echo $props['reveal'] ? ' data-ap-reveal' : ''; ?>>
				<div class="ap-problem-callouts__cta-body">
					<?php if ( '' !== $cta_logo ) : ?>
						<span class="ap-problem-callouts__cta-logo">
							<img src="<?php echo esc_url( $cta_logo ); ?>" alt="" loading="lazy" decoding="async" />
						</span>
					<?php endif; ?>
					<div>
						<?php if ( '' !== $cta_title || '' !== $cta_accent ) : ?>
							<h3 class="ap-problem-callouts__cta-title">
								<?php echo ap_ui_brandmarks( esc_html( $cta_title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?>
								<?php if ( '' !== $cta_accent ) : ?>
									<span class="ap-problem-callouts__cta-accent"><?php echo ap_ui_brandmarks( esc_html( $cta_accent ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
								<?php endif; ?>
							</h3>
						<?php endif; ?>
						<?php if ( '' !== $cta_sub ) : ?>
							<p class="ap-problem-callouts__cta-sub"><?php echo esc_html( $cta_sub ); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( '' !== $cta_label && '' !== $cta_url ) : ?>
					<a class="ap-problem-callouts__btn" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
