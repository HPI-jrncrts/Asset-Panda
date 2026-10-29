// Before/after captures for Joseph's product-page batch 2:  node qa-batch2-shots.mjs <label>
// → reports/launch-1028/shots/qa2-<label>-<page>-<item>.png : each product hero at 390 (full hero + the strip under
// it), at 1024 (hero), and the Police hero at 1440 (connector lines); the "Municipal…" sticky-list cards at 1440.
import { DEV, launch, guardPage } from './lib.mjs';
const label = process.argv[2] || 'now';
const OUT = 'reports/launch-1028/shots/';
const PAGES = ['local-government', 'public-works', 'fire', 'police', 'k12'];
const browser = await launch();
async function clipShot(page, locator, file, extra = 0) {
  const el = page.locator(locator).first();
  if (!(await el.count())) { console.log('missing ' + file); return; }
  await el.scrollIntoViewIfNeeded(); await page.waitForTimeout(1500);
  const b = await el.boundingBox(); if (!b) return;
  const vp = page.viewportSize();
  await page.screenshot({ path: OUT + file, clip: { x: 0, y: Math.max(0, b.y), width: vp.width, height: Math.min(vp.height, b.height + extra) } });
  console.log('shot ' + file);
}
for (const slug of PAGES) {
  const url = DEV + '/products/asset-panda-for-' + slug + '/?nc=' + Date.now();
  // Phone: the whole hero (scrolling capture) + the chip strip region.
  let page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await guardPage(page);
  await page.goto(url, { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(1800);
  const hero = page.locator('.ap-vhero').first();
  if (await hero.count()) {
    const b = await hero.boundingBox();
    await page.screenshot({ path: OUT + 'qa2-' + label + '-' + slug + '-390.png', fullPage: true, clip: { x: 0, y: 0, width: 390, height: Math.min(2200, (b ? b.y + b.height : 1200) + 160) } });
    const m = await page.evaluate(() => { const s = document.querySelector('.ap-vhero__feats'); const c = document.querySelector('.ap-vhero__certs'); const art = document.querySelector('.ap-vhero__dash, .ap-vhero__person'); const vis = (el) => el ? (getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 0) : false; return { feats: vis(s), featsTop: s ? Math.round(s.getBoundingClientRect().top + window.pageYOffset) : null, featsBottom: s ? Math.round(s.getBoundingClientRect().bottom + window.pageYOffset) : null, certs: vis(c), certsBottom: c ? Math.round(c.getBoundingClientRect().bottom + window.pageYOffset) : null, artTop: art ? Math.round(art.getBoundingClientRect().top + window.pageYOffset) : null }; });
    console.log(slug + ' 390 ' + JSON.stringify(m));
  }
  await page.close();
  // Tablet 1024: hero.
  page = await browser.newPage({ viewport: { width: 1024, height: 1000 } });
  await guardPage(page);
  await page.goto(url + 't', { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(1800);
  await clipShot(page, '.ap-vhero', 'qa2-' + label + '-' + slug + '-1024.png', 80);
  await page.close();
  await new Promise((r) => setTimeout(r, 2000));
}
// Desktop 1440: Police hero lines; Local Government sticky-list cards at rest.
let page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
await page.goto(DEV + '/products/asset-panda-for-police/?nc=' + Date.now() + 'p', { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(2500);
await clipShot(page, '.ap-vhero', 'qa2-' + label + '-police-1440.png');
const lines = await page.evaluate(() => { const svg = document.querySelector('.ap-vhero__lines--d'); const cards = [...document.querySelectorAll('.ap-vhero__card')].map((c) => { const r = c.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top), Math.round(r.right), Math.round(r.bottom)]; }); const ls = svg ? [...svg.querySelectorAll('line')].map((l) => { const r = svg.getBoundingClientRect(); const sx = r.width / 1440, sy = r.height / 550; return [Math.round(+l.getAttribute('x1') * sx + r.left), Math.round(+l.getAttribute('y1') * sy + r.top), Math.round(+l.getAttribute('x2') * sx + r.left), Math.round(+l.getAttribute('y2') * sy + r.top)]; }) : []; const badge = document.querySelector('.ap-vhero__badge'); const br = badge ? badge.getBoundingClientRect() : null; return { cards, lines: ls, badge: br ? [Math.round(br.left), Math.round(br.top), Math.round(br.width)] : null }; });
console.log('police 1440 geometry ' + JSON.stringify(lines));
await page.goto(DEV + '/products/asset-panda-for-local-government/?nc=' + Date.now() + 'l', { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1500);
await clipShot(page, '.ap-sticky-list', 'qa2-' + label + '-localgov-stickylist-1440.png');
await page.close();
await browser.close();
console.log('QA2 SHOTS DONE ' + label);
