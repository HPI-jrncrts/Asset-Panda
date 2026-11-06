<?php
/**
 * solution-awards — the solution pages' award badges.
 *
 * Matches the live .epx-awards section: badges spread along one row from 901px;
 * below that the row becomes a masked marquee, so every badge is written twice
 * (the copies are hidden from assistive tech and shown only while scrolling).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['image'] ?? '' ) ) ) {
		continue;
	}
	$w       = isset( $item['w'] ) ? (int) $item['w'] : 0;
	$h       = isset( $item['h'] ) ? (int) $item['h'] : 0;
	$items[] = array(
		'image' => trim( (string) $item['image'] ),
		'alt'   => (string) ( $item['alt'] ?? '' ),
		'dims'  => ( $w > 0 && $h > 0 && $w < 1000 && $h < 1000 ) ? ' width="' . $w . '" height="' . $h . '"' : '',
	);
}

if ( empty( $items ) ) {
	return;
}
?>
<section class="ap-solution-awards<?php echo 'grey' === ( $props['tone'] ?? '' ) ? ' ap-solution-awards--grey' : ' ap-solution-awards--white'; ?>">
	<div class="ap-solution-awards__wrap">
		<div class="ap-solution-awards__ticker">
			<div class="ap-solution-awards__row">
				<?php foreach ( $items as $i => $item ) : ?>
					<img class="ap-solution-awards__rev<?php echo $i ? ' ap-solution-awards__rev--d' . esc_attr( (string) min( $i, 6 ) ) : ''; ?>" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>"<?php echo $item['dims']; // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> loading="lazy" decoding="async" />
				<?php endforeach; ?>
				<?php foreach ( $items as $item ) : ?>
					<?php // Eager (WordPress adds loading="lazy" to sized images unless one is set): the duplicate copies sit off-screen in the phone marquee and stayed blank until they scrolled into view. Same URLs as the first row, so no extra request. ?>
					<img class="ap-solution-awards__dup" src="<?php echo esc_url( $item['image'] ); ?>" alt="" aria-hidden="true"<?php echo $item['dims']; // phpcs:ignore WordPress.Security.EscapeOutput -- integers. ?> loading="eager" decoding="async" />
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
