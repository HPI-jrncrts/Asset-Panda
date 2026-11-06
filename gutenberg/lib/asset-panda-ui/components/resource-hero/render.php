<?php
/**
 * resource-hero — listing hero: eyebrow, H1, copy, instant search, category tabs.
 *
 * The tabs past `visible_tabs` carry is-extra and stay hidden on wide screens
 * until the ••• button (behaviour.js) opens the rail; when the active tab is
 * one of them the rail renders open. Phones show every tab in one scrolling
 * row and no button. (Extras are hidden by CSS from the first paint, so the
 * footer script never shifts the layout.)
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
$eyebrow = trim( (string) $props['eyebrow'] );
$desc    = trim( (string) $props['description'] );
$search  = is_array( $props['search'] ) ? $props['search'] : array();
$visible = max( 2, (int) $props['visible_tabs'] );

$tabs = array();
foreach ( is_array( $props['tabs'] ) ? $props['tabs'] : array() as $tab ) {
	if ( ! is_array( $tab ) || '' === trim( (string) ( $tab['label'] ?? '' ) ) || '' === trim( (string) ( $tab['url'] ?? '' ) ) ) {
		continue;
	}
	$tabs[] = array(
		'label'  => trim( (string) $tab['label'] ),
		'url'    => (string) $tab['url'],
		'active' => ! empty( $tab['active'] ),
	);
}
$has_more    = count( $tabs ) > $visible;
$open        = false;
foreach ( $tabs as $i => $tab ) {
	if ( $tab['active'] && $i >= $visible ) {
		$open = true;
	}
}
$uid = 'ap-rh-' . substr( md5( $title . wp_json_encode( $tabs ) ), 0, 8 );

$icon_dots = '<svg viewBox="0 0 18 4" width="18" height="4" fill="currentColor" aria-hidden="true" focusable="false"><circle cx="2" cy="2" r="1.6"/><circle cx="9" cy="2" r="1.6"/><circle cx="16" cy="2" r="1.6"/></svg>';
$icon_x    = '<svg viewBox="0 0 12 12" width="12" height="12" fill="none" aria-hidden="true" focusable="false"><path d="M10 2L2 10M2 2l8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
?>
<section class="ap-resource-hero<?php echo $tabs ? ' ap-resource-hero--tabs' : ''; ?>" data-ap-resource-hero>
	<div class="ap-resource-hero__wash" aria-hidden="true"></div>
	<div class="ap-resource-hero__inner">
		<?php if ( '' !== $eyebrow ) : ?>
			<p class="ap-resource-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h1 class="ap-resource-hero__title"><?php echo esc_html( $title ); ?></h1>
		<?php if ( '' !== $desc ) : ?>
			<p class="ap-resource-hero__desc"><?php echo esc_html( $desc ); ?></p>
		<?php endif; ?>
		<?php
		if ( ! empty( $search['index_url'] ) && function_exists( 'ap_render_component' ) ) {
			echo '<div class="ap-resource-hero__search">' . ap_render_component( 'resource-search', $search ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- kit component output, escaped inside.
		}
		?>
		<?php if ( $tabs ) : ?>
			<nav class="ap-resource-hero__tabs<?php echo $open ? ' is-open' : ''; ?><?php echo $has_more ? ' has-more' : ''; ?>" aria-label="<?php echo esc_attr( (string) $props['tabs_label'] ); ?>" data-ap-rh-tabs>
				<ul class="ap-resource-hero__rail" id="<?php echo esc_attr( $uid ); ?>">
					<?php foreach ( $tabs as $i => $tab ) : ?>
						<li class="ap-resource-hero__item<?php echo $i >= $visible ? ' is-extra' : ''; ?>">
							<a class="ap-resource-hero__tab<?php echo $tab['active'] ? ' is-active' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>"<?php echo $tab['active'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tab['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $has_more ) : ?>
					<button class="ap-resource-hero__more" type="button" aria-controls="<?php echo esc_attr( $uid ); ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>"
						data-more="<?php echo esc_attr( (string) $props['more_label'] ); ?>" data-less="<?php echo esc_attr( (string) $props['less_label'] ); ?>"
						aria-label="<?php echo esc_attr( (string) ( $open ? $props['less_label'] : $props['more_label'] ) ); ?>">
						<span class="ap-resource-hero__dots"><?php echo $icon_dots; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
						<span class="ap-resource-hero__x"><?php echo $icon_x; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
					</button>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</section>
