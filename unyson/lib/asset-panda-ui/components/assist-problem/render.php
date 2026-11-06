<?php
/**
 * assist-problem — the help desk problem section.
 *
 * Matches the live .epx-prob on /solutions/help-desk-ticketing/: a centred
 * heading over a row holding a fixed 606x400 art frame (blurred backdrop, a
 * ring, the panda seen through a window, the UrsaAI orb and floating feature
 * chips at their design coordinates) beside the copy column. Below 1181px the
 * row stacks; below 701px the copy moves above the frame, which scales as one
 * piece and swaps the chips to their phone coordinates (mx/my).
 *
 * The orb's sphere and wordmark are drawn by the kit (assets/), the photos and
 * the ring come from props. Pieces reveal through a CSS scroll timeline, as on
 * live.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// "UrsaAI" in already-escaped copy → live's brand mark: a gradient "Ursa" and a
// solid superscript "AI". Only the fixed markup below is added.
$ap_ursa = static function ( $escaped ) {
	return preg_replace( '/\bUrsa\s?AI\b/', '<span class="ap-assist-problem__ursa"><span class="ap-assist-problem__g">Ursa</span><sup>AI</sup></span>', (string) $escaped );
};

// Heading with a gradient slice: escape first, then wrap the first match.
$ap_accent = static function ( $title, $accent ) use ( $ap_ursa ) {
	$html    = ap_ui_lines( $title );
	$escaped = esc_html( trim( (string) $accent ) );
	$at      = '' !== $escaped ? strpos( $html, $escaped ) : false;
	if ( false !== $at ) {
		$html = substr_replace( $html, '<span class="ap-assist-problem__g">' . $escaped . '</span>', $at, strlen( $escaped ) );
	}
	return $ap_ursa( $html );
};

// A design coordinate in px for a custom property, or '' when not a number.
$ap_px = static function ( $value ) {
	return is_numeric( $value ) ? round( (float) $value, 2 ) . 'px' : '';
};

$title   = trim( (string) $props['title'] );
$eyebrow = trim( (string) $props['eyebrow'] );
$sub     = trim( (string) $props['sub_title'] );
$paras   = array_values( array_filter( array_map( 'trim', preg_split( '/\R+/', trim( (string) $props['body'] ) ) ), 'strlen' ) );
$bullets = array();
foreach ( is_array( $props['bullets'] ) ? $props['bullets'] : array() as $bullet ) {
	$text = trim( (string) ( is_array( $bullet ) ? ( $bullet['text'] ?? '' ) : $bullet ) );
	if ( '' !== $text ) {
		$bullets[] = $text;
	}
}
$chips = array();
foreach ( is_array( $props['chips'] ) ? $props['chips'] : array() as $chip ) {
	if ( ! is_array( $chip ) || '' === trim( (string) ( $chip['label'] ?? '' ) ) ) {
		continue;
	}
	$vars = array();
	foreach ( array( 'x' => '--chip-x', 'y' => '--chip-y', 'w' => '--chip-w', 'mx' => '--chip-mx', 'my' => '--chip-my' ) as $key => $var ) {
		$px = $ap_px( $chip[ $key ] ?? null );
		if ( '' !== $px ) {
			$vars[] = $var . ':' . $px;
		}
	}
	$chips[] = array(
		'label' => trim( (string) $chip['label'] ),
		'icon'  => ap_ui_svg( $chip['icon_svg'] ?? '' ),
		'style' => implode( ';', $vars ),
	);
}

$blur   = trim( (string) $props['blur'] );
$ring   = trim( (string) $props['ring'] );
$panda  = trim( (string) $props['panda'] );
$orb    = trim( (string) $props['orb'] );
$sphere = ap_ui_asset_url( 'assist-problem', 'sphere.svg' );
$mark   = ap_ui_asset_url( 'assist-problem', 'ursa-logo.svg' );
$art    = '' !== $blur || '' !== $ring || '' !== $panda || '' !== $orb || $chips;
// id on the section (the page's own anchor), letters, digits, hyphens; empty renders none.
$anchor = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
// heading "beside": the eyebrow and heading open the copy column, beside the art, instead of sitting centred above the row.
$beside = 'beside' === $props['heading'];
$head   = '<div class="ap-assist-problem__head">'
	. ( '' !== $eyebrow ? '<p class="ap-assist-problem__eyebrow">' . esc_html( $eyebrow ) . '</p>' : '' )
	. '<h2 class="ap-assist-problem__title">' . $ap_accent( $title, $props['title_accent'] ) . '</h2></div>';
?>
<section class="ap-assist-problem<?php echo 'md' === $props['body_size'] ? ' ap-assist-problem--body-md' : ''; ?><?php echo $beside ? ' ap-assist-problem--head-beside' : ''; ?>"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="ap-assist-problem__wrap">
<?php if ( ! $beside ) : ?>
		<div class="ap-assist-problem__head ap-assist-problem__rev">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-assist-problem__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-assist-problem__title"><?php echo $ap_accent( $title, $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and fixed spans are added. ?></h2>
		</div>
<?php endif; ?>
		<div class="ap-assist-problem__row">
			<?php if ( $art ) : ?>
				<div class="ap-assist-problem__artwrap">
					<div class="ap-assist-problem__art ap-assist-problem__rev">
						<?php if ( '' !== $blur ) : ?>
							<img class="ap-assist-problem__blur" src="<?php echo esc_url( $blur ); ?>" alt="" width="672" height="470" loading="lazy" decoding="async" />
						<?php endif; ?>
						<?php if ( '' !== $ring ) : ?>
							<img class="ap-assist-problem__ring" src="<?php echo esc_url( $ring ); ?>" alt="" width="595" height="397" loading="lazy" decoding="async" />
						<?php endif; ?>
						<?php if ( '' !== $panda ) : ?>
							<span class="ap-assist-problem__pandabox"><img class="ap-assist-problem__panda" src="<?php echo esc_url( $panda ); ?>" alt="<?php echo esc_attr( $props['panda_alt'] ); ?>" width="610" height="407" loading="lazy" decoding="async" /></span>
						<?php endif; ?>
						<span class="ap-assist-problem__orb">
							<?php if ( '' !== $ring ) : ?>
								<img class="ap-assist-problem__orbring" src="<?php echo esc_url( $ring ); ?>" alt="" width="232" height="155" loading="lazy" decoding="async" />
							<?php endif; ?>
							<?php if ( '' !== $orb ) : ?>
								<span class="ap-assist-problem__orbimg"><img src="<?php echo esc_url( $orb ); ?>" alt="" width="144" height="100" loading="lazy" decoding="async" /></span>
							<?php endif; ?>
							<?php if ( '' !== $sphere ) : ?>
								<img class="ap-assist-problem__sphere" src="<?php echo esc_url( $sphere ); ?>" alt="" width="87" height="86" loading="lazy" decoding="async" />
							<?php endif; ?>
							<?php if ( '' !== $mark ) : ?>
								<img class="ap-assist-problem__logo" src="<?php echo esc_url( $mark ); ?>" alt="" width="52" height="17" loading="lazy" decoding="async" />
							<?php endif; ?>
						</span>
						<?php foreach ( $chips as $i => $chip ) : ?>
							<span class="ap-assist-problem__fc ap-assist-problem__rev<?php echo $i < 4 ? ' ap-assist-problem__rev--d' . esc_attr( (string) ( $i + 1 ) ) : ''; ?>"<?php echo '' !== $chip['style'] ? ' style="' . esc_attr( $chip['style'] ) . '"' : ''; ?>><span class="ap-assist-problem__flo"><?php echo $chip['icon']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?><?php echo esc_html( $chip['label'] ); ?></span></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $beside || '' !== $sub || $paras || $bullets ) : ?>
				<div class="ap-assist-problem__copy ap-assist-problem__rev">
<?php echo $beside ? $head : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built above from escaped parts. ?>
					<?php if ( '' !== $sub ) : ?>
						<h3 class="ap-assist-problem__subtitle"><?php echo '' !== trim( (string) $props['sub_title_accent'] ) ? $ap_accent( $sub, $props['sub_title_accent'] ) : $ap_ursa( ap_ui_lines( $sub ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and fixed spans are added. ?></h3>
					<?php endif; ?>
					<?php foreach ( $paras as $para ) : ?>
						<p><?php echo $ap_ursa( esc_html( $para ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only a fixed span is added. ?></p>
					<?php endforeach; ?>
					<?php if ( $bullets ) : ?>
						<ul>
							<?php foreach ( $bullets as $bullet ) : ?>
								<li><?php echo $ap_ursa( esc_html( $bullet ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only a fixed span is added. ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
