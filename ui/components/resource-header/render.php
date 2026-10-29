<?php
/**
 * resource-header — breadcrumbs + the deep-indigo article band (variant
 * "band"), or the 2026 post design's light header (variant "article": Figma
 * Blog Detail 5434:79954 / compare post 7219:87997 — breadcrumbs, eyebrow,
 * H1, "Share on:" row, featured image full width, gradient wash behind).
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = trim( (string) $props['title'] );
if ( '' === $title ) {
	return;
}

$variant = 'article' === $props['variant'] ? 'article' : 'band';

$crumbs = array();
foreach ( is_array( $props['crumbs'] ) ? $props['crumbs'] : array() as $crumb ) {
	if ( is_array( $crumb ) && '' !== trim( (string) ( $crumb['label'] ?? '' ) ) ) {
		$crumbs[] = $crumb;
	}
}
$cats = array();
foreach ( is_array( $props['categories'] ) ? $props['categories'] : array() as $cat ) {
	if ( is_array( $cat ) && '' !== trim( (string) ( $cat['label'] ?? '' ) ) ) {
		$cats[] = $cat;
	}
}
$label     = trim( (string) $props['label'] );
$label_url = trim( (string) $props['label_url'] );
$img       = trim( (string) $props['image'] );
$w         = (int) $props['image_w'];
$h         = (int) $props['image_h'];
$side      = 'left' === $props['image_side'] ? 'left' : 'right';
$date      = trim( (string) $props['date'] );
$share_url = trim( (string) $props['share_url'] );

if ( '' !== $img && function_exists( 'ap_ui_preload_image' ) ) {
	echo ap_ui_preload_image( $img ); // phpcs:ignore WordPress.Security.EscapeOutput -- built with esc_url/esc_attr in the loader.
}

$share = array();
if ( '' !== $share_url ) {
	$u       = rawurlencode( $share_url );
	$t       = rawurlencode( (string) $props['share_title'] );
	$share[] = array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.75h4v11H3v-11zm6.5 0h3.83v1.5h.05c.53-1 1.84-2.06 3.8-2.06 4.06 0 4.82 2.67 4.82 6.15v5.41h-4v-4.8c0-1.14-.02-2.62-1.6-2.62-1.6 0-1.84 1.25-1.84 2.54v4.88h-4.06v-11z"/></svg>' );
	$share[] = array( 'X', 'https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true" focusable="false"><path d="M16.89 4h2.72l-5.96 6.79L20.61 20h-5.47l-4.28-5.6L5.96 20H3.25l6.32-7.26L2.89 4h5.6l3.87 5.11L16.89 4zm-.95 14.41h1.5L7.7 5.54H6.09l9.85 12.87z"/></svg>' );
	$share[] = array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u, '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.9 3.78-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99A10 10 0 0 0 22 12z"/></svg>' );
	$share[] = array( 'Email', 'mailto:?subject=' . $t . '&body=' . $u, '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true" focusable="false"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7zm0 0l9 6 9-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
}
$chev = '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" aria-hidden="true" focusable="false"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

if ( 'article' === $variant ) :
	// The design's own glyphs (Figma 5434:79967): 16px home and chevron, 24px X / Facebook / LinkedIn / mail, in that order.
	$chev_a = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false"><path d="M6 4L10 8L6 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$home_a = '<svg viewBox="0 0 16 16" width="16" height="16" fill="none" aria-hidden="true" focusable="false"><path d="M14.5 8L13 6.5L8.53033 2.03033C8.23744 1.73744 7.76256 1.73744 7.46967 2.03033L3 6.5L1.5 8M3 6.5V13.25C3 13.6642 3.33579 14 3.75 14H5.5H6.5M13 6.5V13.25C13 13.6642 12.6642 14 12.25 14H11H9.5M6.5 14V10.75C6.5 10.3358 6.83579 10 7.25 10H8.75C9.16421 10 9.5 10.3358 9.5 10.75V14M6.5 14H9.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$share_a = array();
	if ( '' !== $share_url ) {
		$share_a[] = array( 'X', $share[1][1], '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false"><path d="M16.8911 4H19.607L13.6439 10.7897L20.6107 20H15.1435L10.8631 14.403L5.96273 20H3.24686L9.56421 12.738L2.89262 4H8.49557L12.3627 9.11292L16.8911 4ZM15.9406 18.4059H17.4461L7.70443 5.53506H6.08672L15.9406 18.4059Z" fill="currentColor"/></svg>' );
		$share_a[] = array( 'Facebook', $share[2][1], '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false"><path d="M22 12C22 6.477 17.523 2 12 2C6.477 2 2 6.477 2 12C2 16.991 5.657 21.128 10.438 21.878V14.891H7.898V12H10.438V9.797C10.438 7.291 11.93 5.907 14.215 5.907C15.309 5.907 16.453 6.102 16.453 6.102V8.562H15.193C13.95 8.562 13.563 9.333 13.563 10.124V12H16.336L15.893 14.89H13.563V21.878C18.343 21.128 22 16.991 22 12Z" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"/></svg>' );
		$share_a[] = array( 'LinkedIn', $share[0][1], '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false"><path d="M3 4.50382C3 4.10498 3.15844 3.72248 3.44046 3.44046C3.72248 3.15844 4.10498 3 4.50382 3H19.4945C19.6922 2.99968 19.888 3.03834 20.0707 3.11378C20.2533 3.18922 20.4194 3.29996 20.5592 3.43964C20.699 3.57933 20.81 3.74522 20.8856 3.92783C20.9612 4.11044 21.0001 4.30617 21 4.50382V19.4945C21.0002 19.6922 20.9614 19.888 20.8859 20.0707C20.8104 20.2534 20.6996 20.4194 20.5598 20.5593C20.4201 20.6991 20.2541 20.81 20.0715 20.8856C19.8888 20.9612 19.6931 21.0001 19.4954 21H4.50382C4.30627 21 4.11065 20.9611 3.92814 20.8855C3.74564 20.8098 3.57982 20.699 3.44017 20.5593C3.30052 20.4195 3.18976 20.2536 3.11424 20.0711C3.03871 19.8886 2.99989 19.6929 3 19.4954V4.50382ZM10.1247 9.86291H12.5621V11.0869C12.9139 10.3833 13.8139 9.75 15.1664 9.75C17.7592 9.75 18.3736 11.1515 18.3736 13.7231V18.4865H15.7497V14.3089C15.7497 12.8444 15.3979 12.018 14.5045 12.018C13.2649 12.018 12.7495 12.909 12.7495 14.3089V18.4865H10.1247V9.86291ZM5.62473 18.3745H8.24945V9.75H5.62473V18.3736V18.3745ZM8.625 6.93709C8.62995 7.16182 8.58996 7.38528 8.50738 7.59435C8.4248 7.80342 8.30129 7.99389 8.1441 8.15457C7.98691 8.31526 7.79921 8.44293 7.59201 8.5301C7.38481 8.61726 7.16229 8.66216 6.9375 8.66216C6.71271 8.66216 6.49019 8.61726 6.28299 8.5301C6.07579 8.44293 5.88809 8.31526 5.7309 8.15457C5.57371 7.99389 5.4502 7.80342 5.36762 7.59435C5.28504 7.38528 5.24505 7.16182 5.25 6.93709C5.25971 6.49597 5.44177 6.07619 5.75718 5.76765C6.07259 5.45911 6.49627 5.28634 6.9375 5.28634C7.37873 5.28634 7.80241 5.45911 8.11782 5.76765C8.43323 6.07619 8.61529 6.49597 8.625 6.93709V6.93709Z" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"/></svg>' );
		$share_a[] = array( 'Email', $share[3][1], '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" aria-hidden="true" focusable="false"><path d="M3 7C3 6.46957 3.21071 5.96086 3.58579 5.58579C3.96086 5.21071 4.46957 5 5 5H19C19.5304 5 20.0391 5.21071 20.4142 5.58579C20.7893 5.96086 21 6.46957 21 7M3 7V17C3 17.5304 3.21071 18.0391 3.58579 18.4142C3.96086 18.7893 4.46957 19 5 19H19C19.5304 19 20.0391 18.7893 20.4142 18.4142C20.7893 18.0391 21 17.5304 21 17V7M3 7L12 13L21 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' );
	}
	?>
<header class="ap-resource-header ap-resource-header--article<?php echo '' === $img ? ' ap-resource-header--no-img' : ''; ?>">
	<?php if ( ! empty( $props['wash'] ) ) : ?>
		<div class="ap-resource-header__wash" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="ap-resource-header__inner">
		<?php if ( $crumbs ) : ?>
			<nav class="ap-resource-header__crumbs" aria-label="Breadcrumb">
				<ol>
					<?php foreach ( $crumbs as $crumb ) : ?>
						<?php
						$crumb_label = (string) $crumb['label'];
						$crumb_url   = trim( (string) ( $crumb['url'] ?? '' ) );
						$crumb_home  = 'home' === ( $crumb['icon'] ?? '' );
						$crumb_text  = $crumb_home
							? $home_a . '<span class="ap-resource-header__sr">' . esc_html( $crumb_label ) . '</span>'
							: esc_html( $crumb_label );
						?>
						<li>
							<?php if ( '' !== $crumb_url ) : ?>
								<a href="<?php echo esc_url( $crumb_url ); ?>"><?php echo $crumb_text; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; the icon is fixed markup. ?></a>
							<?php else : ?>
								<span><?php echo $crumb_text; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above; the icon is fixed markup. ?></span>
							<?php endif; ?>
							<?php echo $chev_a; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
						</li>
					<?php endforeach; ?>
					<li><span class="ap-resource-header__current" aria-current="page"><?php echo esc_html( $title ); ?></span></li>
				</ol>
			</nav>
		<?php endif; ?>
		<div class="ap-resource-header__head">
			<?php if ( '' !== $label ) : ?>
				<p class="ap-resource-header__eyebrow">
					<?php if ( '' !== $label_url ) : ?>
						<a href="<?php echo esc_url( $label_url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $label ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<h1 class="ap-resource-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $date || $share_a ) : ?>
				<div class="ap-resource-header__meta">
					<?php if ( '' !== $date ) : ?>
						<time class="ap-resource-header__date"<?php echo '' !== trim( (string) $props['date_iso'] ) ? ' datetime="' . esc_attr( (string) $props['date_iso'] ) . '"' : ''; ?>><?php echo esc_html( $date ); ?></time>
					<?php endif; ?>
					<?php if ( $share_a ) : ?>
						<div class="ap-resource-header__share">
							<span class="ap-resource-header__share-label"><?php echo esc_html( (string) $props['share_label'] ); ?></span>
							<span class="ap-resource-header__share-icons">
								<?php foreach ( $share_a as $s ) : ?>
									<a href="<?php echo esc_url( $s[1] ); ?>"<?php echo 'Email' !== $s[0] ? ' target="_blank" rel="noopener"' : ''; ?> aria-label="<?php echo esc_attr( ( 'Email' === $s[0] ? 'Share by email' : 'Share on ' . $s[0] ) ); ?>"><?php echo $s[2]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
								<?php endforeach; ?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $img ) : ?>
			<figure class="ap-resource-header__media">
				<img class="ap-resource-header__img" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"
					width="<?php echo esc_attr( (string) ( $w > 0 ? $w : 1856 ) ); ?>" height="<?php echo esc_attr( (string) ( $h > 0 ? $h : 1160 ) ); ?>" fetchpriority="high" decoding="async" />
			</figure>
		<?php endif; ?>
	</div>
</header>
	<?php
	return;
endif;
?>
<header class="ap-resource-header ap-resource-header--img-<?php echo esc_attr( $side ); ?><?php echo '' === $img ? ' ap-resource-header--no-img' : ''; ?>">
	<?php if ( $crumbs ) : ?>
		<nav class="ap-resource-header__crumbs" aria-label="Breadcrumb">
			<ol>
				<?php foreach ( $crumbs as $crumb ) : ?>
					<li>
						<?php if ( '' !== trim( (string) ( $crumb['url'] ?? '' ) ) ) : ?>
							<a href="<?php echo esc_url( (string) $crumb['url'] ); ?>"><?php echo esc_html( (string) $crumb['label'] ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( (string) $crumb['label'] ); ?></span>
						<?php endif; ?>
						<?php echo $chev; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?>
					</li>
				<?php endforeach; ?>
				<li><span class="ap-resource-header__current" aria-current="page"><?php echo esc_html( $title ); ?></span></li>
			</ol>
		</nav>
	<?php endif; ?>
	<div class="ap-resource-header__band">
		<div class="ap-resource-header__copy">
			<h1 class="ap-resource-header__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( '' !== $label ) : ?>
				<p class="ap-resource-header__label">
					<?php if ( '' !== $label_url ) : ?>
						<a href="<?php echo esc_url( $label_url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $label ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $cats ) : ?>
				<ul class="ap-resource-header__cats" aria-label="Categories">
					<?php foreach ( $cats as $cat ) : ?>
						<li>
							<?php if ( '' !== trim( (string) ( $cat['url'] ?? '' ) ) ) : ?>
								<a href="<?php echo esc_url( (string) $cat['url'] ); ?>"><?php echo esc_html( (string) $cat['label'] ); ?></a>
							<?php else : ?>
								<span><?php echo esc_html( (string) $cat['label'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( '' !== $date || $share ) : ?>
				<div class="ap-resource-header__meta">
					<?php if ( '' !== $date ) : ?>
						<time class="ap-resource-header__date"<?php echo '' !== trim( (string) $props['date_iso'] ) ? ' datetime="' . esc_attr( (string) $props['date_iso'] ) . '"' : ''; ?>><?php echo esc_html( $date ); ?></time>
					<?php endif; ?>
					<?php if ( $share ) : ?>
						<div class="ap-resource-header__share">
							<span class="ap-resource-header__share-label"><?php echo esc_html( (string) $props['share_label'] ); ?></span>
							<?php foreach ( $share as $s ) : ?>
								<a href="<?php echo esc_url( $s[1] ); ?>"<?php echo 'Email' !== $s[0] ? ' target="_blank" rel="noopener"' : ''; ?> aria-label="<?php echo esc_attr( ( 'Email' === $s[0] ? 'Share by email' : 'Share on ' . $s[0] ) ); ?>"><?php echo $s[2]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $img ) : ?>
			<figure class="ap-resource-header__media">
				<img class="ap-resource-header__img" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( (string) $props['image_alt'] ); ?>"
					width="<?php echo esc_attr( (string) ( $w > 0 ? $w : 1200 ) ); ?>" height="<?php echo esc_attr( (string) ( $h > 0 ? $h : 675 ) ); ?>" fetchpriority="high" decoding="async" />
			</figure>
		<?php endif; ?>
	</div>
</header>
