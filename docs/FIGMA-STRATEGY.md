# Figma Make vs Figma Design — recommendation

You asked whether Figma Make would make this better than working from the Figma
design file. Short answer: **no for production, yes for one specific thing.**

## Keep Figma Design as the source of truth

Figma Make generates working app code — React and Tailwind — from prompts and
designs. Config 2026 extended it with production code editing and added Code
Layers, which let a designer turn any layer into an interactive code layer.

None of that maps onto this project's output. You ship WordPress markup into two
page builders, one of which is a legacy Unyson install. React/Tailwind output
would have to be hand-translated, and hand-translation is the exact step that has
been introducing drift. You would be adding a conversion, not removing one.

There is a second, worse problem: Make regenerates. Ask it again and you get
different code. A source of truth has to be stable enough to diff against. Your
Figma file already has **variables and well-structured components** — that is
the asset worth building on, and it is what `get_variable_defs` reads to produce
your token contract.

## Adopt Figma Motion — this is the real win

Config 2026 added **Figma Motion**: timeline-based animation authored directly
in Figma Design, with keyframes, presets and easing that become reusable design
system elements the way fills and typography already are. Exports include CSS.

This matters because "animations are not on point" is on your list, and today
motion is the one part of the design that reaches you as prose rather than data.
Someone reads a description and picks an easing curve. That is a guess, and it
is unverifiable.

With motion authored in Figma, `get_motion_context` returns the actual keyframes,
duration and cubic-bezier. The extractor writes them into the spec, and
`npm run audit:motion` fails the build when the implementation does not match.
Animation moves from taste to contract.

**Action:** ask the designer to author motion on the Figma Motion timeline rather
than describing it in comments or a prototype. This is the single highest-value
change available on the design side.

## Where Figma Make is genuinely useful

One case: a complex interaction nobody can describe precisely — a multi-stage
scroll reveal, a tricky sticky-tab sequence. Have Make prototype it, then read
its output as an **executable spec** for timing and sequencing, and implement it
by hand in the kit (`ui/components/<name>/`).

Use it as a reference, never as a source. Do not paste Make output into the
pipeline.

## Also worth setting up: Code Connect

`add_code_connect_map` maps Figma components to your real components, so
`get_design_context` returns *your* class names instead of generic markup. Once
`ap-btn`, `ap-card` and the mega-menu items are mapped, extraction quality rises
noticeably and the "which existing component is this" question mostly disappears.

Worth doing after three or four sections are through the pipeline and the class
vocabulary has settled.

## Summary

| | Verdict |
|---|---|
| Figma Design file (variables + components) | **Source of truth.** Keep. |
| Figma Motion | **Adopt now.** Turns animation into verifiable data. |
| Code Connect | **Adopt after ~4 sections**, once class names stabilise. |
| Figma Make | **Not in the pipeline.** Reference-only for hard interactions. |
| Figma Code Layers | Useful as an interaction spec. Not a code source. |
