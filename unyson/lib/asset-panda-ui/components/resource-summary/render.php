<?php
/**
 * resource-summary — success-story summary panel + key features card.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inline = array(
	'a'      => array(
		'href'   => true,
		'target' => true,
		'rel'    => true,
	),
	'strong' => array(),
	'b'      => array(),
	'em'     => array(),
	'i'      => array(),
	'br'     => array(),
);

$title    = trim( (string) $props['title'] );
$text     = trim( wp_kses( (string) $props['text'], $inline ) );
$ftitle   = trim( (string) $props['features_title'] );
$features = array();
foreach ( is_array( $props['features'] ) ? $props['features'] : array() as $f ) {
	$f = trim( wp_kses( (string) $f, $inline ) );
	if ( '' !== $f ) {
		$features[] = $f;
	}
}
if ( '' === $title && '' === $text && ! $features ) {
	return;
}
?>
<section class="ap-resource-summary">
	<?php if ( '' !== $title ) : ?>
		<h2 class="ap-resource-summary__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== $text ) : ?>
		<p class="ap-resource-summary__text"><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses with an inline allowlist above. ?></p>
	<?php endif; ?>
	<?php if ( $features ) : ?>
		<div class="ap-resource-summary__card">
			<?php if ( '' !== $ftitle ) : ?>
				<h3 class="ap-resource-summary__ftitle"><?php echo esc_html( $ftitle ); ?></h3>
			<?php endif; ?>
			<ul class="ap-resource-summary__list<?php echo count( $features ) < 4 ? ' is-short' : ''; ?>">
				<?php foreach ( $features as $f ) : ?>
					<li><?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses with an inline allowlist above. ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
