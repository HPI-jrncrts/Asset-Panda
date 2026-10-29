// Joseph's later 2026-10-29 notes on dev (kit 1.3.133 + content edits):
//  footer (390): six splashes, none in the bottom quarter; Ursa steps: right copy centred on the heading block (1440);
//  hubs (Government, Education, Healthcare): the puddle is centred on the gap under the first row (1440);
//  energy-utilities: a puddle between the two rows and the scene art transparent at its edges;
//  tutorials (1440): every playlist card the same width, the last card's bottom level with the caption text (±8px);
//  emergency preparedness (1440): the photo circles at the reunification sizes (visible core 40–100px);
//  school reunification (1680): the backdrop keeps its 1440px width instead of stretching to the window;
//  barcode-labels: the closing card is the blue card; equipment-management / tool-tracking: card c1 sits behind the person.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const OUT = 'reports/launch-1028/shots/r1029c';
fs.mkdirSync(OUT, { recursive: true });
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const open = async (path, width = 1440, h = 900) => { const p = await browser.newPage({ viewport: { width, height: h } }); await guardPage(p); await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(1500); return p; };
const done = async (p) => { await p.close(); await sleep(3300); };
// footer splashes at 390
{
  const p = await open('/solutions/inventory-management/', 390, 844);
  const r = await p.evaluate(() => { const f = document.querySelector('.ap-site-footer'); const bg = getComputedStyle(f).backgroundImage; const spots = [...bg.matchAll(/radial-gradient\([^,]*?at\s+([\d.]+)%\s+([\d.]+)(%|px)/g)].filter((m) => m[3] === '%').map((m) => [+m[1], +m[2]]).filter(([x, y]) => y > 0); return { n: spots.length, maxY: Math.max(...spots.map((s) => s[1])) }; });
  ok(r.n === 6 && r.maxY <= 75, '390 footer: six splashes, none in the bottom quarter', JSON.stringify(r));
  await done(p);
}
// Ursa steps
{
  const p = await open('/ursa-ai/');
  const offs = await p.evaluate(async () => { const c = document.querySelector('.ap-ursa-steps'); c.scrollIntoView(); await new Promise((r) => setTimeout(r, 800)); return [...document.querySelectorAll('.ap-ursa-steps__card')].map((card) => { const l = card.querySelector('.ap-ursa-steps__lead'), b = card.querySelector('.ap-ursa-steps__top > .ap-ursa-steps__body'); if (!l || !b) return null; const lr = l.getBoundingClientRect(), br = b.getBoundingClientRect(); return Math.round((br.top + br.height / 2) - (lr.top + lr.height / 2)); }); });
  ok(offs.filter((o) => o !== null).length >= 3 && offs.every((o) => o === null || Math.abs(o) <= 4), 'Ursa: each card\'s right-hand copy centred on its heading block', JSON.stringify(offs));
  await done(p);
}
// hub puddles
for (const path of ['/solutions/government/', '/solutions/education/', '/solutions/healthcare/']) {
  const p = await open(path);
  const r = await p.evaluate(() => { const row = document.querySelector('.ap-hub-intel--gov > .apg-int-row:first-child, .ap-hub-intel--eduhub .apu-container > .apu-block:first-child'); if (!row) return null; const next = row.nextElementSibling; const a = getComputedStyle(row, '::after'); const rr = row.getBoundingClientRect(); const band = rr.bottom + (parseFloat(a.top) - rr.height) + parseFloat(a.height) / 2; const gapMid = next ? (rr.bottom + next.getBoundingClientRect().top) / 2 : rr.bottom + 40; return { content: a.content, bandMid: Math.round(band), gapMid: Math.round(gapMid), sectionBefore: getComputedStyle(row.closest('.ap-hub-intel'), '::before').backgroundImage.slice(0, 10) }; });
  ok(!!r && r.content !== 'none' && Math.abs(r.bandMid - r.gapMid) <= 12 && r.sectionBefore === 'none', path + ': the puddle is centred on the gap between the first two rows', JSON.stringify(r));
  await done(p);
}
// energy
{
  const p = await open('/solutions/energy-utilities/');
  const r = await p.evaluate(async () => { const s = [...document.querySelectorAll('.ap-media-split')]; if (s.length < 2) return null; s[1].scrollIntoView({ block: 'center' }); await new Promise((res) => setTimeout(res, 1200)); const a = getComputedStyle(s[0], '::after'); const im = s[1].querySelector('.apg-int-scene img'); await im.decode().catch(() => {}); const c = document.createElement('canvas'); c.width = im.naturalWidth; c.height = im.naturalHeight; const x = c.getContext('2d'); x.drawImage(im, 0, 0); const edge = Math.max(...[[2, 2], [2, c.height >> 1], [c.width - 3, c.height - 3], [c.width >> 1, c.height - 3]].map(([px, py]) => x.getImageData(px, py, 1, 1).data[3])); return { puddle: a.content !== 'none' && /radial/.test(a.backgroundImage), src: im.currentSrc.split('/').pop(), edgeAlpha: edge, blend: getComputedStyle(im).mixBlendMode }; });
  ok(!!r && r.puddle && r.edgeAlpha <= 2 && /clean/.test(r.src), 'energy-utilities: puddle between the rows, scene art with no edge box', JSON.stringify(r));
  await p.screenshot({ path: OUT + '/energy-after-1440.png' });
  await done(p);
}
// tutorials
{
  const p = await open('/tutorials/');
  const r = await p.evaluate(() => { const slots = [...document.querySelectorAll('.ap-page-videos__slot:not(.is-active)')]; const widths = [...new Set(slots.map((s) => Math.round(s.getBoundingClientRect().width)))]; const last = slots.sort((a, b) => b.getBoundingClientRect().bottom - a.getBoundingClientRect().bottom)[0]; const cap = document.querySelector('.ap-page-videos__caption, [data-ap-pv-caption]:not([hidden])'); const txt = cap ? [...cap.querySelectorAll('p, h2, h3')].filter((e) => e.getBoundingClientRect().height > 0).pop() : null; return { widths, lastBottom: Math.round(last.getBoundingClientRect().bottom), textBottom: txt ? Math.round(txt.getBoundingClientRect().bottom) : null }; });
  ok(r.widths.length === 1 && r.textBottom !== null && Math.abs(r.lastBottom - r.textBottom) <= 8, 'tutorials: playlist cards one width; last card level with the caption text', JSON.stringify(r));
  await p.screenshot({ path: OUT + '/tutorials-after-1440.png', clip: { x: 0, y: 500, width: 1440, height: 900 } }).catch(() => {});
  await done(p);
}
// EP circles
{
  const p = await open('/solutions/emergency-preparedness/');
  const r = await p.evaluate(async () => { const st = document.querySelector('.ap-intel-dashboard--readiness .epx-dash__stage'); st.scrollIntoView({ block: 'center' }); await new Promise((res) => setTimeout(res, 1200)); return [...st.querySelectorAll('.epx-chip')].map((c) => { const r = c.getBoundingClientRect(); const mr = parseFloat(getComputedStyle(c).getPropertyValue('--mr')) / 100 || .55; return Math.round(r.width * mr); }); });
  ok(r.length === 8 && r.every((v) => v >= 38 && v <= 104), 'emergency preparedness: photo circles at the reunification sizes', JSON.stringify(r));
  await p.locator('.ap-intel-dashboard').first().screenshot({ path: OUT + '/ep-dashboard-1440.png' });
  await done(p);
}
// SR backdrop at a wide window
{
  const p = await open('/solutions/school-reunification-software/', 1680);
  const r = await p.evaluate(() => { const o = document.querySelector('.ap-intel-dashboard--reunification .epx-dash__orbs'); return o ? Math.round(o.getBoundingClientRect().width) : null; });
  ok(r === 1440, '1680 school reunification: the backdrop keeps its 1440px width', 'width=' + r);
  await p.locator('.ap-intel-dashboard').first().screenshot({ path: OUT + '/sr-dashboard-1680.png' });
  await done(p);
}
// barcode closing card + cards behind the person
{
  const p = await open('/barcode-labels/');
  const r = await p.evaluate(() => { const s = document.querySelector('.ap-solution-cta'); const c = s && s.querySelector('.ap-solution-cta__card'); return { splash: s ? s.classList.contains('ap-solution-cta--splash') : null, card: c ? /linear-gradient/.test(getComputedStyle(c).backgroundImage) : false }; });
  ok(r.splash === false && r.card, '/barcode-labels/: the closing section is the blue card', JSON.stringify(r));
  await done(p);
}
for (const path of ['/solutions/equipment-management/', '/solutions/tool-tracking/']) {
  const p = await open(path);
  const r = await p.evaluate(() => { const hero = document.querySelector('.ap-solution-hero'); const photo = hero.querySelector('.ap-solution-hero__photo'); const c1 = hero.querySelector('.epx-hv--c1'); if (!photo || !c1) return null; const pz = +getComputedStyle(photo).zIndex || 0; const cz = getComputedStyle(c1).zIndex; return { below: c1.classList.contains('epx-hv--below'), photoZ: pz, cardZ: cz, behind: cz === 'auto' ? false : +cz < pz }; });
  ok(!!r && r.below && r.behind, path + ': card c1 sits behind the person', JSON.stringify(r));
  await p.locator('.ap-solution-hero').first().screenshot({ path: OUT + '/hero-' + path.split('/').filter(Boolean).pop() + '-1440.png' });
  await done(p);
}
await browser.close();
if (problems.length) { console.log('ROUND 1029C FAILED: ' + problems.length); process.exit(1); }
console.log('ROUND 1029C PASSED');
