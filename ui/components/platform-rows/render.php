<?php
/**
 * platform-rows — the numbered capability rows on the Asset Intelligence
 * Platform page.
 *
 * Matches the live .apx-sol: a centred heading (eyebrow, title with a gradient
 * slice, lede) over rows ruled in violet, each with its number and title on the
 * left and the body plus an optional text link on the right. Inner class names
 * mirror live's (.apx-sol__x → .ap-platform-rows__x), so the ported sheet stays
 * diffable. The heading is a <div>: the theme pins every <header> on phones.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array_values(
	array_filter(
		is_array( $props['items'] ) ? $props['items'] : array(),
		static function ( $i ) {
			return is_array( $i ) && '' !== trim( (string) ( $i['title'] ?? '' ) );
		}
	)
);
if ( empty( $items ) ) {
	return;
}

// Title: each line break becomes live's desktop-only <br class="lb">; the
// accent slice is wrapped after escaping.
$ap_title = static function ( $title, $accent ) {
	$parts  = array_map( 'esc_html', preg_split( '/\r\n|\r|\n/', trim( (string) $title ) ) );
	$html   = ap_ui_brandmarks( implode( '<br class="lb" /> ', $parts ) );
	$accent = trim( (string) $accent );
	if ( '' !== $accent ) {
		$escaped = ap_ui_brandmarks( esc_html( $accent ) );
		$pos     = strrpos( $html, $escaped );
		if ( false !== $pos ) {
			$html = substr_replace( $html, '<span class="g">' . $escaped . '</span>', $pos, strlen( $escaped ) );
		}
	}
	return $html;
};

// Row title with its leading keyword in teal.
$ap_h3 = static function ( $title, $keyword ) {
	$title   = trim( (string) $title );
	$keyword = trim( (string) $keyword );
	if ( '' !== $keyword && 0 === strpos( $title, $keyword ) ) {
		return '<span class="k">' . esc_html( $keyword ) . '</span> ' . esc_html( ltrim( substr( $title, strlen( $keyword ) ) ) );
	}
	return esc_html( $title );
};

$eyebrow = trim( (string) $props['eyebrow'] );
$lede    = trim( (string) $props['lede'] );
?>
<section class="ap-platform-rows">
	<div class="ap-platform-rows__wrap">
		<div class="ap-platform-rows__head">
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="ap-platform-rows__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>
			<h2 class="ap-platform-rows__title"><?php echo $ap_title( $props['title'], $props['title_accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h2>
			<?php if ( '' !== $lede ) : ?>
				<p class="ap-platform-rows__sub"><?php echo ap_ui_brandmarks( esc_html( $lede ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
			<?php endif; ?>
		</div>

		<div class="ap-platform-rows__rows">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$label = trim( (string) ( $item['link_label'] ?? '' ) );
				$url   = trim( (string) ( $item['url'] ?? '' ) );
				?>
				<div class="ap-platform-rows__row">
					<div class="ap-platform-rows__lead">
						<?php if ( '' !== trim( (string) ( $item['number'] ?? '' ) ) ) : ?>
							<p class="ap-platform-rows__num"><?php echo esc_html( $item['number'] ); ?></p>
						<?php endif; ?>
						<h3 class="ap-platform-rows__h3"><?php echo $ap_h3( $item['title'], $item['keyword'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper. ?></h3>
					</div>
					<div class="ap-platform-rows__col">
						<?php if ( '' !== trim( (string) ( $item['body'] ?? '' ) ) ) : ?>
							<p class="ap-platform-rows__p"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>
						<?php if ( '' !== $label && '' !== $url ) : ?>
							<a class="ap-platform-rows__cta" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?> <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.75 16L21.5 12.25L17.75 8.5M21.5 12.25H3.5" stroke="#00A991" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
						<?php endif; ?>
						<?php foreach ( ( isset( $item['links'] ) && is_array( $item['links'] ) ) ? $item['links'] : array() as $extra ) : ?>
							<?php
							$extra_label = is_array( $extra ) ? trim( (string) ( $extra['label'] ?? '' ) ) : '';
							$extra_url   = is_array( $extra ) ? trim( (string) ( $extra['url'] ?? '' ) ) : '';
							if ( '' === $extra_label || '' === $extra_url ) {
								continue;
							}
							?>
							<a class="ap-platform-rows__cta" href="<?php echo esc_url( $extra_url ); ?>"><?php echo esc_html( $extra_label ); ?> <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.75 16L21.5 12.25L17.75 8.5M21.5 12.25H3.5" stroke="#00A991" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
