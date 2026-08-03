# Why this setup looks the way it does

## The problem being solved

The site is built twice — Unyson on live, Gutenberg on dev — from one Figma
design. The reported symptoms were "UI isn't matching, some layouts are
overlapping, animations are not on point... little things [that] break quality
confidence with the client."

Those symptoms are not a prompting problem. They are a **feedback problem**.

The previous workflow was:

```
Figma → static HTML → copy/paste into a builder → look at it → spot a bug → paste a patch
```

Three things about that loop guarantee drift, no matter how carefully anyone works:

1. **Nothing could see the rendered result.** Correctness was judged by eye,
   after the fact, on a Cloudflare-cached page. A 6px overlap at 1024px, a 22px
   heading that should be 24px, a 300ms ease that should be 550ms
   `cubic-bezier(0.22,1,0.36,1)` — none of those are visible to a human scanning
   a page. They are exactly the "little things."

2. **Styling lived inside the builder blocks.** `platform-stack-hover-fix.md` in
   this repo is 60 lines of `!important` overrides appended to a single block.
   That is a specificity war against the theme, fought separately in each
   builder. Two copies, hand-maintained, diverging from the first edit onward.

3. **There was no diff.** Base64 payloads pasted into a CMS cannot be reviewed,
   reverted, or compared. There was no git history for any of it.

## The three structural changes

### 1. One CSS source, delivered to both sites

`mu-plugins/ap-design-system.php` enqueues a single compiled bundle on **both**
WordPress installs at priority 999 — after the theme, so source order wins and
`!important` is never needed. The builder blocks contain nothing but semantic
markup with `ap-*` classes.

Same CSS + same markup = same render. The two environments *cannot* drift,
rather than merely being *supposed* not to.

`/ap-version` on each site returns the bundle hash. Different hashes mean any
visual comparison between the sites is meaningless — check it first, always.

### 2. One markup source, two emitted payloads

`src/sections/<name>.html` is authored once. `npm run emit` writes
`dist/gutenberg/<name>.html` and `dist/unyson/<name>.html` with **byte-identical
bodies** and different wrappers. Nobody ever hand-edits one of the two.

### 3. Machine verification instead of eyeballing

`specs/<section>.spec.json` turns "matches Figma" from an opinion into a
contract, written during extraction from what the Figma MCP actually returns.
Four independent audits run against it:

| Audit | Catches | Why it exists |
|---|---|---|
| **Layout** | overlapping boxes, elements escaping the section, clipped text, collapsed containers, horizontal scroll | directly targets "layouts are overlapping" |
| **Tokens** | every computed font-size, colour, radius and gap vs the Figma variable | catches the 2px and one-shade-off errors nobody sees |
| **Motion** | duration, easing, whether the animation fires at all, and **scroll jitter** | targets "animations are not on point"; the jitter probe catches the hero shake by design |
| **Pixel** | structural divergence the other three cannot see | weakest signal, read last |

Triage order is **layout → tokens → motion → pixel**, and it is not arbitrary: a
pixel failure is nearly always a symptom of one of the first three. Tuning values
until a diff percentage drops is how you get a build that passes and still looks
wrong.

## Why local preview matters

`npm run preview` serves each section standalone with the same bundle the
mu-plugin enqueues. Verifying there is fast, uncached, and isolates the design
layer from WordPress entirely.

That gives a clean two-tier signal:

- **fails on preview** → the build is wrong
- **passes on preview, fails on dev/live** → the theme is interfering

Those are different bugs with different fixes, and previously they were
indistinguishable.

## What this does not do

- It does not verify copy, SEO or analytics.
- It does not replace design review. It verifies conformance to the spec; if the
  spec is wrong, it will confidently certify the wrong thing. That is why the
  extractor writes the spec from Figma and is forbidden from guessing.
- The pixel diff will never read 0%. Figma and Chromium rasterise text
  differently. 1–2% on a text-heavy section is normal and meaningless.
