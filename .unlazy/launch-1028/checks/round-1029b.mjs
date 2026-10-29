// Joseph's late 2026-10-29 round (kit 1.3.132): /ursa-ai/ "What is Ursa" art layers are transparent (ring + blur
// served as alpha WebP, no white box), each Ask/Build/Analyze/Act card's right-hand copy is vertically centred on its
// heading block at 1440; /integrations/ Categories stay pinned under the header while the cards scroll at 1440; the
// why-cards splash is centred (sides of the band white) on /barcode-labels/. Saves captures for review, incl. /compare/.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const OUT = 'reports/launch-1028/shots/r1029b';
fs.mkdirSync(OUT, { recursive: true });
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const open = async (path, width = 1440, h = 900) => { const p = await browser.newPage({ viewport: { width, height: h } }); await guardPage(p); await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 }); await p.waitForTimeout(1500); return p; };
// Ursa
{
  const p = await open('/ursa-ai/');
  const art = p.locator('.ap-assist-problem__art').first();
  await art.scrollIntoViewIfNeeded(); await p.waitForTimeout(1200);
  const r = await p.evaluate(async () => {
    const out = {};
    for (const sel of ['.ap-assist-problem__ring', '.ap-assist-problem__blur', '.ap-assist-problem__orbring']) {
      const im = document.querySelector(sel); if (!im) { out[sel] = 'missing'; continue; }
      const c = document.createElement('canvas'); c.width = 8; c.height = 8; const x = c.getContext('2d');
      const img = new Image(); img.crossOrigin = 'anonymous'; img.src = im.currentSrc; try { await img.decode(); x.drawImage(img, 0, 0, 8, 8); out[sel] = { src: im.currentSrc.split('/').pop(), cornerAlpha: x.getImageData(0, 0, 1, 1).data[3] }; } catch (e) { out[sel] = { src: im.currentSrc.split('/').pop(), err: String(e).slice(0, 60) }; }
    }
    const cards = [...document.querySelectorAll('.ap-ursa-steps__card')].map((c) => { const l = c.querySelector('.ap-ursa-steps__lead'), b = c.querySelector('.ap-ursa-steps__top > .ap-ursa-steps__body'); if (!l || !b) return null; const lr = l.getBoundingClientRect(), br = b.getBoundingClientRect(); return Math.round((br.top + br.height / 2) - (lr.top + lr.height / 2)); });
    out.offsets = cards;
    return out;
  });
  const layers = ['.ap-assist-problem__ring', '.ap-assist-problem__blur', '.ap-assist-problem__orbring'].map((s) => r[s]);
  ok(layers.every((l) => l && typeof l === 'object' && l.cornerAlpha === 0), 'Ursa: ring, blur and orb ring are transparent at the corners', JSON.stringify(layers));
  const offs = r.offsets.filter((o) => o !== null);
  ok(offs.length >= 3 && offs.every((o) => Math.abs(o) <= 4), 'Ursa: each card\'s right-hand copy is centred on its heading block', JSON.stringify(r.offsets));
  await art.screenshot({ path: OUT + '/ursa-art-1440.png' });
  const card2 = p.locator('.ap-ursa-steps__card').nth(1); await card2.scrollIntoViewIfNeeded(); await p.waitForTimeout(600); await card2.screenshot({ path: OUT + '/ursa-card2-1440.png' });
  await p.close(); await sleep(3300);
}
// Integrations sticky
{
  const p = await open('/integrations/');
  const r = await p.evaluate(async () => {
    const f = document.querySelector('.ap-integration-directory__filter'); const g = document.querySelector('.ap-integration-directory__grid');
    if (!f || !g) return null;
    const gt = g.getBoundingClientRect().top + window.pageYOffset;
    window.scrollTo(0, gt + 500); await new Promise((res) => setTimeout(res, 500));
    const t1 = Math.round(f.getBoundingClientRect().top);
    // second point: still inside the directory (the list lets go at the directory's end, by design)
    const body = f.parentElement.getBoundingClientRect(); const room = Math.round(body.height - f.getBoundingClientRect().height - 200);
    window.scrollTo(0, gt + Math.max(260, Math.min(420, room))); await new Promise((res) => setTimeout(res, 500));
    const t2 = Math.round(f.getBoundingClientRect().top);
    return { pos: getComputedStyle(f).position, t1, t2 };
  });
  ok(!!r && r.pos === 'sticky' && Math.abs(r.t1 - 104) <= 2 && Math.abs(r.t2 - 104) <= 2, '/integrations/: Categories pinned 104px from the top while the cards scroll', JSON.stringify(r));
  await p.screenshot({ path: OUT + '/integrations-scrolled-1440.png' });
  await p.close(); await sleep(3300);
}
// why-cards splash centred
for (const width of [1440, 390]) {
  const p = await open('/barcode-labels/', width);
  const sec = p.locator('.ap-why-cards').first();
  if (await sec.count()) {
    await sec.scrollIntoViewIfNeeded(); await p.waitForTimeout(900);
    const buf = await sec.screenshot({ path: OUT + '/why-cards-' + width + '.png' });
    const r = await p.evaluate(async (b64) => { const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode(); const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const x = c.getContext('2d'); x.drawImage(img, 0, 0); const px = (fx, fy) => { const d = x.getImageData(Math.round(img.width * fx), Math.round(img.height * fy), 1, 1).data; return 765 - d[0] - d[1] - d[2]; }; return { leftEdge: px(0.02, 0.5), rightEdge: px(0.98, 0.5), centre: Math.max(px(0.5, 0.15), px(0.35, 0.12), px(0.65, 0.12)) }; }, buf.toString('base64'));
    ok(r.leftEdge <= 12 && r.rightEdge <= 12, width + ' why-cards: the band\'s sides stay white (splash centred)', JSON.stringify(r));
  } else ok(false, width + ' why-cards present on /barcode-labels/');
  await p.close(); await sleep(3300);
}
// Compare captures
for (const width of [1440, 390]) {
  const p = await open('/compare/', width);
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 80)); } window.scrollTo(0, 0); });
  await p.waitForTimeout(800);
  await p.screenshot({ path: OUT + '/compare-' + width + '.png', fullPage: true });
  await p.close(); await sleep(3300);
}
await browser.close();
if (problems.length) { console.log('ROUND 1029B FAILED: ' + problems.length); process.exit(1); }
console.log('ROUND 1029B PASSED');
