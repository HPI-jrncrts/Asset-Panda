# Verification layer — drop into the kit repo

This folder bolts onto the existing Asset Panda kit (`ui/` + `gutenberg/` + `unyson/`).
It adds nothing to how pages render; it only checks that what renders matches Figma.

## Why it exists

The kit already guarantees the two verticals render the SAME markup — `ui/` is the
only place markup lives, and both adapters call `ap_render_component()`. That kills
drift *between* dev and live.

What the kit does not do is check either of them against **Figma**. That is this layer:

| Audit | Catches |
|---|---|
| `npm run audit:layout` | overlapping elements, escaping the container, clipped text, horizontal scroll |
| `npm run audit:tokens` | computed font-size / colour / radius / gap vs the Figma variable |
| `npm run audit:motion` | wrong duration or easing, animations that never fire, scroll jitter |
| `npm run audit:pixel`  | structural divergence the other three can't see |

`npm run verify -- --section <name>` runs all four. Triage in that order — a pixel
failure is almost always a symptom of one of the first three.

## How it hooks into the kit

- **Tokens** are read from `ui/src/tokens.css` — the same file the components use.
  A token change therefore cannot pass the audit while the page still renders the
  old value.
- **Specs** live in `specs/<component>.spec.json`: the Figma node id plus the
  assertions that define "matches Figma". Written during extraction from what the
  Figma MCP returns — never written afterwards to match whatever the build produced.
- **References** live in `refs/<component>@<width>.png`, exported from Figma.
- Audits point at a rendered URL (`--target dev` or `--target live`), so they test
  the real thing: kit component + adapter + theme CSS together.

## What was deliberately removed

`emit.mjs` and `audit-paste.mjs` were built for a paste-based workflow. The kit
renders server-side, so nothing is ever pasted and both are obsolete. Likewise the
`mu-plugins/` scoping plugin: `ap_render_component()` already enqueues a component's
CSS only when that component renders, so pages that don't use the kit get nothing.
Isolation is structural, not configured.

## Setup

```
npm install
npx playwright install chromium
cp .env.example .env      # set AP_DEV_URL and AP_LIVE_URL
```

Then, per component: extract from Figma -> write the spec -> build in `ui/` ->
`sync-ui.ps1` -> `npm run verify`.
