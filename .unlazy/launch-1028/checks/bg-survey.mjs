// Section backgrounds (QA 2026-10-29: sections without a gradient must be pure white). For every kit section on
// the listed pages: the root's background colour and whether it (or a full-size ::before/::after) paints a
// gradient/image. Lists the off-white / grey ones. With --gate exits 1 if any remain.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const PAGES = ['/', '/products/asset-panda-for-k12/', '/solutions/asset-management/', '/solutions/healthcare/', '/solutions/energy-utilities/', '/products/asset-panda-for-police/', '/products/asset-panda-for-higher-education/', '/solutions/inventory-management/', '/solutions/calibration-management/', '/solutions/help-desk-ticketing/', '/solutions/education/', '/solutions/healthcare/', '/ursa-ai/', '/asset-intelligence-platform/', '/barcode-labels/', '/pricing/', '/integrations/', '/tutorials/', '/resource-center/blog/', '/roi-calculator/'];
const gate = process.argv.includes('--gate');
const browser = await launch();
const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(p);
const found = new Map();
for (const path of PAGES) {
  await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1000);
  const rows = await p.evaluate(() => {
    const out = [];
    const roots = [...document.querySelectorAll('main [class*="wp-block-assetpanda-"] > [class^="ap-"], main [class*="wp-block-assetpanda-"] > section')];
    const cols = (str) => [...String(str).matchAll(/rgba?\((\d+), (\d+), (\d+)(?:, ([\d.]+))?\)/g)].map((m) => ({ r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] }));
    const isGrey = (c) => c.a > 0.05 && Math.min(c.r, c.g, c.b) >= 225 && !(c.r === 255 && c.g === 255 && c.b === 255) && Math.max(c.r, c.g, c.b) - Math.min(c.r, c.g, c.b) < 12;
    const isColour = (c) => c.a > 0.05 && Math.max(c.r, c.g, c.b) - Math.min(c.r, c.g, c.b) >= 18;
    for (const r of roots) {
      const name = (r.className.toString().match(/ap-[a-z0-9-]+/) || [''])[0];
      if (!name || /site-header|site-footer/.test(name)) continue;
      const layers = [getComputedStyle(r)];
      for (const x of ['::before', '::after']) { const ps = getComputedStyle(r, x); if (ps.content !== 'none' && ps.position === 'absolute') layers.push(ps); }
      for (const k of r.children) { const kr = k.getBoundingClientRect(), rr = r.getBoundingClientRect(); if (kr.width >= rr.width * 0.95 && kr.height >= rr.height * 0.6) layers.push(getComputedStyle(k)); }
      const all = layers.flatMap((cs) => cols(cs.backgroundColor + ' ' + cs.backgroundImage));
      const colour = all.some(isColour) || layers.some((cs) => cs.backgroundImage.includes('url(')) || !!r.querySelector('img[class*="bg"]');
      const grey = all.some(isGrey);
      out.push({ name, bg: all.filter(isGrey).map((c) => c.r + ',' + c.g + ',' + c.b).slice(0, 2).join(' '), grad: colour, offWhite: grey });
    }
    return out;
  });
  for (const r of rows) if (r.offWhite && !r.grad) { const k = r.name + ' ' + r.bg; if (!found.has(k)) found.set(k, path); }
  await sleep(3000);
}
await browser.close();
for (const [k, v] of found) console.log('OFF-WHITE ' + k + '  (first on ' + v + ')');
console.log('off-white sections without a gradient: ' + found.size);
if (gate) { console.log(found.size ? 'BG SURVEY FAILED' : 'BG SURVEY PASSED'); if (found.size) process.exit(1); }
