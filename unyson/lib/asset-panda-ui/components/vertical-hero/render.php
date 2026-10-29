<?php
/**
 * vertical-hero — the hero on every vertical product page.
 *
 * Matches the live #ap-hero (Fire, Police, Public Works, Local Government,
 * K-12, Higher Education): copy on the left over a composed stage. The stage is
 * one aspect-locked box sized in design units (--u = 1/1440 of its width), so
 * every layer scales together; each layer sits at a percentage of the stage.
 *
 * Pages differ only in their images, copy and the exact placement of each
 * layer, so placement arrives as numbers and every style attribute here is
 * built from numbers alone — author text never reaches a style attribute.
 *
 * The stage is decorative (aria-hidden): the cards illustrate the product and
 * repeat nothing the copy needs to say.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

static $ap_vhero_instance = 0;
++$ap_vhero_instance;
$uid = 'ap-vhero-' . $ap_vhero_instance;

$num = static function ( $value ) {
	return is_numeric( $value ) ? round( (float) $value, 3 ) : null;
};

// "--x:12.5%;--y:40%" from whichever of the named keys hold numbers.
$place = static function ( $geo, array $keys ) use ( $num ) {
	$out = array();
	foreach ( $keys as $key ) {
		$value = is_array( $geo ) && isset( $geo[ $key ] ) ? $num( $geo[ $key ] ) : null;
		if ( null !== $value ) {
			$out[] = '--' . $key . ':' . $value . '%';
		}
	}
	return implode( ';', $out );
};

$src = static function ( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|/)#i', $value ) ) {
		return $value;
	}
	return ap_ui_asset_url( 'vertical-hero', $value );
};

$lines_of = static function ( $lines ) use ( $num ) {
	$out = array();
	foreach ( is_array( $lines ) ? $lines : array() as $line ) {
		if ( ! is_array( $line ) || 4 !== count( $line ) ) {
			continue;
		}
		$pts = array_map( $num, array_values( $line ) );
		if ( in_array( null, $pts, true ) ) {
			continue;
		}
		$pts[] = (int) ceil( sqrt( pow( $pts[2] - $pts[0], 2 ) + pow( $pts[3] - $pts[1], 2 ) ) );
		$out[] = $pts;
	}
	return $out;
};

$orbs_of = static function ( $orbs ) use ( $num ) {
	$out = array();
	foreach ( is_array( $orbs ) ? $orbs : array() as $orb ) {
		if ( ! is_array( $orb ) || ! preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string) ( $orb['fill'] ?? '' ) ) ) {
			continue;
		}
		$geo = array( $num( $orb['cx'] ?? null ), $num( $orb['cy'] ?? null ), $num( $orb['rx'] ?? null ), $num( $orb['ry'] ?? null ) );
		if ( in_array( null, $geo, true ) ) {
			continue;
		}
		$geo[] = $orb['fill'];
		$out[] = $geo;
	}
	return $out;
};

$tones = array( 'amber', 'sky', 'indigo' );
$cards = array();
foreach ( is_array( $props['cards'] ) ? $props['cards'] : array() as $card ) {
	if ( count( $cards ) >= 3 || ! is_array( $card ) || '' === trim( (string) ( $card['title'] ?? '' ) ) ) {
		continue;
	}
	$cards[] = array(
		'label'        => trim( (string) ( $card['label'] ?? '' ) ),
		'title'        => trim( (string) $card['title'] ),
		'status_label' => trim( (string) ( $card['status_label'] ?? '' ) ),
		'tag'          => trim( (string) ( $card['tag'] ?? '' ) ),
		'icon'         => $src( $card['icon'] ?? '' ),
		'icon_svg'     => ap_ui_svg( $card['icon_svg'] ?? '' ),
		'avatar'       => $src( $card['avatar'] ?? '' ),
		'tone'         => $tones[ count( $cards ) ],
		'place'        => $place( $card, array( 'x', 'y', 'mx', 'my' ) ),
	);
}

$badges = array_values(
	array_filter(
		is_array( $props['badges'] ) ? $props['badges'] : array(),
		static function ( $b ) {
			return is_array( $b ) && ! empty( $b['image'] );
		}
	)
);

$layout    = is_array( $props['layout'] ) ? $props['layout'] : array();
$box       = array( 'x', 'y', 'w', 'h', 'mx', 'my', 'mw', 'mh' );
$lines     = $lines_of( $props['lines'] );
$lines_m   = $lines_of( $props['lines_m'] );
$orbs      = $orbs_of( $props['orbs'] );
$orbs_m    = $orbs_of( $props['orbs_m'] );
$dashboard = $src( $props['dashboard'] );
$person    = $src( $props['person'] );
// Emergency Preparedness: a phone-only composite of the cards, and a chip strip
// under the stage (live .epx-hero__mobcards and .epx-hero__strip).
$mobile_cards = $src( $props['mobile_cards'] );
$feats        = array_values(
	array_filter(
		is_array( $props['features'] ) ? $props['features'] : array(),
		static function ( $f ) {
			return is_array( $f ) && '' !== trim( (string) ( $f['label'] ?? '' ) );
		}
	)
);

$title   = trim( (string) $props['title'] );
$accent  = trim( (string) $props['title_accent'] );
$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );

// The accent is a slice of the title: escape first, then wrap that slice.
$title_html = ap_ui_brandmarks( ap_ui_lines( $title ) );
if ( '' !== $accent && false !== strpos( $title, $accent ) ) {
	$escaped_accent = ap_ui_brandmarks( esc_html( $accent ) );
	$title_html     = str_replace( $escaped_accent, '<span class="ap-vhero__accent">' . $escaped_accent . '</span>', $title_html );
}
$title_tag = '' !== $eyebrow ? 'h2' : 'h1';
?>
<?php
// Only "1440/640"-shaped ratios reach the style attribute; anything else keeps
// the stylesheet default.
$ap_ratio = preg_match( '#^\s*(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)\s*$#', (string) $props['ratio'], $ap_rm ) ? $ap_rm[1] . '/' . $ap_rm[2] : '';
$ap_style = '' !== $ap_ratio ? array( '--ap-vhero-ratio:' . $ap_ratio ) : array();
// Phone copy offset and the K-12 flowing phone hero, from numbers alone.
$ap_m_top = $num( $props['m_top'] );
if ( null !== $ap_m_top && $ap_m_top >= 0 && $ap_m_top <= 240 ) {
	$ap_style[] = '--m-top:' . $ap_m_top . 'px';
}
$ap_flow   = is_array( $props['m_flow'] ) ? $props['m_flow'] : array();
$ap_f_px   = $num( $ap_flow['pad_px'] ?? null );
$ap_f_vw   = $num( $ap_flow['pad_vw'] ?? null );
$ap_f_art  = $num( $ap_flow['art_vw'] ?? null );
$ap_m_flow = null !== $ap_f_px && null !== $ap_f_vw && null !== $ap_f_art && $ap_f_art > 0;
if ( $ap_m_flow ) {
	$ap_style[] = '--m-pad:calc(' . $ap_f_px . 'px + ' . $ap_f_vw . 'vw)';
	$ap_style[] = '--m-art:' . $ap_f_art . 'vw';
}
?>
<section class="ap-vhero<?php echo 'overlay' === $props['tablet'] ? ' ap-vhero--overlay' : ''; ?><?php echo $ap_m_flow ? ' ap-vhero--mflow' : ''; ?>"<?php echo $ap_style ? ' style="' . esc_attr( implode( ';', $ap_style ) ) . '"' : ''; ?>>
	<div class="ap-vhero__stage" aria-hidden="true">
		<?php if ( '' !== $dashboard ) : ?>
			<img class="ap-vhero__layer ap-vhero__dash" style="<?php echo esc_attr( $place( $layout['dash'] ?? null, $box ) ); ?>" src="<?php echo esc_url( $dashboard ); ?>" alt="" decoding="async" fetchpriority="high" />
			<div class="ap-vhero__layer ap-vhero__dashveil" style="<?php echo esc_attr( $place( $layout['dash'] ?? null, $box ) ); ?>"></div>
		<?php endif; ?>

		<?php foreach ( array( 'd' => $orbs, 'm' => $orbs_m ) as $variant => $set ) : ?>
			<?php if ( $set ) : ?>
				<svg class="ap-vhero__orbs ap-vhero__orbs--<?php echo esc_attr( $variant ); ?>" viewBox="<?php echo 'd' === $variant ? '0 0 1440 550' : '0 0 360 680'; ?>" preserveAspectRatio="none" focusable="false">
					<defs><filter id="<?php echo esc_attr( $uid . '-soft-' . $variant ); ?>" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="300" /></filter></defs>
					<g filter="url(#<?php echo esc_attr( $uid . '-soft-' . $variant ); ?>)">
						<?php foreach ( $set as $orb ) : ?>
							<ellipse cx="<?php echo esc_attr( $orb[0] ); ?>" cy="<?php echo esc_attr( $orb[1] ); ?>" rx="<?php echo esc_attr( $orb[2] ); ?>" ry="<?php echo esc_attr( $orb[3] ); ?>" fill="<?php echo esc_attr( $orb[4] ); ?>" />
						<?php endforeach; ?>
					</g>
				</svg>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ( '' !== $person ) : ?>
			<img class="ap-vhero__layer ap-vhero__person" style="<?php echo esc_attr( $place( $layout['person'] ?? null, $box ) ); ?>" src="<?php echo esc_url( $person ); ?>" alt="" decoding="async" />
		<?php endif; ?>
		<div class="ap-vhero__veil"></div>

		<?php foreach ( array( 'd' => $lines, 'm' => $lines_m ) as $variant => $set ) : ?>
			<?php if ( $set ) : ?>
				<svg class="ap-vhero__lines ap-vhero__lines--<?php echo esc_attr( $variant ); ?>" viewBox="<?php echo 'd' === $variant ? '0 0 1440 550' : '0 0 360 680'; ?>" preserveAspectRatio="none" focusable="false">
					<defs>
						<?php foreach ( $set as $n => $l ) : ?>
							<linearGradient id="<?php echo esc_attr( $uid . '-' . $variant . $n ); ?>" x1="<?php echo esc_attr( $l[0] ); ?>" y1="<?php echo esc_attr( $l[1] ); ?>" x2="<?php echo esc_attr( $l[2] ); ?>" y2="<?php echo esc_attr( $l[3] ); ?>" gradientUnits="userSpaceOnUse"><stop stop-color="#5044E1" /><stop offset="1" stop-color="#00A991" /></linearGradient>
						<?php endforeach; ?>
					</defs>
					<?php foreach ( $set as $n => $l ) : ?>
						<g class="ap-vhero__ln ap-vhero__ln--<?php echo esc_attr( (string) ( $n + 1 ) ); ?>">
							<line x1="<?php echo esc_attr( $l[0] ); ?>" y1="<?php echo esc_attr( $l[1] ); ?>" x2="<?php echo esc_attr( $l[2] ); ?>" y2="<?php echo esc_attr( $l[3] ); ?>" stroke="url(#<?php echo esc_attr( $uid . '-' . $variant . $n ); ?>)" stroke-width="<?php echo 'd' === $variant ? '1' : '.6'; ?>" style="<?php echo esc_attr( '--len:' . $l[4] ); ?>" />
							<circle cx="<?php echo esc_attr( $l[2] ); ?>" cy="<?php echo esc_attr( $l[3] ); ?>" r="<?php echo 'd' === $variant ? '2.67' : '1.59'; ?>" fill="url(#<?php echo esc_attr( $uid . '-' . $variant . $n ); ?>)" />
						</g>
					<?php endforeach; ?>
				</svg>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php foreach ( $cards as $card ) : ?>
			<div class="ap-vhero__card ap-vhero__card--<?php echo esc_attr( $card['tone'] ); ?>" style="<?php echo esc_attr( $card['place'] ); ?>">
				<div class="ap-vhero__flo">
					<?php if ( '' !== $card['icon_svg'] ) : ?>
						<span class="ap-vhero__ico ap-inline-icon" aria-hidden="true"><?php echo $card['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
					<?php elseif ( '' !== $card['icon'] ) : ?>
						<span class="ap-vhero__ico"><img src="<?php echo esc_url( $card['icon'] ); ?>" alt="" loading="lazy" decoding="async" /></span>
					<?php endif; ?>
					<span class="ap-vhero__body">
						<?php if ( '' !== $card['label'] ) : ?>
							<span class="ap-vhero__lab"><?php echo esc_html( $card['label'] ); ?></span>
						<?php endif; ?>
						<span class="ap-vhero__ttl"><?php echo esc_html( $card['title'] ); ?></span>
						<?php if ( '' !== $card['status_label'] ) : ?>
							<span class="ap-vhero__lab"><?php echo esc_html( $card['status_label'] ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $card['tag'] ) : ?>
							<span class="ap-vhero__tag"><?php echo esc_html( $card['tag'] ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( '' !== $card['avatar'] ) : ?>
						<img class="ap-vhero__avatar" src="<?php echo esc_url( $card['avatar'] ); ?>" alt="" loading="lazy" decoding="async" />
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php if ( ! empty( $layout['badge'] ) ) : ?>
			<div class="ap-vhero__badge" style="<?php echo esc_attr( $place( $layout['badge'], array( 'x', 'y', 'mx', 'my' ) ) ); ?>">
				<div class="ap-vhero__badge-flo">
					<span class="ap-vhero__badge-ring"></span>
					<span class="ap-vhero__badge-tile"><img src="<?php echo esc_url( ap_ui_asset_url( 'vertical-hero', 'panda-tile.svg' ) ); ?>" alt="" /></span>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $mobile_cards ) : ?>
			<img class="ap-vhero__layer ap-vhero__mobcards" style="<?php echo esc_attr( $place( $layout['mobcards'] ?? null, $box ) ); ?>" src="<?php echo esc_url( $mobile_cards ); ?>" alt="" loading="lazy" decoding="async" />
		<?php endif; ?>
	</div>

	<div class="ap-vhero__in">
		<div class="ap-vhero__container">
			<div class="ap-vhero__copy">
				<?php if ( '' !== $eyebrow ) : ?>
					<h1 class="ap-vhero__eyebrow"><?php echo esc_html( $eyebrow ); ?></h1>
				<?php endif; ?>
				<<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h1/h2. ?> class="ap-vhero__title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; only <br> and fixed spans are added. ?></<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h1/h2. ?>>
				<?php if ( '' !== $lede ) : ?>
					<p class="ap-vhero__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>
				<?php if ( ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) || $badges ) : ?>
					<div class="ap-vhero__cta">
						<?php if ( '' !== trim( (string) $props['cta_label'] ) && '' !== trim( (string) $props['cta_url'] ) ) : ?>
							<a class="ap-vhero__btn" href="<?php echo esc_url( $props['cta_url'] ); ?>"><?php echo esc_html( $props['cta_label'] ); ?></a>
						<?php endif; ?>
						<?php if ( $badges ) : ?>
							<div class="ap-vhero__certs" aria-label="Security and platform certifications">
								<?php foreach ( $badges as $badge ) : ?>
									<img src="<?php echo esc_url( $src( $badge['image'] ) ); ?>" alt="<?php echo esc_attr( $badge['alt'] ?? '' ); ?>" decoding="async" />
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( $feats ) : ?>
		<div class="ap-vhero__feats">
			<div class="ap-vhero__feats-track">
				<?php foreach ( array( false, true ) as $dup ) : ?>
					<?php foreach ( $feats as $feat ) : ?>
						<div class="ap-vhero__feat<?php echo $dup ? ' ap-vhero__feat--dup' : ''; ?>"<?php echo $dup ? ' aria-hidden="true"' : ''; ?>>
							<?php
							$feat_svg = ap_ui_svg( $feat['icon_svg'] ?? '' );
							if ( '' !== $feat_svg ) :
								?>
								<span class="ap-inline-icon" aria-hidden="true"><?php echo $feat_svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
							<?php elseif ( '' !== trim( (string) ( $feat['icon'] ?? '' ) ) ) : ?>
								<img src="<?php echo esc_url( $src( $feat['icon'] ) ); ?>" alt="" loading="lazy" decoding="async" />
							<?php endif; ?>
							<span><?php echo esc_html( $feat['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</section>
