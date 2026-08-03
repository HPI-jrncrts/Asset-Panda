# `gutenberg/` — staging theme (Gutenberg blocks)

This is the **staging** theme. Its blocks are **adapters**: they collect data in
the block editor and hand it to the shared kit. They do not contain markup.

## The rule

A block's server render maps its **attributes → a props array → the kit**, and
returns the string. Never build HTML in a block.

```php
// A dynamic block's render_callback:
function ap_hero_block_render( $attributes ) {
    $props = array(
        'title'     => $attributes['title']     ?? '',
        'subtitle'  => $attributes['subtitle']  ?? '',
        'cta_label' => $attributes['ctaLabel']  ?? '',
        'cta_url'   => $attributes['ctaUrl']     ?? '',
        'alignment' => $attributes['alignment'] ?? 'center',
        'theme'     => $attributes['theme']     ?? 'light',
    );

    return ap_render_component( 'hero', $props );
}
```

The block's job ends at building `$props`. All markup, escaping, and styling
live in `ui/components/hero/`.

## Rules for this layer

1. **No markup in blocks.** Not in `render_callback`, not in `save.js`. Use
   **dynamic blocks** (server-rendered via `render_callback`) so the single
   source of truth stays PHP in the kit. `save` should return `null` (dynamic).
   `edit.js` may render an editor-only preview, but the front end must come from
   `ap_render_component()`.

2. **Attribute names are the block's concern; prop names are the kit's.** Map
   between them explicitly in the render callback (note `ctaLabel` → `cta_label`
   above). Don't rename kit props to match block conventions — adapt.

3. **Contract is the source of truth for the schema.** When defining
   `block.json` attributes, mirror the prop names, types, and enums from
   `ui/components/<name>/component.json`. If they drift, the loader will reject
   props at render time.

4. **Load the kit once.** The theme requires `lib/asset-panda-ui/src/loader.php`
   and defines `AP_UI_URL` (and optionally `AP_UI_VERSION`) so the loader can
   enqueue component CSS. Do not enqueue component styles yourself.

5. **Don't edit `lib/asset-panda-ui/`.** It is a generated copy of `ui/`. To
   change a component, edit `ui/` at the repo root and run `pwsh
   tools/sync-ui.ps1`.

## Adding a block for a component

1. Ensure the component exists in `ui/components/` and is synced.
2. Create the block (`block.json` + `edit.js`), mirroring the contract's props
   as attributes.
3. Register a `render_callback` that maps attributes → props → `ap_render_component()`.
4. Verify the front-end output matches the live (Unyson) output — same kit, same HTML.
