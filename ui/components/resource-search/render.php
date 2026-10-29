<?php
/**
 * resource-search — the Resource Center's instant search field.
 *
 * Server output is a plain search form (it works without JavaScript: the
 * query goes to fallback_url as ?q=). behaviour.js turns it into a combobox:
 * the index is fetched once, the first time the field is focused, hovered or
 * touched, and results are drawn under the field as the visitor types.
 *
 * @var array $props Validated props.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$index = trim( (string) $props['index_url'] );
if ( '' === $index ) {
	return;
}

$label       = trim( (string) $props['label'] );
$placeholder = trim( (string) $props['placeholder'] );
$value       = (string) $props['value'];
$fallback    = trim( (string) $props['fallback_url'] );
$max         = max( 3, min( 20, (int) $props['max_results'] ) );
$size        = 'md' === $props['size'] ? 'md' : 'lg';
$uid         = 'ap-rs-' . substr( md5( $index . '|' . $props['scope'] . '|' . $placeholder . '|' . wp_rand() ), 0, 8 );

$icon_search = '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M13 13l5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
$icon_clear  = '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" aria-hidden="true" focusable="false"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
?>
<div class="ap-resource-search ap-resource-search--<?php echo esc_attr( $size ); ?>" data-ap-resource-search
	data-index="<?php echo esc_url( $index ); ?>"
	data-action="<?php echo esc_attr( (string) $props['action'] ); ?>"
	data-action-all="<?php echo esc_attr( (string) $props['action_all'] ); ?>"
	data-scope="<?php echo esc_attr( (string) $props['scope'] ); ?>"
	data-scope-label="<?php echo esc_attr( (string) $props['scope_label'] ); ?>"
	data-max="<?php echo esc_attr( (string) $max ); ?>">
	<form class="ap-resource-search__form" role="search" method="get" action="<?php echo esc_url( '' !== $fallback ? $fallback : './' ); ?>">
		<label class="ap-resource-search__label" for="<?php echo esc_attr( $uid ); ?>"><?php echo esc_html( $label ); ?></label>
		<span class="ap-resource-search__icon"><?php echo $icon_search; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></span>
		<input class="ap-resource-search__input" id="<?php echo esc_attr( $uid ); ?>" type="search" name="q"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search"
			role="combobox" aria-autocomplete="list" aria-expanded="false" aria-haspopup="listbox"
			aria-controls="<?php echo esc_attr( $uid ); ?>-list" />
		<button class="ap-resource-search__clear" type="button" aria-label="Clear search"<?php echo '' === $value ? ' hidden' : ''; ?>><?php echo $icon_clear; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup. ?></button>
	</form>
	<div class="ap-resource-search__panel" id="<?php echo esc_attr( $uid ); ?>-panel" hidden>
		<p class="ap-resource-search__status" role="status" aria-live="polite"></p>
		<ul class="ap-resource-search__list" id="<?php echo esc_attr( $uid ); ?>-list" role="listbox" aria-label="<?php echo esc_attr( $label ); ?>"></ul>
		<div class="ap-resource-search__foot"></div>
	</div>
</div>
