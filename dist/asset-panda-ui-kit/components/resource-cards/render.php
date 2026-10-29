<?php
/**
 * resource-cards — listing / related grid of resource cards.
 *
 * Each card is one link target (the title); the photo and Read More repeat
 * the link for pointer users and are hidden from assistive tech, so a screen
 * reader hears each card once. The type label and category link to their
 * listings.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant = 'related' === $props['variant'] ? 'related' : 'grid';
$heading = trim( (string) $props['heading'] );
$count   = trim( (string) $props['count_text'] );
$eager   = max( 0, (int) $props['eager'] );
$more    = trim( (string) $props['more_label'] );

$items = array();
foreach ( is_array( $props['items'] ) ? $props['items'] : array() as $item ) {
	if ( ! is_array( $item ) || '' === trim( (string) ( $item['title'] ?? '' ) ) || '' === trim( (string) ( $item['url'] ?? '' ) ) ) {
		continue;
	}
	$items[] = $item;
}

$pages = array();
foreach ( is_array( $props['pages'] ) ? $props['pages'] : array() as $page ) {
	if ( is_array( $page ) && ( ! empty( $page['gap'] ) || '' !== trim( (string) ( $page['label'] ?? '' ) ) ) ) {
		$pages[] = $page;
	}
}
$prev = trim( (string) $props['prev_url'] );
$next = trim( (string) $props['next_url'] );

if ( 'related' === $variant && ! $items ) {
	return;
}

$arrow      = '<svg viewBox="0 0 19 9" width="19" height="9" fill="none" aria-hidden="true" focusable="false"><path d="M14.75 8.25L18.5 4.5L14.75.75M18.5 4.5H.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$arrow_left = '<svg viewBox="0 0 19 9" width="19" height="9" fill="none" aria-hidden="true" focusable="false"><path d="M4.25.75L.5 4.5l3.75 3.75M.5 4.5h18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<section class="ap-resource-cards ap-resource-cards--<?php echo esc_attr( $variant ); ?>"<?php echo 'grid' === $variant ? ' data-ap-resource-cards' : ''; ?>>
	<div class="ap-resource-cards__inner">
		<?php if ( 'grid' === $variant ) : ?>
			<p class="ap-resource-cards__status" role="status" aria-live="polite"></p>
		<?php endif; ?>
		<?php if ( '' !== $heading ) : ?>
			<h2 class="ap-resource-cards__heading"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $count && $items ) : ?>
			<p class="ap-resource-cards__count"><?php echo esc_html( $count ); ?></p>
		<?php endif; ?>

		<?php if ( $items ) : ?>
			<ul class="ap-resource-cards__grid">
				<?php
				foreach ( $items as $i => $item ) :
					$url      = (string) $item['url'];
					$title    = trim( (string) $item['title'] );
					$img      = trim( (string) ( $item['image'] ?? '' ) );
					$w        = (int) ( $item['image_w'] ?? 0 );
					$h        = (int) ( $item['image_h'] ?? 0 );
					$label    = trim( (string) ( $item['label'] ?? '' ) );
					$label_u  = trim( (string) ( $item['label_url'] ?? '' ) );
					$cat      = trim( (string) ( $item['category'] ?? '' ) );
					$cat_u    = trim( (string) ( $item['category_url'] ?? '' ) );
					$excerpt  = trim( (string) ( $item['excerpt'] ?? '' ) );
					$date     = trim( (string) ( $item['date'] ?? '' ) );
					$date_iso = trim( (string) ( $item['date_iso'] ?? '' ) );
					$is_eager = $i < $eager;
					?>
					<li class="ap-resource-cards__card">
						<a class="ap-resource-cards__media" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true">
							<?php if ( '' !== $img ) : ?>
								<img class="ap-resource-cards__img" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( (string) ( $item['image_alt'] ?? '' ) ); ?>"
									width="<?php echo esc_attr( (string) ( $w > 0 ? $w : 1200 ) ); ?>" height="<?php echo esc_attr( (string) ( $h > 0 ? $h : 675 ) ); ?>"
									<?php echo $is_eager ? ( 0 === $i ? 'fetchpriority="high"' : '' ) : 'loading="lazy"'; ?> decoding="async" />
							<?php else : ?>
								<span class="ap-resource-cards__img ap-resource-cards__img--none"></span>
							<?php endif; ?>
						</a>
						<div class="ap-resource-cards__body">
							<?php if ( '' !== $label || '' !== $cat || '' !== $date ) : ?>
								<p class="ap-resource-cards__meta">
									<?php if ( '' !== $label ) : ?>
										<?php if ( '' !== $label_u ) : ?>
											<a class="ap-resource-cards__label" href="<?php echo esc_url( $label_u ); ?>"><?php echo esc_html( $label ); ?></a>
										<?php else : ?>
											<span class="ap-resource-cards__label"><?php echo esc_html( $label ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( '' !== $cat ) : ?>
										<?php if ( '' !== $cat_u ) : ?>
											<a class="ap-resource-cards__cat" href="<?php echo esc_url( $cat_u ); ?>"><?php echo esc_html( $cat ); ?></a>
										<?php else : ?>
											<span class="ap-resource-cards__cat"><?php echo esc_html( $cat ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( '' !== $date ) : ?>
										<time class="ap-resource-cards__date"<?php echo '' !== $date_iso ? ' datetime="' . esc_attr( $date_iso ) . '"' : ''; ?>><?php echo esc_html( $date ); ?></time>
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<h3 class="ap-resource-cards__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
							<?php if ( '' !== $excerpt ) : ?>
								<p class="ap-resource-cards__excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $more ) : ?>
								<a class="ap-resource-cards__more" href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php echo esc_html( $more ); ?> <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="ap-resource-cards__empty">
				<p class="ap-resource-cards__empty-title"><?php echo esc_html( (string) $props['empty_title'] ); ?></p>
				<?php if ( '' !== trim( (string) $props['empty_text'] ) ) : ?>
					<p class="ap-resource-cards__empty-text"><?php echo esc_html( (string) $props['empty_text'] ); ?></p>
				<?php endif; ?>
				<?php
				$links = array();
				foreach ( is_array( $props['empty_links'] ) ? $props['empty_links'] : array() as $link ) {
					if ( is_array( $link ) && '' !== trim( (string) ( $link['label'] ?? '' ) ) && '' !== trim( (string) ( $link['url'] ?? '' ) ) ) {
						$links[] = $link;
					}
				}
				if ( $links ) :
					?>
					<p class="ap-resource-cards__empty-links">
						<?php foreach ( $links as $link ) : ?>
							<a href="<?php echo esc_url( (string) $link['url'] ); ?>"><?php echo esc_html( (string) $link['label'] ); ?></a>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'grid' === $variant && ( $pages || '' !== $prev || '' !== $next ) ) : ?>
			<nav class="ap-resource-cards__pages" aria-label="Pagination">
				<?php if ( '' !== $prev ) : ?>
					<a class="ap-resource-cards__step ap-resource-cards__step--prev" href="<?php echo esc_url( $prev ); ?>" rel="prev"><?php echo $arrow_left; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo esc_html( (string) $props['prev_label'] ); ?></span></a>
				<?php else : ?>
					<span class="ap-resource-cards__step ap-resource-cards__step--prev is-disabled" aria-hidden="true"><?php echo $arrow_left; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?><span><?php echo esc_html( (string) $props['prev_label'] ); ?></span></span>
				<?php endif; ?>
				<ol class="ap-resource-cards__nums">
					<?php foreach ( $pages as $page ) : ?>
						<?php if ( ! empty( $page['gap'] ) ) : ?>
							<li class="ap-resource-cards__gap" aria-hidden="true">&hellip;</li>
						<?php elseif ( ! empty( $page['current'] ) ) : ?>
							<li><span class="ap-resource-cards__num is-current" aria-current="page"><?php echo esc_html( (string) $page['label'] ); ?></span></li>
						<?php else : ?>
							<li><a class="ap-resource-cards__num" href="<?php echo esc_url( (string) ( $page['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) $page['label'] ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ol>
				<?php if ( '' !== $next ) : ?>
					<a class="ap-resource-cards__step ap-resource-cards__step--next" href="<?php echo esc_url( $next ); ?>" rel="next"><span><?php echo esc_html( (string) $props['next_label'] ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
				<?php else : ?>
					<span class="ap-resource-cards__step ap-resource-cards__step--next is-disabled" aria-hidden="true"><span><?php echo esc_html( (string) $props['next_label'] ); ?></span><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

		<?php if ( 'grid' === $variant ) : ?>
			<?php /* behaviour.js clones this while a filtered page is being fetched: one count bone, one card per card that left, one pager rule. Inert until then. */ ?>
			<template class="ap-resource-cards__skeleton" data-ap-rc-skeleton>
				<p class="ap-resource-cards__count ap-resource-cards__count--skeleton" aria-hidden="true"><span class="ap-resource-cards__bone ap-resource-cards__bone--count"></span></p>
				<ul class="ap-resource-cards__grid ap-resource-cards__grid--skeleton" aria-hidden="true">
					<li class="ap-resource-cards__card ap-resource-cards__card--skeleton">
						<span class="ap-resource-cards__media"></span>
						<div class="ap-resource-cards__body">
							<span class="ap-resource-cards__bone ap-resource-cards__bone--meta"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--title"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--title is-short"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--text"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--text"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--text is-short"></span>
							<span class="ap-resource-cards__bone ap-resource-cards__bone--more"></span>
						</div>
					</li>
				</ul>
				<nav class="ap-resource-cards__pages ap-resource-cards__pages--skeleton" aria-hidden="true"></nav>
			</template>
		<?php endif; ?>
	</div>
</section>
