# `ui/` — the shared presentation kit

This is the **only** layer allowed to contain HTML markup and CSS. Everything
here is framework-agnostic: it knows nothing about Gutenberg, Unyson, or
WordPress business logic. Props go in, HTML comes out.

## Layout

```
ui/
├── src/
│   ├── loader.php     # ap_render_component() + validation. The public API.
│   └── tokens.css     # design tokens — the source of truth for color/type/space.
└── components/
    └── <name>/
        ├── component.json   # the prop contract
        ├── render.php       # the markup
        └── style.css        # the styles
```

`hero/` is the reference component — copy its shape when adding new ones.

## Rules

1. **Presentation only.** No WordPress queries, no `get_option`, no globals, no
   business logic in `render.php`. If a value isn't a declared prop, it doesn't
   belong in the component. Adapters (the verticals) do the data-fetching.

2. **Contract first.** Every component has a `component.json` declaring its
   props. The loader validates against it. If you add a prop to `render.php`,
   add it to the contract in the same change, or the loader will reject it as
   an unknown prop.

3. **Escape on output.** Every dynamic value is escaped at the point of output:
   `esc_html()` for text, `esc_url()` for hrefs/src, `esc_attr()` for attribute
   values. Props arrive untrusted.

4. **Style with tokens.** Component CSS references `tokens.css` custom
   properties (`var(--ap-...)`). Don't hard-code a color, font size, or spacing
   value that a token already covers. Scope every selector under the component's
   root class (e.g. `.ap-hero`) so components never bleed into each other.

5. **Don't enqueue from components.** The loader enqueues each component's
   `style.css` (with `tokens.css` as a dependency) automatically when the
   component renders. Components and verticals never call `wp_enqueue_style`.

6. **`ap_render_component()` returns a string.** It never echoes. Callers decide
   whether to echo or return it (blocks and shortcodes both need the string).

## The contract format (`component.json`)

```json
{
  "name": "hero",
  "description": "One line describing the component.",
  "props": {
    "title":     { "type": "string", "required": true },
    "subtitle":  { "type": "string", "default": "" },
    "alignment": { "type": "string", "default": "center", "enum": ["left", "center"] }
  }
}
```

Supported `type` values: `string`, `number`, `integer`, `boolean`, `array`
(list), `object` (assoc array), `any`. A prop may declare `required: true`, a
`default`, and/or an `enum` of allowed values. Optional props with no `default`
resolve to `null`.

## Adding a component

1. `mkdir ui/components/<name>/`
2. Write `component.json` (the contract).
3. Write `render.php` — presentation only, escape everything, use props.
4. Write `style.css` — scoped under `.ap-<name>`, tokens only.
5. Run `pwsh tools/sync-ui.ps1` so the verticals pick it up.
6. Add the adapter in `gutenberg/` and/or `unyson/` to expose it in the editors.

## Never edit the copies

`ui/` is synced into each vertical's `lib/asset-panda-ui/`. Those copies are
generated. Edit `ui/` and re-sync — never edit `lib/asset-panda-ui/` directly.
