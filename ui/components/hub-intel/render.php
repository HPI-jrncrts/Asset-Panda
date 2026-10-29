<?php
/**
 * hub-intel — the industry hubs' platform band.
 *
 * Matches live #ap-gov-intel (variant gov) and #ap-eduhub-ursa (variant eduhub)
 * markup class for class, so the ported sheets apply as they do on live: rows
 * of copy beside a composed scene, then a heading beside numbered feature
 * cards. Scene layers are placed from numbers; glow colours are validated as
 * rgb()/rgba() before use.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edu = 'eduhub' === $props['variant'];
$p   = $edu ? 'apu' : 'apg';

// "left:12.5%;top:40%" from whichever geometry keys hold numbers.
$ap_pct = static function ( $geo, array $map ) {
	$out = array();
	foreach ( $map as $key => $property ) {
		if ( is_array( $geo ) && isset( $geo[ $key ] ) && is_numeric( $geo[ $key ] ) ) {
			$out[] = $property . ':' . round( (float) $geo[ $key ], 3 ) . '%';
		}
	}
	return implode( ';', $out );
};

// Heading with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent, $class ) {
	$html   = ap_ui_lines( $title );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = esc_html( $accent );
		$html    = str_replace( $escaped, '<span class="' . esc_attr( $class ) . '">' . $escaped . '</span>', $html );
	}
	return $html;
};

// Live writes UrsaAI as bold text with a raised AI in the Government band's body,
// and with a small raised AI in the Education band's links.
$ap_ursa = static function ( $escaped, $markup ) {
	return preg_replace( '/\bUrsa\s?AI\b/', $markup, (string) $escaped );
};

$arrow = ap_ui_asset_url( 'hub-intel', 'icon-arrow.svg' );

$rows = array();
foreach ( is_array( $props['rows'] ) ? $props['rows'] : array() as $row ) {
	if ( ! is_array( $row ) || '' === trim( (string) ( $row['title'] ?? '' ) ) ) {
		continue;
	}
	$rows[] = array(
		'eyebrow' => trim( (string) ( $row['eyebrow'] ?? '' ) ),
		'title'   => (string) $row['title'],
		'accent'  => (string) ( $row['title_accent'] ?? '' ),
		'body'    => trim( (string) ( $row['body'] ?? '' ) ),
		'reverse' => ! empty( $row['reverse'] ),
		'links'   => array_values( array_filter( is_array( $row['links'] ?? null ) ? $row['links'] : array(), static function ( $l ) { return is_array( $l ) && '' !== trim( (string) ( $l['label'] ?? '' ) ) && '' !== trim( (string) ( $l['url'] ?? '' ) ); } ) ),
		'images'  => array_values( array_filter( is_array( $row['images'] ?? null ) ? $row['images'] : array(), static function ( $i ) { return is_array( $i ) && '' !== trim( (string) ( $i['image'] ?? '' ) ); } ) ),
		'glows'   => array_values( array_filter( is_array( $row['glows'] ?? null ) ? $row['glows'] : array(), static function ( $g ) { return is_array( $g ) && preg_match( '/^rgba?\(\s*[\d.\s,%]+\)$/i', (string) ( $g['color'] ?? '' ) ); } ) ),
	);
}

$features = array_values( array_filter( is_array( $props['features'] ) ? $props['features'] : array(), static function ( $f ) { return is_array( $f ) && '' !== trim( (string) ( $f['title'] ?? '' ) ); } ) );
$f_title  = trim( (string) $props['features_title'] );

if ( empty( $rows ) && empty( $features ) ) {
	return;
}

$scene = static function ( $row ) use ( $ap_pct, $edu, $p ) {
	if ( empty( $row['images'] ) && empty( $row['glows'] ) ) {
		return '';
	}
	$html = '<div class="' . ( $edu ? 'apu-media apu-scene' : 'apg-int-scene' ) . '" aria-hidden="true">';
	foreach ( $row['glows'] as $glow ) {
		$html .= '<span class="' . $p . '-glow" style="' . esc_attr( $ap_pct( $glow, array( 'x' => 'left', 'y' => 'top', 'w' => 'width', 'h' => 'height' ) ) . ';background:radial-gradient(circle,' . $glow['color'] . ' 0%,rgba(255,255,255,0) 70%)' ) . '"></span>';
	}
	foreach ( $row['images'] as $image ) {
		$class = trim( ( ! empty( $image['float'] ) ? $p . '-float ' : '' ) . ( ! $edu && ! empty( $image['crop'] ) ? 'apg-tile-crop' : '' ) );
		$html .= '<img' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' src="' . esc_url( $image['image'] ) . '" alt="" loading="eager" fetchpriority="low" decoding="async" style="' . esc_attr( $ap_pct( $image, array( 'x' => 'left', 'y' => 'top', 'w' => 'width' ) ) ) . '" />';
	}
	$html .= '</div>';
	// Live wraps the Education scene in a second .apu-media box.
	return $edu ? '<div class="apu-media">' . $html . '</div>' : $html;
};
?>
<section class="ap-hub-intel ap-hub-intel--<?php echo esc_attr( $edu ? 'eduhub' : 'gov' ); ?><?php echo ! $edu && 'plain' === $props['phone'] ? ' ap-hub-intel--phone-plain' : ''; ?>">
	<?php if ( $edu ) : ?>
	<div class="apu-container">
	<?php endif; ?>
	<?php foreach ( $rows as $row ) : ?>
		<?php ob_start(); ?>
		<div class="<?php echo esc_attr( $edu ? 'apu-text' : 'apg-int-copy' ); ?>">
			<?php if ( '' !== $row['eyebrow'] ) : ?>
				<p class="<?php echo esc_attr( $p ); ?>-eyebrow"><?php echo esc_html( $row['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="<?php echo esc_attr( $p ); ?>-h2"><?php echo $ap_accent( $row['title'], $row['accent'], $edu ? 'apu-grad' : 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			<?php if ( '' !== $row['body'] ) : ?>
				<p class="<?php echo esc_attr( $edu ? 'apu-body' : 'apg-int-body' ); ?>"><?php echo $edu ? ap_ui_lines( $row['body'] ) : $ap_ursa( ap_ui_lines( $row['body'] ), '<strong>Ursa<sup>AI</sup></strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only fixed markup is added. ?></p>
			<?php endif; ?>
			<?php if ( $row['links'] ) : ?>
				<div class="<?php echo esc_attr( $edu ? 'apu-ctas' : 'apg-int-ctas' ); ?>">
					<?php foreach ( $row['links'] as $link ) : ?>
						<?php if ( $edu ) : ?>
							<a class="apu-cta" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo $ap_ursa( esc_html( trim( (string) $link['label'] ) ), 'Ursa<span class="apu-sup">AI</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only a fixed span is added. ?><img src="<?php echo esc_url( $arrow ); ?>" alt="" decoding="async" /></a>
						<?php else : ?>
							<a class="apg-int-cta" href="<?php echo esc_url( $link['url'] ); ?>"><span><?php echo esc_html( trim( (string) $link['label'] ) ); ?></span><img src="<?php echo esc_url( $arrow ); ?>" alt="" decoding="async" /></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		$copy  = ob_get_clean();
		$media = $scene( $row );
		?>
		<div class="<?php echo esc_attr( $edu ? 'apu-block' : 'apg-int-row' . ( $row['reverse'] ? ' apg-int-row-rev' : '' ) ); ?>">
			<?php echo $row['reverse'] ? $copy . $media : $media . $copy; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above. ?>
		</div>
	<?php endforeach; ?>
	<?php if ( $features || '' !== $f_title ) : ?>
		<div class="<?php echo esc_attr( $edu ? 'apu-features' : 'apg-int-feats' ); ?>">
			<div class="<?php echo esc_attr( $edu ? 'apu-features-head' : 'apg-feats-head' ); ?>">
				<?php if ( '' !== trim( (string) $props['features_eyebrow'] ) ) : ?>
					<p class="<?php echo esc_attr( $p ); ?>-eyebrow"><?php echo esc_html( $props['features_eyebrow'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $f_title ) : ?>
					<h2 class="<?php echo esc_attr( $p ); ?>-h2"><?php echo $ap_accent( $f_title, $props['features_title_accent'], $edu ? 'apu-grad' : 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
				<?php endif; ?>
			</div>
			<div class="<?php echo esc_attr( $edu ? 'apu-features-list' : 'apg-feats-list' ); ?>">
				<?php foreach ( $features as $n => $feature ) : ?>
					<?php
					$accent = strtolower( trim( (string) ( $feature['accent'] ?? '' ) ) );
					$accent = preg_match( '/^#[0-9a-f]{3,8}$/', $accent ) ? $accent : '';
					$icon   = trim( (string) ( $feature['icon'] ?? '' ) );
					$alt    = isset( $feature['icon_alt'] ) ? (string) $feature['icon_alt'] : (string) ( $n + 1 );
					$label  = trim( (string) ( $feature['link_label'] ?? '' ) );
					$url    = trim( (string) ( $feature['link_url'] ?? '' ) );
					if ( $edu ) {
						$class = 'apu-fcard apu-fcard-' . ( 0 === $n % 2 ? '1' : '2' );
					} else {
						$class = 'apg-feat-card' . ( '#5044e1' === $accent ? ' apg-fc-indigo' : ( '#00a991' === $accent ? ' apg-fc-green' : '' ) );
					}
					$rule = ( '' !== $accent && ! in_array( $accent, array( '#5044e1', '#00a991' ), true ) ) ? ' style="' . esc_attr( 'border-color:' . $accent ) . '"' : '';
					?>
					<?php if ( $edu ) : ?>
						<div class="<?php echo esc_attr( $class ); ?>"<?php echo $rule; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>>
							<div class="apu-fcard-inner">
								<div class="apu-fcard-head">
									<?php if ( '' !== $icon ) : ?>
										<img class="apu-fcard-num" src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $alt ); ?>" decoding="async" />
									<?php endif; ?>
									<h3 class="apu-fcard-title"><?php echo esc_html( $feature['title'] ); ?></h3>
								</div>
								<?php if ( '' !== trim( (string) ( $feature['body'] ?? '' ) ) ) : ?>
									<p class="apu-fcard-desc"><?php echo ap_ui_lines( $feature['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
								<?php endif; ?>
								<?php if ( '' !== $label && '' !== $url ) : ?>
									<a class="apu-fcard-cta" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?><img src="<?php echo esc_url( $arrow ); ?>" alt="" decoding="async" /></a>
								<?php endif; ?>
							</div>
						</div>
					<?php else : ?>
						<div class="<?php echo esc_attr( $class ); ?>"<?php echo $rule; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>>
							<div class="apg-feat-head">
								<?php if ( '' !== $icon ) : ?>
									<img src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $alt ); ?>" decoding="async" />
								<?php endif; ?>
								<h3 class="apg-feat-title"><?php echo esc_html( $feature['title'] ); ?></h3>
							</div>
							<?php if ( '' !== trim( (string) ( $feature['body'] ?? '' ) ) ) : ?>
								<p class="apg-feat-body"><?php echo ap_ui_lines( $feature['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
							<?php endif; ?>
							<?php if ( '' !== $label && '' !== $url ) : ?>
								<a class="apg-int-cta" href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $label ); ?></span><img src="<?php echo esc_url( $arrow ); ?>" alt="" decoding="async" /></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $edu ) : ?>
	</div>
	<?php endif; ?>
</section>
