# Token reconciliation — Figma vs the existing build

Source: `get_variable_defs` on node `4331:20126`, file `VW6cE9oR0LeIeowjwJvEqY`
("Website 2026 — Asset Panda"). Compared against `design-system/src/tokens.css`.

Per CLAUDE.md, conflicting tokens **block** — they are reported, never silently merged.

---

## 1. Confirmed correct

The existing `tokens.css` holds up well. Verified identical to Figma:

Indigo 50–950, Emerald 600/100/50, Yellow 500/300/100/50, Amaranth 500, Red 400/50,
Violet 800/200/100, Slate 50–600, Athens 50–800, heading `#25272c`, body `#5b616e`,
subtle `#b6bac3`, muted `#d8dbdf`, border `#edeef1`.

Layout also matches: page 1440, content-xl 1120, content-md 800, content-sm 640,
`pad-x-2xl` 160.

Body type scale matches: 12 / 14 / 16 / 18.

---

## 2. BLOCKING — must be resolved before building

### 2.1 Two conflicting heading scales

Figma defines both, with different values for the same level:

| Level | `Headline/*` | `Headlines-Desktop/*` |
|---|---|---|
| H3 | — | **24** |
| H4 | **24** | **18** |
| H5 | **20** | **16** |
| H6 | **16** | — |
| Eyebrow | **14** | **16** |

`tokens.css` currently uses the `Headline/*` set (h4 24, h5 20, h6 16, eyebrow 14).
**Which set is authoritative?** If `Headlines-Desktop/*` is the newer one, every
heading below H2 in the current build is wrong.

### 2.2 Eyebrow letter-spacing is ambiguous

Figma: `letterSpacing: 4` on a 14px eyebrow.
`tokens.css` has `--ap-eyebrow-tracking: 0.04em` — which is **0.56px**.

If Figma means 4px, the correct value is `0.2857em` — roughly **7x wider** than what
is currently shipping. This is visible to the eye across a whole eyebrow line, and it
is on every section. Needs one confirmation from the designer.

### 2.3 Two body-text greys

- `Text/Body/Default Dark` = `#5b616e` (used by `tokens.css`)
- `Colors/Text/Body Text` = `#4b5563` (cool-gray 600)

Both are live in the file. `#4b5563` also appears in the existing
`platform-stack-hover-fix.md` overrides, so both are in use on the live site today.
One must win.

### 2.4 A second, foreign design system is in the file

The variable set contains a complete **shadcn/Tailwind** kit that does not belong to
Asset Panda:

`base/primary`, `base/secondary`, `base/muted-foreground`, `base/card`,
`tailwind colors/*`, `neutral/5…100`, `spacing/1…4`, `height/h-4…h-16`,
`width/w-4…w-10`, `border-radius/rounded-*`, `stroke-width/*`.

It also brings **two non-brand fonts**:

- `Font family/font-family-body` = **Geist**
- `Popular Pro/Paragraphs/P(14)` = **Inter**

The brand font is Manrope. Anything drawn with Geist or Inter in Figma is either a
stray component from a purchased kit or an unconverted third-party block.

**Risk:** an extractor pointed at a component built on this kit will emit Tailwind
tokens and the wrong font, and it will look plausible. Recommend the designer either
deletes this collection or moves it to a clearly-marked archive page.

---

## 3. Not in Figma — invented by the previous build

`tokens.css` defines radii that Figma does not have:

| Token | Value | In Figma? |
|---|---|---|
| `--ap-radius-xs` | 2px | yes (`rounded-xs`, but from the Tailwind kit) |
| `--ap-radius-sm` | 4px | **yes** (`Border/Radius/sm`) |
| `--ap-radius-md` | 8px | **yes** (`Border/Radius/md`) |
| `--ap-radius-lg` | 16px | **yes** (`Border/Radius/lg`) |
| `--ap-radius-xl` | 24px | **no** |
| `--ap-radius-2xl` | 32px | **no** |

Cards on the live site use 24px. Figma's largest defined radius is 16px. Either the
card radius is wrong on live, or Figma is missing a variable. Worth one check.

Similarly, `--ap-shadow-*` and `--ap-dur` / `--ap-ease` have **no Figma source at
all** — they were invented. Motion in particular should come from Figma Motion via
`get_motion_context`, not from a guessed 200ms.

---

## 4. New from Figma, missing from tokens.css

| Figma | Value | Note |
|---|---|---|
| `Section/padding y/pad-y-lg` | 112 | section rhythm, not currently tokenised |
| `Section/padding y/pad-y-2xs` | 20 | |
| `Section/padding x/pad-x-md` | 64 | |
| `Grid Guides/gap/grid-gap` | 28 | grid gutter — distinct from the 24 spacing step |
| `Opacity/Neutral/White Opaque/*` | White 15/60, transparent | overlay scrims |
| `Text/Body/Light` | `#ffffffcc` | body text on dark sections |
| `Utility/Button/md` | 16px, **weight 600** | current build uses 700 for buttons |

Note the button weight: Figma says **SemiBold 600**, the existing `tokens.css` has no
button weight and inherits bold. Small, but it is exactly the class of "little thing"
that costs confidence.

---

## Recommended resolution order

1. Designer confirms **2.1** (which heading scale) and **2.2** (eyebrow tracking)
2. Designer confirms **2.3** (which body grey) and the **24px card radius** in §3
3. Designer archives or deletes the Tailwind/shadcn collection in **2.4**
4. I regenerate `tokens.css` + `theme.json` from the resolved set and re-verify

Items 1–3 are ~15 minutes of designer time and remove an entire category of drift.
