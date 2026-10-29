<?php
/**
 * sticky-list — sticky heading beside ruled capability rows.
 *
 * Matches the live `.ap-features` section: from 1025px the heading sticks
 * while the rows scroll past it; below that it sits above them.
 *
 * Each row carries its own accent colour, which paints the left rule and the
 * check marks. The colour arrives as a prop, so it is validated as a hex
 * colour before it reaches a style attribute.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) ) {
		continue;
	}
	$checks = array();
	foreach ( is_array( $item['checks'] ?? null ) ? $item['checks'] : array() as $check ) {
		$check = trim( (string) $check );
		if ( '' !== $check ) {
			$checks[] = $check;
		}
	}
	$accent  = trim( (string) ( $item['accent'] ?? '' ) );
	$items[] = array(
		'title'    => trim( (string) $item['title'] ),
		'icon'     => trim( (string) ( $item['icon'] ?? '' ) ),
		'icon_svg' => ap_ui_svg( $item['icon_svg'] ?? '' ),
		'icon_alt' => (string) ( $item['icon_alt'] ?? '' ),
		'accent'   => preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $accent ) ? $accent : '',
		'num'      => trim( (string) ( $item['num'] ?? '' ) ),
		'checks'   => $checks,
		'body'     => trim( (string) ( $item['body'] ?? '' ) ),
		'link'     => trim( (string) ( $item['link_label'] ?? '' ) ),
		'url'      => trim( (string) ( $item['link_url'] ?? '' ) ),
	);
}

if ( empty( $items ) ) {
	return;
}

$title   = trim( (string) $props['title'] );
$eyebrow = trim( (string) $props['eyebrow'] );
?>
<?php
// Police spaces its heading its own way below 1025px; the space above and
// below the heading per step arrives as whole pixels.
$ap_hs = array();
foreach ( array( 1024, 991, 768, 560 ) as $bp ) {
	$pair = is_array( $props['head_space'] ) ? ( $props['head_space'][ (string) $bp ] ?? null ) : null;
	if ( is_array( $pair ) && isset( $pair[0], $pair[1] ) && is_numeric( $pair[0] ) && is_numeric( $pair[1] ) && abs( (float) $pair[0] ) <= 200 && abs( (float) $pair[1] ) <= 200 ) {
		$ap_hs[] = '--hs-' . $bp . '-t:' . round( (float) $pair[0] ) . 'px;--hs-' . $bp . '-b:' . round( (float) $pair[1] ) . 'px';
	}
}
?>
<section class="ap-sticky-list<?php echo '900' === (string) $props['stack'] ? ' ap-sticky-list--stack-900' : ''; ?><?php echo 'solution' === $props['variant'] ? ' ap-sticky-list--solution' : ''; ?><?php echo $ap_hs ? ' ap-sticky-list--hs' : ''; ?>"<?php echo $ap_hs ? ' style="' . esc_attr( implode( ';', $ap_hs ) ) . '"' : ''; ?>>
	<div class="ap-sticky-list__inner">
		<div class="ap-sticky-list__grid">
			<div class="ap-sticky-list__head">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="ap-sticky-list__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<h2 class="ap-sticky-list__title"><?php echo ap_ui_brandmarks( ap_ui_lines( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></h2>
				<?php if ( '' !== trim( (string) $props['lede'] ) ) : ?>
					<p class="ap-sticky-list__lede"><?php echo ap_ui_brandmarks( ap_ui_lines( $props['lede'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; only <br> and a fixed span are added. ?></p>
				<?php endif; ?>
			</div>

			<div class="ap-sticky-list__items">
				<?php foreach ( $items as $i => $item ) : ?>
					<article
						class="ap-sticky-list__card"
						<?php if ( '' !== $item['accent'] ) : ?>
							style="<?php echo esc_attr( '--ap-sticky-list-accent:' . $item['accent'] ); ?>"
						<?php endif; ?>
						<?php if ( $props['reveal'] ) : ?>
							data-ap-reveal data-ap-reveal-delay="<?php echo esc_attr( (string) ( ( $i % 4 ) + 1 ) ); ?>"
						<?php endif; ?>
					>
						<div class="ap-sticky-list__top">
							<?php if ( '' !== $item['num'] ) : ?>
								<span class="ap-sticky-list__num" aria-hidden="true"><?php echo esc_html( $item['num'] ); ?></span>
							<?php elseif ( '' !== $item['icon_svg'] ) : ?>
								<span class="ap-sticky-list__icon" aria-hidden="true"><?php echo $item['icon_svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised by ap_ui_svg(). ?></span>
							<?php elseif ( '' !== $item['icon'] ) : ?>
								<span class="ap-sticky-list__icon">
									<img src="<?php echo esc_url( $item['icon'] ); ?>" alt="<?php echo esc_attr( $item['icon_alt'] ); ?>" loading="lazy" decoding="async" />
								</span>
							<?php endif; ?>
							<h3 class="ap-sticky-list__h3"><?php echo ap_ui_brandmarks( esc_html( $item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></h3>
						</div>

						<?php if ( '' !== $item['body'] ) : ?>
							<p class="ap-sticky-list__body"><?php echo ap_ui_brandmarks( esc_html( $item['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $item['checks'] ) ) : ?>
							<ul class="ap-sticky-list__checks">
								<?php foreach ( $item['checks'] as $check ) : ?>
									<li>
										<span class="ap-sticky-list__check" aria-hidden="true"></span>
										<span><?php echo ap_ui_brandmarks( esc_html( $check ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped; brandmarks adds a fixed span. ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( '' !== $item['link'] && '' !== $item['url'] ) : ?>
							<a class="ap-sticky-list__link" href="<?php echo esc_url( $item['url'] ); ?>"><span><?php echo esc_html( $item['link'] ); ?></span></a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
