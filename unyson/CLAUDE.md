# `unyson/` — live theme (Unyson page-builder shortcodes)

This is the **live** theme. Its Unyson shortcodes are **adapters**: they read
page-builder option values and hand them to the shared kit. They do not contain
markup.

## The rule

A shortcode maps its **options → a props array → the kit**, and returns the
string. Never build HTML in a shortcode.

```php
// An Unyson shortcode's render (views/view.php or the shortcode callback):
$props = array(
    'title'     => $atts['title']     ?? '',
    'subtitle'  => $atts['subtitle']  ?? '',
    'cta_label' => $atts['cta_label'] ?? '',
    'cta_url'   => $atts['cta_url']   ?? '',
    'alignment' => $atts['alignment'] ?? 'center',
    'theme'     => $atts['theme']     ?? 'light',
);

echo ap_render_component( 'hero', $props );
```

The shortcode's job ends at building `$props`. All markup, escaping, and styling
live in `ui/components/hero/`.

## Rules for this layer

1. **No markup in shortcode views.** An Unyson shortcode's `views/view.php`
   should do nothing but assemble `$props` and call `ap_render_component()`.
   `ap_render_component()` returns a string — `echo` it (or return it) from the
   view.

2. **Option names are the shortcode's concern; prop names are the kit's.** Map
   between the shortcode's `options.php` config and the kit's props explicitly.
   Don't rename kit props to match Unyson option keys — adapt in the view.

3. **Contract is the source of truth for the schema.** When defining a
   shortcode's `options.php`, mirror the prop names, types, and allowed values
   from `ui/components/<name>/component.json`. Drift is caught by the loader at
   render time.

4. **Load the kit once.** The theme requires `lib/asset-panda-ui/src/loader.php`
   and defines `AP_UI_URL` (and optionally `AP_UI_VERSION`) so the loader can
   enqueue component CSS. Do not enqueue component styles yourself.

5. **Don't edit `lib/asset-panda-ui/`.** It is a generated copy of `ui/`. To
   change a component, edit `ui/` at the repo root and run `pwsh
   tools/sync-ui.ps1`.

## Adding a shortcode for a component

1. Ensure the component exists in `ui/components/` and is synced.
2. Create the Unyson shortcode (`config.php`, `options.php`, `views/view.php`),
   mirroring the contract's props as options.
3. In `views/view.php`, map options → props → `ap_render_component()`.
4. Verify the output matches the staging (Gutenberg) output — same kit, same HTML.
