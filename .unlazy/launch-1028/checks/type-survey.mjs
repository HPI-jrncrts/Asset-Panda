// Body-text survey (Joseph 2026-10-29: "normal text content … some are thicker and some are thinner, some are bigger").
// For each listed page, every running-text paragraph/list item (>= 40 chars) inside <main> is grouped by its kit
// section and the distinct computed styles (font / size / weight / colour / line-height) are printed per section,
// with the count of paragraphs in each style, so the outliers stand out.  node type-survey.mjs
import { DEV, launch, guardPage } from './lib.mjs';
const PAGES = ['/', '/ursa-ai/', '/asset-intelligence-platform/', '/solutions/asset-management/', '/products/asset-panda-for-fire/', '/compare/', '/integrations/', '/solutions/healthcare/'];
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
const totals = {};
for (const p of PAGES) {
  await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(1200);
  const rows = await page.evaluate(() => {
    const out = [];
    const main = document.querySelector('main') || document.body;
    for (const el of main.querySelectorAll('p, li, dd')) {
      const text = (el.innerText || '').trim();
      if (text.length < 40) continue;
      if (el.closest('footer, nav, .ap-site-header, .ap-site-footer, .ap-faq, details')) continue;
      const cs = getComputedStyle(el);
      const sec = el.closest('section, [class^="ap-"]');
      const cls = sec ? (sec.className.split(' ').find((c) => /^ap-[a-z-]+$/.test(c) && !/__/.test(c)) || sec.className.split(' ')[0]) : '?';
      const fam = (cs.fontFamily.split(',')[0] || '').replace(/["']/g, '');
      out.push({ sec: cls, key: fam + ' ' + cs.fontSize + ' w' + cs.fontWeight + ' lh' + (Math.round(parseFloat(cs.lineHeight) / parseFloat(cs.fontSize) * 100) / 100) + ' ' + cs.color });
    }
    return out;
  });
  const bySec = {};
  for (const r of rows) { (bySec[r.sec] = bySec[r.sec] || {})[r.key] = ((bySec[r.sec] || {})[r.key] || 0) + 1; totals[r.key] = (totals[r.key] || 0) + 1; }
  console.log('=== ' + p);
  for (const [sec, styles] of Object.entries(bySec)) console.log('  ' + sec.padEnd(26) + Object.entries(styles).sort((a, b) => b[1] - a[1]).map(([k, n]) => n + '× ' + k).join('  |  '));
  await page.waitForTimeout(2500);
}
console.log('=== site totals (style: paragraphs)');
for (const [k, n] of Object.entries(totals).sort((a, b) => b[1] - a[1])) console.log('  ' + String(n).padStart(4) + '  ' + k);
await browser.close();
