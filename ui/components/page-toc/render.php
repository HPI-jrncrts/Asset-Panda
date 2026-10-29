<?php
/**
 * page-toc — floating "On this page" pill and panel.
 *
 * Fixed to the left edge, vertically centred. The pill opens the panel; the
 * panel lists the sections, marks the one in view and shows reading progress.
 * All of that is behaviour.js — without it the panel renders open with plain
 * anchor links, so the list still works.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['label'] ?? '' ) ) ) {
		continue;
	}
	$anchor = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $item['anchor'] ?? '' ) );
	if ( '' === $anchor ) {
		continue;
	}
	$items[] = array(
		'label'  => trim( (string) $item['label'] ),
		'anchor' => $anchor,
	);
}

if ( empty( $items ) ) {
	return;
}

$title    = trim( (string) $props['title'] );
$progress = trim( (string) $props['progress_label'] );
$open     = ! empty( $props['open'] );
$uid      = 'ap-page-toc-' . substr( md5( wp_json_encode( $items ) ), 0, 8 );

$icon_list = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false"><path d="M8.67 3.33H14M8.67 6H12M8.67 10H14M8.67 12.67H12M2 3.33c0-.37.3-.66.67-.66h2.66c.37 0 .67.3.67.66V6c0 .37-.3.67-.67.67H2.67A.67.67 0 0 1 2 6V3.33zm0 6.67c0-.37.3-.67.67-.67h2.66c.37 0 .67.3.67.67v2.67c0 .36-.3.66-.67.66H2.67A.67.67 0 0 1 2 12.67V10z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$icon_up   = '<svg viewBox="0 0 10 6" width="10" height="6" fill="none" aria-hidden="true" focusable="false"><path d="M1 5l4-4 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$icon_x    = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<div class="ap-page-toc<?php echo $open ? ' is-open' : ''; ?>" data-ap-page-toc>
	<button class="ap-page-toc__chip" type="button" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $uid ); ?>" data-ap-toc-toggle>
		<?php echo $icon_list; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
		<span><?php echo esc_html( $title ); ?></span>
		<?php echo $icon_up; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
	</button>
	<nav class="ap-page-toc__panel" id="<?php echo esc_attr( $uid ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
		<div class="ap-page-toc__head">
			<?php echo $icon_list; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
			<p class="ap-page-toc__title"><?php echo esc_html( $title ); ?></p>
			<button class="ap-page-toc__close" type="button" aria-label="Close" data-ap-toc-close><?php echo $icon_x; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></button>
		</div>
		<ol class="ap-page-toc__list">
			<?php foreach ( $items as $i => $item ) : ?>
				<li class="ap-page-toc__item<?php echo 0 === $i ? ' is-active' : ''; ?>">
					<a class="ap-page-toc__link" href="#<?php echo esc_attr( $item['anchor'] ); ?>" data-ap-toc-link="<?php echo esc_attr( $item['anchor'] ); ?>"<?php echo 0 === $i ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
		<div class="ap-page-toc__progress">
			<span class="ap-page-toc__progress-label"><?php echo esc_html( $progress ); ?></span>
			<span class="ap-page-toc__pct" data-ap-toc-pct>0%</span>
			<span class="ap-page-toc__bar" aria-hidden="true"><span class="ap-page-toc__fill" data-ap-toc-fill></span></span>
		</div>
	</nav>
</div>
