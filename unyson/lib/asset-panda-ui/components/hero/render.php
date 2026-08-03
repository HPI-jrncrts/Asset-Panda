<?php
/**
 * Hero component — markup.
 *
 * Reference renderer. Every component's render.php follows this shape:
 *   - Receives a validated, defaults-applied $props array from the loader.
 *   - Contains presentation only: no WP queries, no business logic, no option
 *     reads. If a value isn't a prop, it doesn't belong here.
 *   - Escapes every value on output (esc_html / esc_url / esc_attr).
 *   - Uses class names hooked to component CSS; colors/spacing come from tokens.
 *
 * @var array  $props     Validated props (see component.json).
 * @var string $component Component name.
 *
 * @package AssetPanda\UI
 */

if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) {
	exit;
}

$has_cta = '' !== $props['cta_label'] && '' !== $props['cta_url'];
$classes = sprintf(
	'ap-hero ap-hero--align-%s ap-hero--theme-%s',
	$props['alignment'],
	$props['theme']
);
?>
<section class="<?php echo esc_attr( $classes ); ?>">
	<div class="ap-hero__inner">
		<?php if ( '' !== $props['eyebrow'] ) : ?>
			<p class="ap-hero__eyebrow"><?php echo esc_html( $props['eyebrow'] ); ?></p>
		<?php endif; ?>

		<h1 class="ap-hero__title"><?php echo esc_html( $props['title'] ); ?></h1>

		<?php if ( '' !== $props['subtitle'] ) : ?>
			<p class="ap-hero__subtitle"><?php echo esc_html( $props['subtitle'] ); ?></p>
		<?php endif; ?>

		<?php if ( $has_cta ) : ?>
			<p class="ap-hero__actions">
				<a class="ap-hero__cta" href="<?php echo esc_url( $props['cta_url'] ); ?>">
					<?php echo esc_html( $props['cta_label'] ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</section>
