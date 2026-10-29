# QA 2026-10-29: fixes ready to ship (from the full-site QA chat)

Joseph's call (2026-10-29): the QA chat checks every page and prepares fixes; **the NEW SITE chat ships them**,
so only one chat builds and deploys the kit. Nothing here has been written to `ui/` or deployed.

Each fix is a block to **append to the end** of the named file in `C:\Sample Code\ui\` (appending keeps it
safe next to your helpers' in-flight edits). Every fix was injected into the real dev page and checked at the
listed sizes; before/after images are in `C:\Sample Code\reports\qa-1029\report\img\`.

| # | Append this file | to the end of | What it fixes | Checked on |
|---|---|---|---|---|
| F1 | `F1-demo-booking.style.append.css` | `ui/components/demo-booking/style.css` | /get-a-demo/ testimonial card cut the author's name and logo in half on laptop screens (the hero is one screen tall). Slides now fit the card; title + author always show; screens ≤999px tall get the compact card the ≤899px rule already uses. | 1024×768, 1280×720, 1366×657, 1440×700/790/860/900/950/1000, 1536×730, 1920×960/1080 |
| F1 | `F1-demo-booking.behaviour.append.js` | `ui/components/demo-booking/behaviour.js` (after the closing `})();`) | `fitQuotes()`: clamps each quote to the lines that fit, so it ends on "…"; re-fits on load, resize, fonts ready and when the hero column resizes (watches `.ap-d26-v1-side`, never the card, so no loop). Sets `html[data-ap-qfit]`. Also fixes the same cut card on /get-a-quote-pro/. | same, plus /get-a-quote-pro/ at 1440×700–1000 |
| F2 | `F2-tokens.append.css` | `ui/src/tokens.css` | 28 newer components were missing from the host-baseline `:where()` list, so their unstyled text took the staging theme's 22px / weight 300 / -0.1px. Careers, Client Reviews, FAQ, Our Company, Resource Center, Tutorials read thin and large; resource posts' related cards had 22px excerpts. Same zero-specificity rule; merge into the list when convenient. | census before/after on those 6 pages; no height or style change on /integrations/, /integrations/slack/, /compare/, /roi-calculator/, a blog post, a booking page, the AWS landing page |
| F3 | `F3-trust-strip.style.append.css` | `ui/components/trust-strip/style.css` | Education hub on phones/tablets: logo band 431px tall (Government's is 179px) with ~250px of blank space; "Trusted across education operations." caption pushed off the right edge. Cause: the stacked band still wrapped, so its flex line took the ticker's ~1000px width. Scoped to `--eduhub`, ≤900px only. | 390, 768, 1440 (desktop unchanged) |
| F5 | `F5-login-splash.style.append.css` | `ui/components/login-splash/style.css` | On phones the event artwork (480px Panda Pit Stop logo on /login-splash-1-a/ and siblings) ran off the right edge. Below 576px art images fit the column; desktop keeps live's behaviour. | 1-a, 3-b, 5-p at 390; 1-a at 1440 unchanged |
| F4 | `F4-page-cards.style.append.css`, `F4-page-split.style.append.css`, `F4-card-grid.style.append.css` | each component's `style.css` | `--soft` sections were #f9fafb and ended in a hard grey-to-white edge (Careers, Contact, Our Company ×2, 3 Government guides). Joseph's rule: sections without a gradient are pure white. The colour wash stays. | /careers/ 1440; cards keep their borders/shadows |

`trust-strip`, `card-grid` and `ui/src/tokens.css` are on your helpers' do-not-touch list, so please apply F2/F3/F4
after those helpers finish (they're appends, so order doesn't matter).

## Batch 2 (detailed pass with Figma, 2026-10-29 evening) — F6–F11, not deployed yet

| # | Apply | What it fixes | Checked on |
|---|---|---|---|
| F6 | `git apply reports/qa-1029/fixes/F6-site-header.render.patch` (10 lines added to `ui/components/site-header/render.php`, applies cleanly to today's file) + append `F6-site-header.style.append.css` to `ui/components/site-header/style.css` | Phones had **no way to log in**: the login menu lives in the desktop header only, and the drawer had no login. The drawer now ends with "Log in" and the same two logins as the desktop menu (Asset Panda Pro, Asset Panda Classic). Figma's mobile menu has a Login button. Descriptions stay hidden on phones (Jeph's rule). | 390 drawer, injected |
| F7 | append `F7-tokens.cta-classes.append.css` to `ui/src/tokens.css` | Four CTA classes your sitewide button rule doesn't list yet: `.ap-integration-detail__btn`, `.ap-integration-more__btn` (all 40 integration pages: 48px/18px/600), `.ap-card-grid__btn` (Contact, Government guides: 44px/15px/600), `.ap-article-cta__btn` (ad landing pages). Same values; merge into your list when convenient. | slack, contact, gov guide, OT ad page at 1440/390 |
| F8 | append `F8-tokens.gradient-per-line.append.css` to `ui/src/tokens.css` | A gradient phrase that wraps (e.g. "Equipment Lease / Management & / Tracking") got one gradient stretched over its lines laid end to end, so lines 2–3 read solid teal. Figma ramps indigo→teal on every line: `box-decoration-break: clone` on the 26 inline gradient-phrase classes. No layout change (14 page×width checks: identical heights). | leased, fleet, electric, gov hub, equipment, AIP, fire, energy, home, careers |
| F9 | append `F9-intel-dashboard.style.append.css` to `ui/components/intel-dashboard/style.css` | Equipment Management dashboard: the fixed-width (176px) stat card wrapped "Ready for deployment", grew taller and the avatar covered "deployment". Stat cards now fit their text on one line (176–260px); the two list cards keep their size. | equipment, IT, asset mgmt, tool tracking at 1440/1024 |
| F10 | append `F10-asset-panda-content.ap-content.append.css` to `gutenberg/plugins/asset-panda-content/assets/ap-content.css` (content plugin version bump) | Resource-post buttons ("Talk to an Expert", "Book a Demo", "Jump to the comparison table", core buttons) were 48px/16px/600, outside the CTA scale. | 2 compare posts + 1 blog post at 1440/390 |
| F11 | append `F11-asset-scene.style.append.css` to `ui/components/asset-scene/style.css` | AIP on phones: the connector lines were drawn **on top of** the stacked "One platform…" cards and the intro paragraph. The intended "lines behind the cards" rule loses to the older `position:static !important` block; restated so the cards (and intro) sit above the wires. Desktop untouched (≤1000px only). | 390 live viewport, desktop unchanged |

Proof before you ship: `MSYS_NO_PATHCONV=1 node reports/qa-1029/fixes/check-fixes.mjs --inject` → `QA FIXES 28/28 PASSED`.
After you deploy F6–F11, the same command without `--inject` should also print 28/28 (today it fails the F6–F11 checks, as it should).

Also for your helpers (not fixed here):
- **Fire product page shows the Police dashboard mock** (`police-bg-features.webp`: FIREARM / EVIDENCE / BODY CAMERA, "Precinct 2 / Evidence Room / Locker Room") under "Fire departments aren't all the same." Live www has the same image. Figma has the Fire version (frame 6202:79236, Features section node 6202:79366, lower half: ENGINE / LADDER / THERMAL, "Station 12"): export it and swap the image on page 608.
- **N1 still open** on the new build: the outline "Talk to Sales" / "Talk to an Expert" / "Explore the Resource Center" button on the purple closing card is still teal on purple (Ursa, Integrations, Compare and the integration pages show it on the new captures). The integrations hub's closing card also still says "Book a Demo" (Free Demo rule).

## After you build and deploy

```bash
cd "C:/Sample Code" && MSYS_NO_PATHCONV=1 node reports/qa-1029/fixes/check-fixes.mjs
```
Expect `QA FIXES 18/18 PASSED`. On today's dev it prints 0/18 for F1–F5 (control run: every check fails for the right reason).

## Findings for your helpers (not fixed here; those components are yours)

1. **Closing CTA card, every inner page**: the outlined "Talk to Sales" / "Talk to an Expert" button is teal
   `#00a991` on the card's purple; contrast **1.5:1** against the light end `#6666ee` (needs 4.5:1). On product
   pages the teal quote line in the purple card ("When readiness is questioned…") is also hard to read on phones.
   Suggest white text + translucent white border on dark cards. Images: `n1-talk-to-sales.jpg`, `n1-fire-quote.png`.
2. **Button widths in one group (phones)**: "Explore Asset Intelligence" and "Meet Ursa" stack at two different
   widths on Higher Ed / Local Government / Public Works (K-12 shows them full width). Falls under your site-wide
   button size rule. Image: `n2-buttons.jpg`.
3. **Hero copy runs into the floating record card (desktop)**: Water, CMMS (also reported on Electric, Hospital
   Facilities, Pharmaceutical, Aviation, Maritime, Compliance, Lifecycle, Facilities). Part of your hero spacing
   rework. Image: `n3-hero-water.jpg`.
4. **AIP on phones**: hard horizontal edge in the art behind the panda, under the Ursa orb. Image: `n4-aip-edge.jpg`.

Full report (all pages, screenshots, arrows): see the QA report page link in the QA chat; source
`C:\Sample Code\reports\qa-1029\report\index.html`.
