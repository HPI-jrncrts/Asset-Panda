# `ui/` — the shared presentation kit

This is the **only** layer allowed to contain HTML markup and CSS. Everything
here is framework-agnostic: it knows nothing about Gutenberg, Unyson, or
WordPress business logic. Props go in, HTML comes out.

## Layout

```
ui/
├── src/
│   ├── loader.php     # ap_render_component() + validation. The public API.
│   ├── tokens.css     # design tokens — ported from Figma. Regenerate the
│   │                  #   responsive block with tools/gen-tokens.mjs.
│   ├── motion.css     # shared reveal + hover vocabulary (see "The motion layer")
│   └── motion.js      # one IntersectionObserver for the whole document
└── components/
    └── <name>/
        ├── component.json   # the prop contract
        ├── render.php       # the markup
        ├── style.css        # the styles
        └── behaviour.js     # OPTIONAL front-end JS (see "Component behaviour"; most have none)
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

## Component behaviour (JS)

Most components are markup + CSS only. When one genuinely needs front-end
JavaScript (a delegated interaction, a component-specific trigger), it lives in
`ui/components/<name>/behaviour.js` — the component owns its behaviour the same
way it owns its markup and styles. **There is still no shared component
bundle**: a page that never renders a component receives none of its assets, and
that isolation is the whole point of the kit.

The one deliberate exception is the **motion layer** in `ui/src/` (see below).
Scroll reveal is cross-cutting — every component can opt in, and running N
IntersectionObservers instead of one would be strictly worse. It is enqueued
only when *some* kit component renders, so a page using none of the kit still
receives neither file.

This is now **implemented** — `ap_ui_enqueue_behaviour()` in `src/loader.php`,
mirroring how `style.css` is enqueued in `ap_ui_enqueue_styles()`:

- **On demand.** Enqueue only when the component renders, and only if
  `behaviour.js` exists. No-op cleanly when it is absent — most components won't
  have one.
- **Once per component per page**, even if it renders N times (WordPress
  de-dupes by handle — same as the style enqueue).
- **Footer.** Register with `in_footer = true`.
- **Delegated and idempotent.** The script keys off `data-ap-*` attributes, is
  safe to run with several instances present, and safe to initialise once for
  all of them. Never read layout geometry and write position from it in a scroll
  handler.

### The motion layer

`ui/src/motion.css` + `ui/src/motion.js` are the shared animation vocabulary.
Components never write observer code — they mark elements up and the runtime
does the rest:

```html
<div data-ap-reveal>                          <!-- fade + rise -->
<div data-ap-reveal="left">                   <!-- fade + slide -->
<div data-ap-reveal data-ap-reveal-delay="3"> <!-- stagger step 3 -->
<a   data-ap-hover="lift">                    <!-- hover micro-interaction -->
```

Rules that keep it safe:

- **Everything renders visible at rest.** The hidden pre-reveal state applies
  only under `html.ap-motion`, a class `motion.js` sets at runtime. If the
  script fails, is blocked, or throws, content stays visible. A broken observer
  can never leave a section blank.
- **No `data-ap-reveal` above the fold.** `motion.js` is a footer script, so
  first-screen elements would flash. Heroes animate on load from their own
  component CSS instead.
- **Never hover-animate `transform`** — scroll entrances own it. Animate
  `translate` / `scale` / `box-shadow`, which is what `[data-ap-hover]` does.
- `prefers-reduced-motion` is honoured twice: `motion.js` declines to add
  `.ap-motion`, and `motion.css` neutralises transitions anyway.

### Motion tokens

- Shared easing/duration values live in `ui/src/tokens.css` (`--ap-ease-*`,
  `--ap-dur-*`, `--ap-stagger-step`, `--ap-reveal-distance`).
- Component-specific `@keyframes` live in that component's `style.css`.
- **Never invent** a duration or easing — use the tokens. The current values
  come from the `assetpanda-2026` child theme's established hover standard, not
  from Figma; if `get_motion_context` later gives real Figma motion values,
  update the tokens and every component follows.

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
5. *(Only if it needs JS)* Write `behaviour.js` and add loader enqueue support —
   see **Component behaviour (JS)** above. Most components skip this.
6. Run `pwsh tools/sync-ui.ps1` so the verticals pick it up.
7. Add the adapter in `gutenberg/` and/or `unyson/` to expose it in the editors.

## Never edit the copies

`ui/` is synced into each vertical's `lib/asset-panda-ui/`. Those copies are
generated. Edit `ui/` and re-sync — never edit `lib/asset-panda-ui/` directly.
