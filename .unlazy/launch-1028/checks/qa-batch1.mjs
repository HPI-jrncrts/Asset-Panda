// Joseph's homepage batch 1 on dev: the homepage still loads every image and leaves nothing faded at 1440/390 (via
// images.mjs + fade.mjs on a one-page list), the tablet (1024) layout shows the comparison grid in three columns and
// the industries grid with no empty right-hand track, every System-of-Record card fills as it scrolls at 1024, the
// liquid hover is wired (blob markup present on green outline and gradient CTAs; an outline CTA ends hovered with
// white text), and tapping an industries tab scrolls smoothly (scroll position moves over several frames).
// --reduced: with prefers-reduced-motion no blob markup is injected and the outline CTA still fills green/white.
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { DEV, launch, guardPage, flag } from './lib.mjs';
const reducedMode = flag('--reduced');
const problems = [];
const ok = (cond, label, detail) => { console.log((cond ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!cond) problems.push(label); };
const browser = await launch();
const ctxOpts = reducedMode ? { reducedMotion: 'reduce' } : {};
if (!reducedMode) {
  fs.writeFileSync('.unlazy/launch-1028/home.json', JSON.stringify(['/']));
  for (const [script, token] of [['images.mjs', 'IMAGE CHECK PASSED'], ['fade.mjs', 'FADE CHECK PASSED']]) {
    let out = '';
    try { out = execFileSync('node', ['.unlazy/launch-1028/checks/' + script, '--paths', '.unlazy/launch-1028/home.json'], { encoding: 'utf8', timeout: 900000, env: process.env }); } catch (e) { out = String(e.stdout || '') + String(e.stderr || ''); }
    ok(out.includes(token), 'homepage ' + script, out.trim().split('\n').slice(-1)[0].slice(0, 120));
  }
}
// Tablet
const t = await browser.newContext({ viewport: { width: 1024, height: 1000 }, ...ctxOpts });
let page = await t.newPage(); await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1200);
const tablet = await page.evaluate(() => {
  const table = document.querySelector('.ap-compare--home .ap-compare-table');
  const cols = table ? getComputedStyle(table).gridTemplateColumns.split(' ').filter(Boolean).length : 0;
  const grid = document.querySelector('.ap-industries .ap-sector-grid');
  let gapRight = null;
  if (grid) { const g = grid.getBoundingClientRect(); const cards = [...grid.querySelectorAll('.ap-sector-card')].slice(0, 2); const right = Math.max(...cards.map((c) => c.getBoundingClientRect().right)); gapRight = Math.round(g.right - right); }
  return { cols, gapRight, cards: document.querySelectorAll('.ap-pstack [data-ap-pstack-panel]').length };
});
ok(tablet.cols === 3, 'tablet: comparison grid has three columns', 'columns=' + tablet.cols);
ok(tablet.gapRight !== null && tablet.gapRight <= 24, 'tablet: industries grid fills the width', 'empty space right of the cards=' + tablet.gapRight + 'px');
// Scroll through the System-of-Record cards and record which ones took the fill class.
const filled = await page.evaluate(async () => {
  const panels = [...document.querySelectorAll('.ap-pstack [data-ap-pstack-panel]')];
  const seen = new Set();
  for (const p of panels) { window.scrollTo({ top: p.getBoundingClientRect().top + window.pageYOffset - window.innerHeight * 0.4 }); await new Promise((r) => setTimeout(r, 450)); panels.forEach((q, i) => { if (q.classList.contains('is-mobile-scroll-active')) seen.add(i); }); }
  return { total: panels.length, filled: [...seen].sort((a, b) => a - b) };
});
ok(filled.total > 0 && filled.filled.length >= filled.total - 1, 'tablet: System-of-Record cards highlight per scroll', 'filled ' + filled.filled.length + ' of ' + filled.total);
await t.close();
// Desktop: liquid wiring + hover end state; smooth scroll on the industries tab.
const d = await browser.newContext({ viewport: { width: 1440, height: 900 }, ...ctxOpts });
page = await d.newPage(); await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now() + 'd', { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1500);
// Since 2026-10-29 PM the CTA hover is a highlight + edge light (Joseph replaced the liquid fill): motion.js tags
// .ap-cta-outline / .ap-cta-grad and adds one .ap-cta-fx SVG per button (none under reduced motion).
const wiring = await page.evaluate(() => ({ outline: document.querySelectorAll('.ap-cta-outline > .ap-cta-fx').length, grad: document.querySelectorAll('.ap-cta-grad > .ap-cta-fx').length, goo: !!document.getElementById('ap-cta-defs'), candidates: document.querySelectorAll('[data-ap-cta]').length }));
if (reducedMode) {
  ok(wiring.outline === 0 && wiring.grad === 0, 'reduced motion: no edge-light markup injected', JSON.stringify(wiring));
} else {
  ok(wiring.outline >= 1 && wiring.grad >= 1 && wiring.goo, 'edge-light hover wired on outline and gradient CTAs', JSON.stringify(wiring));
}
const btn = page.locator('.ap-header-demo').first();
if (await btn.count()) {
  const before = await btn.evaluate((el) => getComputedStyle(el).color);
  await btn.hover(); await page.waitForTimeout(900);
  // Since kit 1.3.111 the animated label is a two-colour gradient clipped to the glyphs (white half shown at the end
  // of the hover), so "white text" there means the label's background sits at 0%; under reduced motion the button
  // itself turns green with white text.
  const after = await btn.evaluate((el) => { const lbl = el.querySelector('.ap-liquid-lbl'); return { color: getComputedStyle(el).color, fill: getComputedStyle(el).backgroundColor, lblPos: lbl ? getComputedStyle(lbl).backgroundPositionX : null, blobs: [...el.querySelectorAll('.ap-liquid > span')].map((s) => getComputedStyle(s).translate).join('|') }; });
  const white = after.lblPos !== null ? (after.lblPos === '0%' || after.lblPos === '0px') : after.color === 'rgb(255, 255, 255)';
  ok(white, (reducedMode ? 'reduced motion: ' : '') + 'outline CTA text is white when hovered', before + ' → ' + (after.lblPos !== null ? 'label at ' + after.lblPos : after.color) + (reducedMode ? ' fill=' + after.fill : ' blobs=' + after.blobs));
  if (reducedMode) ok(after.fill === 'rgb(0, 169, 145)', 'reduced motion: outline CTA fills green when hovered', 'fill=' + after.fill);
} else ok(false, 'header Free Demo button present');
if (!reducedMode) {
  // The industry filter chips are a phone control (hidden on desktop): test the smooth scroll at 390.
  const mctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  page = await mctx.newPage(); await guardPage(page);
  await page.goto(DEV + '/?nc=' + Date.now() + 'm', { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(1500);
  const tab = page.locator('.ap-industries .ap-sector-tab').nth(2);
  if (await tab.count()) {
    await tab.scrollIntoViewIfNeeded(); await page.waitForTimeout(400);
    const y0 = await page.evaluate(() => window.pageYOffset);
    await tab.click();
    const samples = [];
    for (let i = 0; i < 8; i++) { await page.waitForTimeout(60); samples.push(await page.evaluate(() => window.pageYOffset)); }
    const distinct = new Set(samples.map((s) => Math.round(s))).size;
    ok(distinct >= 3, 'industries tab click scrolls smoothly (several intermediate positions)', 'from ' + Math.round(y0) + ' samples=' + samples.map((s) => Math.round(s)).join(','));
  } else ok(false, 'industries tab present');
}
await d.close();
await browser.close();
if (problems.length) { console.log('QA BATCH1 ' + (reducedMode ? 'REDUCED ' : '') + 'FAILED: ' + problems.length); process.exit(1); }
console.log('QA BATCH1 ' + (reducedMode ? 'REDUCED ' : '') + 'PASSED');
