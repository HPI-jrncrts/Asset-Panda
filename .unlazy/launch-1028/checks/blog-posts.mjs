// Blog/compare posts after content 1.3.9 (QA 2026-10-29: posts follow the Figma post designs; Quick Answer / Key Summary
// / Key Takeaways take the Key Takeaways look, "do not change content"). For nine sampled posts at 1440 and 390: the
// page serves ap-content.css 1.3.9, has no horizontal overflow and no broken image, the key-content blocks carry the
// Key Takeaways style (teal, bold label) where a post has one. Saves a first-screen capture of each for review.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const POSTS = [
  '/resource-center/blog/what-are-ghost-assets-vs-zombie-assets/',
  '/resource-center/blog/furniture-inventory-asset-management-makes-a-difference-to-your-bottom-line/',
  '/resource-center/blog/fire-department-inspection-records-compliance/',
  '/resource-center/compare/best-asset-management-software/',
  '/resource-center/compare/asset-panda-vs-ezo/',
  '/resource-center/compare/asset-panda-vs-maintainx-asset-tracking-management-comparison/',
  '/resource-center/compare/best-ai-visual-inspection-software/',
  '/resource-center/blog/asset-panda-vs-asset-tiger/',
  '/resource-center/blog/rfid-asset-tracking-systems/',
];
const SHOTS = 'reports/launch-1028/shots/blog-1039';
fs.mkdirSync(SHOTS, { recursive: true });
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
for (const width of [1440, 390]) {
  for (const path of POSTS) {
    const p = await browser.newPage({ viewport: { width, height: 900 } });
    await guardPage(p);
    await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await p.waitForTimeout(1200);
    const r = await p.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise((res) => setTimeout(res, 60)); }
      window.scrollTo(0, 0); await new Promise((res) => setTimeout(res, 400));
      const css = [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.href).find((h) => /ap-content\.css/.test(h)) || '';
      const broken = [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 0).map((i) => i.currentSrc || i.src).slice(0, 3);
      const titles = [...document.querySelectorAll('.ap26-key__title')].map((t) => { const cs = getComputedStyle(t); return { text: t.textContent.trim().slice(0, 30), color: cs.color, size: cs.fontSize, weight: cs.fontWeight }; });
      return { ver: (css.match(/ver=([\d.]+)/) || [])[1] || null, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth, broken, titles };
    });
    const slug = path.split('/').filter(Boolean).pop();
    const vn = String(r.ver || '0.0.0').split('.').map(Number);
    ok(vn[0] === 1 && (vn[1] > 3 || (vn[1] === 3 && vn[2] >= 9)), width + ' ' + slug + ': content 1.3.9 or later served', 'ver=' + r.ver);
    ok(!r.overflow, width + ' ' + slug + ': no horizontal overflow');
    ok(r.broken.length === 0, width + ' ' + slug + ': no broken image', r.broken.join(' '));
    const off = r.titles.filter((t) => t.color !== 'rgb(0, 169, 145)' || parseFloat(t.weight) < 700);
    ok(off.length === 0, width + ' ' + slug + ': key-content labels in the Key Takeaways style', r.titles.length + ' label(s)' + (off.length ? ' off: ' + JSON.stringify(off) : ''));
    await p.screenshot({ path: SHOTS + '/' + width + '-' + slug + '.png', fullPage: false });
    await p.close(); await sleep(3200);
  }
}
await browser.close();
if (problems.length) { console.log('BLOG POSTS FAILED: ' + problems.length); process.exit(1); }
console.log('BLOG POSTS PASSED');
