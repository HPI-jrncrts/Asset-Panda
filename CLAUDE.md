# Asset Panda — WordPress monorepo

This repo holds three cooperating layers. **Read this before touching any of them.**

## The layers

| Dir          | Role                                                                 |
| ------------ | ------------------------------------------------------------------- |
| `ui/`        | Shared **presentation kit**. The *only* place HTML markup and CSS live. |
| `gutenberg/` | **Staging** theme. Gutenberg blocks that adapt block data → the kit.  |
| `unyson/`    | **Live** theme. Unyson page-builder shortcodes that adapt option data → the kit. |
| `tools/`     | `sync-ui.ps1` copies `ui/` into each vertical's `lib/asset-panda-ui/`. |

## The one rule that governs everything

**Neither vertical writes markup.** `gutenberg/` and `unyson/` are *adapters*.
Each takes its editor's data, maps it into a flat props array, and calls:

```php
echo ap_render_component( $name, $props );
```

`ap_render_component()` (in `ui/src/loader.php`) validates the props against
`ui/components/<name>/component.json` and renders
`ui/components/<name>/render.php`. Markup and CSS exist in exactly one place —
the kit — so both the staging and live sites render identically.

```
Gutenberg block  ─┐
                  ├─→  props array  ─→  ap_render_component()  ─→  ui/components/<name>/render.php  ─→  HTML
Unyson shortcode ─┘
```

## How the kit reaches the verticals

The verticals do **not** import from `ui/` at runtime. `tools/sync-ui.ps1`
copies the whole `ui/` tree into each vertical's `lib/asset-panda-ui/`. So:

- **Edit `ui/`. Never edit `lib/asset-panda-ui/`** — it is a generated copy and
  your changes there will be overwritten by the next sync.
- After changing anything in `ui/`, run the sync:

```bash
pwsh tools/sync-ui.ps1
```

## Where a change belongs

- **Change how something looks / its markup** → `ui/components/<name>/` (then sync).
- **Add a new shared component** → new folder in `ui/components/` with
  `component.json` + `render.php` + `style.css` (then sync). See `hero/` as the reference.
- **Expose a component in the block editor** → `gutenberg/` (map block attrs → props).
- **Expose a component in the live page builder** → `unyson/` (map shortcode options → props).

Each layer has its own `CLAUDE.md` with the specific rules for that layer —
read it before working there.
