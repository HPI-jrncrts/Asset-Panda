# Handoff: NEW SITE (Asset Panda 2026 site on dev)

Written 2026-10-28. State as of ~23:05 Manila, Oct 28.
The old chat, [NEW SITE](claude://claude.ai/epitaxy/local_79954c53-c4b8-44bf-bf15-69618b665c56), was still running when this was written. **Stop it before you start the new one**, so two chats don't deploy to dev at the same time. Nothing in the old chat is deleted.

## The job
Rebuild www.assetpanda.com's 2026 design on dev (https://assetpanda1dev.wpenginepowered.com: WordPress, Gutenberg and the `asset-panda-ui-kit` plugin). At cutover, dev replaces www.

Joseph's standard (Oct 28):
- "treat the new site as NEW… its own assets, its own links"
- "beautiful, great in UX and accessibility"
- "make sure the website is ready for launch"

Adjustments come "from us", not only from Figma. Where Figma has copy, Figma wins. Otherwise use the live copy.

## Live on dev now
- **Plugins:**
  - kit 1.3.102. `C:\Sample Code\dist\asset-panda-ui-kit` already holds a **1.3.103** build, so check whether it was deployed before building again.
  - asset-panda-content 1.3.3
  - ap-media-localizer 1.2.0
  - asset-panda-site 1.2.0
- **Content:**
  - 0 links to www in page content (3,145 rewritten on Oct 28).
  - Image paths fixed in 186 posts.
  - Integrations hub (page 2312) plus 40 integration pages.
  - ROI calculator (page 2322), Compare (page 2303), Ursa AI, and the Who We Help mega menu.
  - 42 QA pages, including 26 hidden/noindex ones.
- **Checks passed:**
  - no half-faded blocks (14 pages at 2 widths)
  - forms skin (26 pages)
  - Figma parity for Ursa, Compare, the mega menu, ROI and Integrations

## Open work, in order
1. **Finish the sweeps that were running:** images (642 pages), then links. The log is in `C:\Sample Code\reports\launch-1028\`.
   - Dev sits behind Cloudflare rate limiting (429 errors on bursts of images), so pace requests.
   - Measure the limit so Joseph can send WP Engine the numbers.
2. **Help Desk page:** there's a ~900px empty band under "Move each question". Also review what the page-fixes agent (leaf-1.3.1) reported.
3. **Spacing pass on every page family.** Joseph: "a lot of extra spaces or huge spaces".
4. **Lighthouse (mobile),** run only when nothing else is hitting dev. Target 90+. The last scores, measured before the speed release, were Police 66, Fire 73, Asset Intelligence Platform 83 and Equipment 82.
5. **Re-check every item on Joseph's 10-27 QA list with evidence.** The list is in memory `ap-qa-push-2026-10-27`. Then update the WIP sheet: notes in column M, plus the site-wide PageSpeed note in row 92.
6. **/start-pro-trial/:** its HubSpot form 404s on live too. That needs Joseph or Jon, not code.

## Waiting on Joseph
- ROI button: "Free Demo" or "Book a Demo"?
- Figma says $28,800, the page shows $28,200.
- Compare page category labels and copy.
- Mega menu cards: OKC/Creston, or the Navigation Map cards?
- "Industry" or "Industrial"?
- Top bar height: 64px (Figma) or 72px (dev)?
- Video links for Slack, plus 5 integrations known by title only.
- Key Capabilities copy for 39 integration pages.
- Google Calendar consolidation.
- Custom-ERP pages, currently on hold.
- Same badges on every hub?
- Trash /start-pro-trial/, the AWS re:Invent page and the transition-track duplicate?
- Glow strength.
- Access to Jeph's compare sheet.
- Permission to download the Thunderly photos (Fire, Police).

## Rules for this work
- **Kit:**
  - Markup and CSS live only in the kit (`C:\Sample Code\ui\`). Never edit `lib/asset-panda-ui/` (see `C:\Sample Code\CLAUDE.md`).
  - Build with `qa1027\build-kit.mjs <version>` (full) or `qa1027\build-narrow.mjs` (narrow), both in the old chat's scratchpad below. The version must be higher than the last one deployed.
  - Don't run `build.mjs --site dev` as-is.
- **Sign-ins and live writes:**
  - Never type passwords or keys, and never sign in; Joseph signs in.
  - Writes to www (live) are blocked for Claude. Stage them so Joseph needs one click, leave the tab open with a clear title, and name pages by title.
- **Hard don'ts:**
  - Never POST to post.php on the live Unyson site.
  - Don't submit HubSpot forms on dev.
  - Don't delete page 1089.
  - The get-a-demo-calcom worktree is read-only.
- **Playwright** runs need `NODE_OPTIONS=--import=file:///C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/live-guard.mjs` and `MSYS_NO_PATHCONV=1`.
- **WIP sheet:** for each page that's ready, put the link in column J (highlighted yellow) and the status in K/L. Our own adjustments go in column M with a screenshot.
- Client-facing notes carry no AI attribution.

## Where things are
- **Launch checklist (gates):** `C:\Sample Code\.unlazy\launch-1028\` (PLAN.md, GATES.md)
- **Old chat's tools** (build scripts, Lighthouse, preview harness, backups): `C:\Users\josep\AppData\Local\Temp\claude\C--Sample-Code\4ce00505-a288-461f-8893-36ba978d4b50\scratchpad\`, in the folders `qa1027\`, `qa1028\`, `lh\`, `fig1028\` and `integ\`.
- **Sheets, ClickUp and Figma:**
  - WIP sheet: https://docs.google.com/spreadsheets/d/1gKA0PPMilyOY-xJALfgNB5HHGA0AITkSvMyb-gTAaV4/edit?gid=0#gid=0
  - Page list (Jeph): https://docs.google.com/spreadsheets/d/1ko_l4Sf_yrEsEoLwYSXLsG0oSfBZfira0pthLhsYnPo/edit?usp=sharing
  - ClickUp: https://app.clickup.com/90161661055/v/l/6-901615429345-1
  - Figma: https://www.figma.com/design/VW6cE9oR0LeIeowjwJvEqY/Website-Design-2026---Asset-Panda?node-id=3-598
- **Full old transcript:** `C:\Users\josep\.claude\projects\C--Sample-Code\4ce00505-a288-461f-8893-36ba978d4b50.jsonl`
- **Memory to read first:**
  - `feedback-working-rules-2026-10-27`
  - `ap-qa-push-2026-10-27` (the most current state is at the bottom)
  - `ap-integrations-pages-2026-10-28`
  - `ap-dev-page-pipeline`
  - `ap-site-header-component`
  - `feedback-do-it-dont-hand-off`
  - `feedback-staging-links-in-wip-sheet`

## First message for the new chat
> Read `C:\Sample Code\reports\handoffs-2026-10-28\NEW-SITE.md` and the memory files it lists. Check what is actually deployed on dev before changing anything, then continue from "Open work".

## Update 2026-10-29 01:15 (overnight run in progress)
- Kit 1.3.105: new Solutions-menu icons (Help Desk Ticketing = ticket, Asset Inspections = clipboard-check; Figma 7349:175242 / 7349:175264). Kit 1.3.106: Education hub glows softened; trust/awards marquee copies + homepage sector tab icons `loading="eager"` (WordPress adds lazy to sized images unless told otherwise). Gov hub picker (page 615) = `"variant":"hub26"`. /ursa-ai/ (2328) and /roi-calculator/ (2322) upload URLs made root-relative.
- Ledger `.unlazy/launch-1028/`: leaf-1.2.x all met (1.2.3 has G5 for the icons); leaf-1.3.1 G3 met, G1/G2 running; leaf-1.1.1 G2/G4/G5 met, G1 (full sweep) timed out at 2 h beside other sweeps → queued to re-run alone (`reports/launch-1028/leaf-1.1.1-reverify.log`), G3 passes after the fix; leaf-1.1.4 links sweep running (`chain-4.log`); leaf-1.4.1 Lighthouse chained after 1.3.1 (`leaf-1.4.1-gates.log`); leaf-1.1.3 G5 ABANDONED (start-pro-trial form id) = handoff.
- Then: branch ledgers (node-1.1 N1/N2, node-1.3, node-1.4 N1/N2), root GATES.md G0–G7 (G2/G3 are full sweeps again — run last), final REPORT.md gate status, open decisions 1–12 to Joseph (11 = Helpdesk spelling, 12 = gov picker look).

## 2026-10-29 evening — STOPPED HERE (Joseph: "take a break, save everything")

**Live on dev (assetpanda1dev) now:** UI kit **1.3.129**, asset-panda-content **1.3.8**. Every change below is deployed and on disk in `C:\Sample Code` (not committed — working tree on branch kit-1.2.9).

**Done and verified today (ledger .unlazy/launch-1028/gates, leaf-1.6.1 … leaf-1.6.19):**
- Batches 1–5 + afternoon notes: CTA hover = highlight + edge light (not liquid), burger ring morph, login icon hover, Who We Help 4 posts 2×2 (latest-posts.php, auto-updates on publish), View All Industries → /#industries, cards 4/6, industries pre-rendered aurora (homepage raster −80%), phone hero order (badges under button, art 10px below, icon strip at foot), every hero eyebrow 16px under the header on phones, phone footer round splashes, AIP phone wires, white sections, help-desk hero gradient, pinned solution steps heading, solution CTA blue card (splash only /barcode-labels/ page 1308), integrations card shuffle, 91 sharper images, tutorials deck gallery (autoplay muted, loop, swap), ROI page, hub filters, post headers, mega menu.
- Footer seam check passes on 8 page types at 1440 and 390 (leaf-1.6.19 G1).
- Posts: Quick Answer / Key Summary / Key Takeaways all styled as the Figma Key Takeaways (teal 20/800 label, normal text) via includes/callouts.php — classes only, text untouched.

**Open / next steps:**
1. leaf-1.6.19 G2 (bg-survey --gate) failed once more after kit 1.3.129 — run `node .unlazy/launch-1028/checks/bg-survey.mjs` to see which section is still off-white and set it to #fff.
2. leaf-1.6.19 G3 — tutorials check was fixed after its run (YT API keeps the frame address); re-run `node C:/Users/josep/.claude/skills/unlazy/scripts/gate-check.mjs --root . --cwd . --approve --timeout 1800 .unlazy/launch-1028/gates/leaf-1.6.19.md`.
3. leaf-1.6.1 G1 (homepage batch-1 check): its liquid assertion was rewritten for the new edge-light hover; re-run to confirm.
4. Two agents were still running when the PC went off — their files may be half-done:
   - **Blog post style audit vs Figma** (owns asset-panda-content assets/ap-content.css + includes/callouts.php, class-adding only, NO content changes). Figma: section "Resources Tab - Blog" 4610:12400 — "Blog Detail Page (Listicle)" 5434:79954 for ranked best-of posts, "Blog Detail Page" 7219:87997 for every other post; Sticky Title Bar 5487:21981, Floating TOC 7224:2, Featured-Image variants. Body column 660px vs 928px image. Re-launch to finish; then bump content to 1.3.9 and deploy.
   - **AP Template Library plugin** (gutenberg/plugins/ap-template-library/, zip to dist/ap-template-library-1.0.0.zip): library of templates read from real reference pages (solution, product/who-we-help, hub, platform, blog, case study, compare), builder with boxes/fields/media pickers generated from block attributes, "Send to Draft" creates a draft page/resource. Check the folder; re-launch to finish and test on the Local app, then upload to dev.
5. Not yet re-verified in a real browser on dev: tutorials autoplay/advance on real YouTube, Ursa demo (interactive only on assetpanda.com hosts).
6. Final QA report republish + message to Joseph (artifact FWPmbhDkgHXJiud7txfEMP) still pending.

## 2026-10-29 late — RESUME HERE (supersedes the "STOPPED HERE" list above)

**Live on dev now:** UI kit **1.3.131**, asset-panda-content **1.3.9**, asset-panda-site **1.2.1**, ap-template-library **1.0.1** (new, active). Nothing committed (branch kit-1.2.9). Every gate ledger in `.unlazy/launch-1028/gates/` is ALL MET, including leaf-1.6.1/1.6.8/1.6.10 G2 reviews and the new leaf-1.7.1–1.7.4.

**Done today after the break:**
- Blog-style audit deployed (content 1.3.9). `checks/blog-posts.mjs`: 9 posts × 1440/390 pass. Text untouched — the content items below still wait for Joseph.
- **Partners page** (dev 2321) rebuilt from Figma 7368:176529: kit 1.3.131 (opt-in props on solution-hero, solution-features, founder-quote, demo-cta; 244/244 existing usages byte-identical), 7 assets in `/wp-content/uploads/2026/10/partners-*`, page content replaced (old version in revisions). `checks/partners.mjs` passes at 1440/1024/390. The real HubSpot form (024ab0e4) draws in Chrome with 5 fields. WIP sheet row **65** updated: H Figma link, K, L. (Row numbers from gviz CSV are WRONG — it skips rows; always confirm with the Name Box.)
- **Template Library** plugin on dev: 20 templates all resolve. Send to Draft was tested on Partners (draft 5069, content identical, now in trash). 1.0.1 fixes the `&#8211;` in the "Draft created" notice.
- **Tracking parity:** report at `reports/launch-1028/tracking-parity/REPORT.md`. Applied on dev: live JSON-LD on 7 pages (`_ap_jsonld`), pricing thank-you (2411) calendar off + live text, hsforms preconnect/dns-prefetch in asset-panda-site 1.2.1 (www only; its Local test suite was NOT run — needs Local MySQL). `checks/parity-fixes.mjs` passes. Second batch DONE: live JSON-LD written on 35 more pages (`checks/parity-jsonld2.mjs` passes; leaf-1.7.1 G3). Not written, for Joseph: /solutions/government/fleet-management/ (staging 1555 is at /solutions/fleet-management/) and 3 resources whose staging value is FAQPage-only (REPORT.md §6).
- `checks/deploy-1115.mjs` amended: solution pages keep the blue CTA card; /barcode-labels/ uses the splash look.

**Waiting on Joseph:**
1. Parity decisions D1–D10 (REPORT.md §7). The biggest one is **D3**: the /roi-calculator/ form redirects to the demo thank-you page, so it counts as a demo conversion.
2. Cutover-only steps (REPORT.md §6): F1 demo widget to live values on pages 33/2405, F6 AP Demo Guard settings, F7 Redirection import.
3. Partners: button says "Book Now" (Figma copy) — "Register"? Extra required Phone field. H1 is now "Join Our Reseller Program".
4. Blog content changes the style agent listed but did not make: fire post Key Summary rules, listicle Key Takeaways bullets vs paragraphs, mini-CTA "Book a Demo" vs "Talk to an Expert", compare badge-row sizes, MaintainX "Advantage:" lines.
5. Real-browser checks only Joseph can see: tutorials autoplay/advance on real YouTube; the Ursa demo is interactive only on assetpanda.com hosts.
6. Follow-ups: 71 upscaled images still need real sources; `article-cta` overrides belong in `ui/components/article-cta`; the demo-cta relabel bug on directly drawn forms is flagged as a separate task.

**Compare page (Figma 7333:173310 updated 2026-10-29):** the frame was redesigned (logo-card directory, guide rows, industry cards, spreadsheet cards, numbered list, centred FAQ, closing card). A helper agent is rebuilding it as kit sections into `scratchpad/compare1029/` (page markup, assets, shots); then: build kit ≥1.3.132, deploy, upload assets, save the markup to the /compare/ page, verify at 1440/1024/390, update leaf-1.2.2 and the WIP row.

## 2026-10-29 evening (kit 1.3.137) — RESUME HERE
- Live on dev: kit **1.3.137**, content 1.3.9, site 1.2.1, template-library 1.0.1. Nothing committed.
- **Protection run** for every item Joseph noted: `sh .unlazy/launch-1028/checks/regression.sh` → `REGRESSION 17/17` on 1.3.136 and 1.3.137. Run it before AND after every deploy; fix or roll back any failure before reporting.
- Shipped today after the break: compare page (Figma 7333:173310), trial page 2406 rebuild, Partners form reskin, closing blue card = homepage footer card geometry on all inner pages, sitewide CTA button rule (ui/src/tokens.css, = header nav buttons), hero copy/art spacing (solution/vertical/assist/readiness/hub heroes), why-cards centred splash, hub + media-split puddles between rows, energy scene cleaned, footer phone splashes (6), tutorials deck, EP circles sized, SR backdrop unstretched, Ursa ring3, integrations sticky categories, QA-chat fixes F1–F5 (`reports/qa-1029/fixes/check-fixes.mjs` → 18/18).
- Joseph opened a second chat "Asset Panda site QA": it checks pages and prepares append-only fixes in reports/qa-1029/fixes/; only this chat builds/deploys.
- Open: EP circle PLACEMENT (woman circle behind the Equipment Assignment card) — needs Figma positions; help-desk hero (spaced vs live's overlap — asked Joseph); tool-tracking left vs right card (asked); N1 Talk-to-Sales contrast on purple, N2 phone button widths (Higher Ed/Local Gov/Public Works), N4 AIP phone art edge; Figma-QA questions Q1–Q10 in reports/launch-1028/figma-qa/findings/; Ursa demo interactive only on assetpanda.com hosts (vendor CSP) — proof shots/r1029c/ursa-live-demo-1440.png.
- **Update 2026-10-29 ~16:45:** dev = kit **1.3.139** + content **1.3.10**. Shipped QA-chat batch 2 (F6 phone drawer login links, F7 more CTA classes on the header-button scale, F8 per-line gradient titles, F9 one-line stat cards, F10 resource-post buttons, F11 AIP phone wires behind cards) → `node reports/qa-1029/fixes/check-fixes.mjs` 28/28; kit 1.3.138 capped the product hero at its 1440 size on wide screens. Protection run 17/17 (blog-posts version pin relaxed to ≥1.3.9). Integrations closing button relabelled "Free Demo". Open: Fire product page still shows the Police dashboard screenshot (Figma 6202:79485 is the Fire one; the section's frame expects the Police shot's 2.07:1 framing — needs a Fire screenshot in that framing or an image-fit option), N1 Talk-to-Sales contrast, EP circle placement, help-desk hero spacing vs live, tool-tracking card side.
