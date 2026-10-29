// Blurry/pixelated image survey (QA 2026-10-29, Joseph): on a 2x screen, every <img> whose natural size is smaller
// than its rendered size x2 is upscaled and looks soft. Lists them per page with the shortfall, grouped by cause.
// Usage: node blur-survey.mjs [out.json]   (paced: one page at a time, ≥3 s apart)
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const PAGES = ['/', '/asset-intelligence-platform/', '/ursa-ai/', '/products/asset-panda-for-local-government/', '/products/asset-panda-for-police/', '/solutions/inventory-management/', '/solutions/help-desk-ticketing/', '/solutions/education/', '/solutions/healthcare/', '/barcode-labels/', '/pricing/', '/roi-calculator/', '/tutorials/', '/resources/'];
const out = process.argv[2] || 'C:/Sample Code/reports/launch-1028/blur-survey.json';
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
await guardPage(page);
const rows = [];
for (const p of PAGES) {
  try {
    const r = await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    if (!r || r.status() >= 400) { console.log('SKIP ' + p + ' status ' + (r && r.status())); await sleep(4000); continue; }
    // Walk the page so lazy images load.
    const h = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < h; y += 700) { await page.evaluate((y) => window.scrollTo(0, y), y); await page.waitForTimeout(120); }
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(1500);
    const imgs = await page.evaluate(() => [...document.images].filter((i) => i.naturalWidth > 0 && i.clientWidth > 8 && i.clientHeight > 8 && getComputedStyle(i).display !== 'none').map((i) => {
      const dpr = window.devicePixelRatio || 1;
      const need = Math.round(i.clientWidth * dpr);
      const have = i.naturalWidth;
      const fit = getComputedStyle(i).objectFit;
      return { src: (i.currentSrc || i.src).replace(/^https?:\/\/[^/]+/, ''), cls: i.className.slice(0, 60), rendered: i.clientWidth + 'x' + i.clientHeight, natural: i.naturalWidth + 'x' + i.naturalHeight, need, have, short: Math.round((1 - have / need) * 100), fit, lazy: i.loading };
    }).filter((x) => x.short >= 20));
    for (const i of imgs) rows.push({ page: p, ...i });
    console.log(p + ' upscaled=' + imgs.length);
  } catch (e) { console.log('ERR ' + p + ' ' + e.message.slice(0, 80)); }
  await sleep(3500);
}
await browser.close();
fs.writeFileSync(out, JSON.stringify(rows, null, 1));
const by = {};
for (const r of rows) { const k = /\.svg\.webp/.test(r.src) ? 'svg-raster-1x' : /\.(png|jpe?g|webp)$/i.test(r.src) ? 'small-raster' : 'other'; by[k] = (by[k] || 0) + 1; }
console.log('TOTAL upscaled images: ' + rows.length + ' ' + JSON.stringify(by));
const uniq = [...new Set(rows.map((r) => r.src))];
console.log('unique sources: ' + uniq.length);
for (const r of rows.sort((a, b) => b.short - a.short).slice(0, 40)) console.log(r.short + '% short | ' + r.page + ' | ' + r.src + ' | rendered ' + r.rendered + ' natural ' + r.natural + ' | ' + r.cls);
