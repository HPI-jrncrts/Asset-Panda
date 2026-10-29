<?php
/**
 * capability-rows — outlined capability rows with explore links.
 *
 * Matches the live #ap-gov-solution. Each row takes its outline and tint from
 * its tone; the link arrow is a mask coloured by that tone.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

// Title with a gradient slice: escape first, then wrap the slice.
$ap_accent = static function ( $title, $accent, $class ) {
	$html   = ap_ui_brandmarks( ap_ui_lines( $title ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent && false !== strpos( (string) $title, $accent ) ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$html    = str_replace( $escaped, '<span class="' . esc_attr( $class ) . '">' . $escaped . '</span>', $html );
	}
	return $html;
};

// An icon from inline SVG (sanitised) or an image URL.
$ap_icon = static function ( $item, $class = '' ) {
	$svg = ap_ui_svg( $item['icon_svg'] ?? '' );
	if ( '' !== $svg ) {
		return '<span class="' . esc_attr( trim( 'ap-inline-icon ' . $class ) ) . '" aria-hidden="true">' . $svg . '</span>';
	}
	$icon = trim( (string) ( $item['icon'] ?? '' ) );
	return '' !== $icon ? '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $icon ) . '" alt="" loading="lazy" decoding="async" />' : '';
};

$tones = array( 'indigo', 'green', 'yellow', 'blue', 'red', 'cyan' );
$items = array_values( array_filter( is_array( $props['items'] ) ? $props['items'] : array(), static function ( $i ) { return is_array( $i ) && '' !== trim( (string) ( $i['title'] ?? '' ) ); } ) );
if ( empty( $items ) ) {
	return;
}

/*
 * Education hub: live #ap-eduhub-solution, ported whole with its own class names
 * (.aps-*) — tinted rows numbered by tone (.aps-row-1 indigo … -6 cyan) and the
 * closing line with its button.
 */
if ( 'eduhub' === $props['variant'] ) :
	?>
<section class="ap-capability-rows ap-capability-rows--eduhub">
	<div class="aps-container">
		<div class="aps-header">
			<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
				<p class="aps-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
			<?php endif; ?>
			<h2 class="aps-h2"><?php echo $ap_accent( $props['title'], $props['title_accent'], 'aps-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
		</div>
		<div class="aps-rows">
			<?php foreach ( $items as $n => $item ) : ?>
				<?php
				$ap_tone = array_search( $item['tone'] ?? '', $tones, true );
				$ap_slot = false === $ap_tone ? ( $n % 6 ) + 1 : $ap_tone + 1;
				?>
				<div class="aps-row aps-row-<?php echo esc_attr( (string) $ap_slot ); ?>">
					<div class="aps-row-head">
						<?php echo $ap_icon( $item, 'aps-row-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
						<h3 class="aps-row-title"><?php echo esc_html( $item['title'] ); ?></h3>
					</div>
					<div class="aps-row-body">
						<?php if ( '' !== trim( (string) ( $item['body'] ?? '' ) ) ) : ?>
							<p class="aps-row-desc"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
						<?php if ( '' !== trim( (string) ( $item['link_label'] ?? '' ) ) && '' !== trim( (string) ( $item['url'] ?? '' ) ) ) : ?>
							<a class="aps-row-cta" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['link_label'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== trim( (string) $props['ender_title'] ) || '' !== trim( (string) $props['ender_body'] ) ) : ?>
			<div class="aps-ender">
				<div class="aps-ender-text">
					<?php if ( '' !== trim( (string) $props['ender_title'] ) ) : ?>
						<p class="aps-ender-title"><?php echo $ap_accent( $props['ender_title'], $props['ender_accent'], 'aps-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $props['ender_body'] ) ) : ?>
						<p class="aps-ender-desc"><?php echo esc_html( $props['ender_body'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( '' !== trim( (string) $props['ender_label'] ) && '' !== trim( (string) $props['ender_url'] ) ) : ?>
					<a class="aps-ender-btn" href="<?php echo esc_url( $props['ender_url'] ); ?>"><?php echo esc_html( $props['ender_label'] ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
	<?php
	return;
endif;
?>
<section class="ap-capability-rows<?php echo '900' === (string) $props['stack'] ? ' ap-capability-rows--stack-900' : ''; ?><?php echo 'readiness' === $props['variant'] ? ' ap-capability-rows--readiness' : ''; ?>">
	<div class="apg-sol-head">
		<?php if ( '' !== trim( (string) $props['eyebrow'] ) ) : ?>
			<p class="apg-eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
		<?php endif; ?>
		<h2 class="apg-h2"><?php echo $ap_accent( $props['title'], $props['title_accent'], 'apg-h2-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
		<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
			<p class="apg-sol-lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
		<?php endif; ?>
	</div>
	<div class="apg-sol-rows<?php echo $props['plain'] ? ' apg-sol-rows--plain' : ''; ?>">
		<?php foreach ( $items as $i => $item ) : ?>
			<?php $tone = in_array( $item['tone'] ?? '', $tones, true ) ? $item['tone'] : $tones[ $i % count( $tones ) ]; ?>
			<div class="apg-sol-row apg-row-<?php echo esc_attr( $tone ); ?>">
				<div class="apg-sol-left">
					<?php if ( '' !== trim( (string) ( $item['number'] ?? '' ) ) ) : ?>
						<span class="apg-sol-num" aria-hidden="true"><?php echo esc_html( $item['number'] ); ?></span>
					<?php else : ?>
						<?php echo $ap_icon( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?>
					<?php endif; ?>
					<h3 class="apg-sol-title"><?php echo esc_html( $item['title'] ); ?></h3>
				</div>
				<div class="apg-sol-right">
					<?php if ( ! empty( $item['body'] ) ) : ?>
						<p class="apg-sol-body"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped. ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $item['link_label'] ) && ! empty( $item['url'] ) ) : ?>
						<a class="apg-sol-cta" href="<?php echo esc_url( $item['url'] ); ?>"><span><?php echo esc_html( $item['link_label'] ); ?></span></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( '' !== trim( (string) $props['ender_title'] ) ) : ?>
		<div class="apg-sol-ender">
			<div class="apg-ender-text">
				<p class="apg-ender-title"><?php echo $ap_accent( $props['ender_title'], $props['ender_accent'], 'apg-ender-grad' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></p>
				<?php if ( '' !== trim( (string) $props['ender_body'] ) ) : ?>
					<p class="apg-ender-body"><?php echo esc_html( $props['ender_body'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== trim( (string) $props['ender_label'] ) && '' !== trim( (string) $props['ender_url'] ) ) : ?>
				<a class="apg-btn" href="<?php echo esc_url( $props['ender_url'] ); ?>"><?php echo esc_html( $props['ender_label'] ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
