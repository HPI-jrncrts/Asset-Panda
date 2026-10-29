<?php
/**
 * page-reviews — the customer review wall (/client-reviews/).
 *
 * Live shows one industry per page load (?industry=education …) through a
 * select box. Here every industry's reviews are in the page — a featured
 * story and a grid of cards per industry — and chips switch between them in
 * place (behaviour.js). Each industry keeps a heading (visually small, it
 * reads as the group label) so the page outline stays meaningful without
 * JavaScript, when every industry simply shows in turn.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$img = static function ( $src, $w, $h, $class, $alt = '' ) {
	$src = trim( (string) $src );
	if ( '' === $src ) {
		return '';
	}
	$w = (int) $w;
	$h = (int) $h;
	return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"' . ( $w > 0 && $h > 0 ? ' width="' . esc_attr( (string) $w ) . '" height="' . esc_attr( (string) $h ) . '"' : '' ) . ' loading="lazy" decoding="async" />';
};

$groups = array();
foreach ( is_array( $props['groups'] ) ? $props['groups'] : array() as $g ) {
	if ( ! is_array( $g ) || '' === trim( (string) ( $g['label'] ?? '' ) ) ) {
		continue;
	}
	$items = array();
	foreach ( is_array( $g['items'] ?? null ) ? $g['items'] : array() as $it ) {
		if ( is_array( $it ) && '' !== trim( (string) ( $it['quote'] ?? '' ) ) ) {
			$items[] = $it;
		}
	}
	$featured = is_array( $g['featured'] ?? null ) && '' !== trim( (string) ( $g['featured']['quote'] ?? '' ) ) ? $g['featured'] : null;
	if ( ! $items && ! $featured ) {
		continue;
	}
	$groups[] = array(
		'label'    => trim( (string) $g['label'] ),
		'key'      => trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $g['label'] ) ), '-' ),
		'featured' => $featured,
		'items'    => $items,
	);
}
if ( empty( $groups ) ) {
	return;
}

$anchor    = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$all_label = trim( (string) $props['all_label'] );
$default   = (int) $props['default_group'];
if ( $default < -1 || $default >= count( $groups ) || ( -1 === $default && '' === $all_label ) ) {
	$default = 0;
}
$current = -1 === $default ? '*' : $groups[ $default ]['key'];
$qmark   = '<svg class="ap-page-reviews__qmark" width="32" height="26" viewBox="0 0 40 32" fill="none" aria-hidden="true" focusable="false"><path d="M0 32V19.2C0 8.53 5.33 2.13 16 0l2 4.27C11.6 6.13 8.67 9.6 8.4 14.67H16V32H0Zm22 0V19.2C22 8.53 27.33 2.13 38 0l2 4.27c-6.4 1.86-9.33 5.33-9.6 10.4H38V32H22Z" fill="currentColor"></path></svg>';
$total   = 0;
foreach ( $groups as $g ) {
	$total += count( $g['items'] ) + ( $g['featured'] ? 1 : 0 );
}
?>
<section class="ap-page-reviews"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?> data-ap-reviews data-current="<?php echo esc_attr( $current ); ?>">
	<div class="ap-page-reviews__wrap">
		<div class="ap-page-reviews__chips" role="group" aria-label="<?php echo esc_attr( (string) $props['filter_label'] ); ?>">
			<?php if ( '' !== $all_label ) : ?>
				<button type="button" class="ap-page-reviews__chip" data-ap-rv="*" aria-pressed="<?php echo '*' === $current ? 'true' : 'false'; ?>"><?php echo esc_html( $all_label ); ?><span><?php echo esc_html( (string) $total ); ?></span></button>
			<?php endif; ?>
			<?php foreach ( $groups as $g ) : ?>
				<button type="button" class="ap-page-reviews__chip" data-ap-rv="<?php echo esc_attr( $g['key'] ); ?>" aria-pressed="<?php echo $g['key'] === $current ? 'true' : 'false'; ?>"><?php echo esc_html( $g['label'] ); ?><span><?php echo esc_html( (string) ( count( $g['items'] ) + ( $g['featured'] ? 1 : 0 ) ) ); ?></span></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $groups as $g ) : ?>
			<div class="ap-page-reviews__group" data-ap-rv-group="<?php echo esc_attr( $g['key'] ); ?>">
				<h2 class="ap-page-reviews__label"><?php echo esc_html( $g['label'] ); ?></h2>
				<?php if ( $g['featured'] ) : ?>
					<?php $f = $g['featured']; ?>
					<figure class="ap-page-reviews__featured">
						<div class="ap-page-reviews__fcopy">
							<?php echo $img( $f['logo'] ?? '', $f['logo_w'] ?? 0, $f['logo_h'] ?? 0, 'ap-page-reviews__flogo', trim( (string) ( $f['org'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url / esc_attr. ?>
							<?php echo $qmark; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
							<blockquote class="ap-page-reviews__fquote"><p><?php echo esc_html( trim( (string) $f['quote'] ) ); ?></p></blockquote>
							<figcaption class="ap-page-reviews__who">
								<?php if ( '' !== trim( (string) ( $f['name'] ?? '' ) ) ) : ?>
									<strong><?php echo esc_html( trim( (string) $f['name'] ) ); ?></strong>
								<?php endif; ?>
								<?php if ( '' !== trim( (string) ( $f['role'] ?? '' ) ) ) : ?>
									<span><?php echo esc_html( trim( (string) $f['role'] ) ); ?></span>
								<?php endif; ?>
							</figcaption>
						</div>
						<?php if ( '' !== trim( (string) ( $f['photo'] ?? '' ) ) ) : ?>
							<div class="ap-page-reviews__fphoto"><?php echo $img( $f['photo'], $f['photo_w'] ?? 0, $f['photo_h'] ?? 0, '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url / esc_attr. ?></div>
						<?php endif; ?>
					</figure>
				<?php endif; ?>
				<?php if ( $g['items'] ) : ?>
					<ul class="ap-page-reviews__grid">
						<?php foreach ( $g['items'] as $i => $it ) : ?>
							<li class="ap-page-reviews__card">
								<figure>
									<div class="ap-page-reviews__logo"><?php echo $img( $it['logo'] ?? '', $it['logo_w'] ?? 0, $it['logo_h'] ?? 0, '', trim( (string) ( $it['org'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url / esc_attr. ?></div>
									<blockquote class="ap-page-reviews__quote"><p><?php echo esc_html( trim( (string) $it['quote'] ) ); ?></p></blockquote>
									<figcaption class="ap-page-reviews__person">
										<?php echo $img( $it['photo'] ?? '', $it['photo_w'] ?? 0, $it['photo_h'] ?? 0, 'ap-page-reviews__face' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url / esc_attr. ?>
										<span class="ap-page-reviews__who">
											<?php if ( '' !== trim( (string) ( $it['name'] ?? '' ) ) ) : ?>
												<strong><?php echo esc_html( trim( (string) $it['name'] ) ); ?></strong>
											<?php endif; ?>
											<?php if ( '' !== trim( (string) ( $it['role'] ?? '' ) ) ) : ?>
												<span><?php echo esc_html( trim( (string) $it['role'] ) ); ?></span>
											<?php endif; ?>
											<?php if ( '' !== trim( (string) ( $it['org'] ?? '' ) ) ) : ?>
												<span class="ap-page-reviews__org"><?php echo esc_html( trim( (string) $it['org'] ) ); ?></span>
											<?php endif; ?>
										</span>
									</figcaption>
								</figure>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
