<?php
/**
 * lp-cookie-declaration — Cookiebot's cookie declaration in a page section.
 *
 * Live's GDPR page ends with a "Cookie Declaration" heading followed by
 * Cookiebot's cd.js, which draws the cookie list (and the consent change /
 * withdraw links) where its script tag sits. The page content cannot carry a
 * script, so this renders the heading and a mount point; behaviour.js adds
 * the script (id CookieDeclaration, as Cookiebot expects) inside the mount
 * point. Without JavaScript a short line says where the cookie list lives.
 *
 * @var array $props Validated props.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cbid = strtolower( trim( (string) $props['cbid'] ) );
if ( ! preg_match( '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/', $cbid ) ) {
	return;
}
$anchor  = preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,95}$/', trim( (string) $props['anchor'] ) ) ? trim( (string) $props['anchor'] ) : '';
$title   = trim( (string) $props['title'] );
$intro   = trim( (string) $props['intro'] );
$culture = preg_match( '/^[A-Za-z]{2}(?:-[A-Za-z]{2})?$/', trim( (string) $props['culture'] ) ) ? strtoupper( trim( (string) $props['culture'] ) ) : '';
?>
<section class="ap-lp-cookies">
	<div class="ap-lp-cookies__inner">
		<?php if ( '' !== $title ) : ?>
			<h2 class="ap-lp-cookies__title"<?php echo '' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>><?php echo esc_html( $title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $intro ) : ?>
			<p class="ap-lp-cookies__intro"><?php echo esc_html( $intro ); ?></p>
		<?php endif; ?>
		<div class="ap-lp-cookies__list" data-ap-lp-cookies="<?php echo esc_attr( $cbid ); ?>"<?php echo '' !== $culture ? ' data-ap-lp-cookies-culture="' . esc_attr( $culture ) . '"' : ''; ?>>
			<noscript><p>The cookie list is drawn by Cookiebot and needs JavaScript. You can change or withdraw your cookie consent at any time from the cookie banner.</p></noscript>
		</div>
	</div>
</section>
