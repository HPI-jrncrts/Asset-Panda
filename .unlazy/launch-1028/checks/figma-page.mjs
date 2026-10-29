// A rebuilt page is live on dev as designed: it answers 200, its sections appear in the order of the approved
// block markup file (block assetpanda/x ↔ section class ap-x), no image is broken, no JavaScript error fires,
// nothing links to the live site, and the page has no sideways scroll at 390.
//   node figma-page.mjs --path /ursa-ai/ --markup <file with <!-- wp:assetpanda/... --> blocks>
import fs from 'node:fs';
import path from 'node:path';
import { DEV, ROOT, launch, guardPage, arg } from './lib.mjs';

const p = arg('--path');
const markup = fs.readFileSync(path.resolve(ROOT, arg('--markup')), 'utf8');
const expected = [...markup.matchAll(/<!-- wp:assetpanda\/([a-z0-9-]+)/g)].map((m) => 'ap-' + m[1]);
const browser = await launch();
const problems = [];
for (const w of [1440, 390]) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 } });
  await guardPage(page);
  const errs = [];
  page.on('pageerror', (e) => errs.push(e.message.slice(0, 100)));
  const res = await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  if (!res || res.status() !== 200) problems.push('HTTP ' + (res && res.status()) + ' @' + w);
  await page.waitForTimeout(1200);
  const r = await page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const roots = [];
    (function walk(el) { for (const c of el.children) { const cls = typeof c.className === 'string' ? c.className : ''; const m = cls.match(/(?:^|\s)(ap-[a-z0-9-]+)(?:\s|$)/); if (m && !/__/.test(m[1]) && !/^ap-(site-header|site-footer)$/.test(m[1])) roots.push(m[1]); else if (c.children.length) walk(c); } })(main);
    const broken = [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 0).map((i) => i.src.replace(location.origin, ''));
    // The live marketing site only: the app login (login.assetpanda.com) and mailto links are legitimate.
    const live = [...document.querySelectorAll('a[href], img[src]')].map((a) => a.href || a.src).filter((u) => /^https?:\/\/(www\.)?assetpanda\.com\//.test(u)).map((u) => u.slice(0, 80));
    return { roots, broken, live, overflow: document.documentElement.scrollWidth - innerWidth };
  });
  if (w === 1440) {
    const got = r.roots.filter((c) => expected.includes(c));
    if (got.join(',') !== expected.join(',')) problems.push('section order differs: expected ' + expected.join(' > ') + ' got ' + r.roots.join(' > '));
  }
  if (r.broken.length) problems.push('broken images @' + w + ': ' + r.broken.slice(0, 4).join(' '));
  if (r.live.length) problems.push('live-site refs @' + w + ': ' + r.live.slice(0, 3).join(' '));
  if (r.overflow > 0) problems.push('overflow ' + r.overflow + 'px @' + w);
  if (errs.length) problems.push('js errors @' + w + ': ' + errs.slice(0, 2).join(' | '));
  await page.close();
}
await browser.close();
for (const x of problems) console.log('PROBLEM ' + x);
if (problems.length) { console.log('FIGMA PAGE CHECK FAILED ' + p); process.exit(1); }
console.log('FIGMA PAGE CHECK PASSED ' + p + ' sections=' + expected.length);
