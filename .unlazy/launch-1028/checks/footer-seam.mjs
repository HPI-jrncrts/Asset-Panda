// Footer seam (QA 2026-10-29: "the gradient is divided" on solution and inner pages; the homepage is the reference).
// Screenshots a 40px band straddling the footer's top edge at 1440 and 390 and compares the pixel rows 3px above and
// 3px below the edge at 9 x positions. A seam is a jump; passes when every column changes by ≤ 8 (sum of |ΔRGB|).
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const PAGES = ['/', '/solutions/inventory-management/', '/solutions/calibration-management/', '/products/asset-panda-for-police/', '/ursa-ai/', '/asset-intelligence-platform/', '/integrations/', '/pricing/'];
const browser = await launch();
const bad = [];
for (const width of [1440, 390]) {
  for (const path of PAGES) {
    const p = await browser.newPage({ viewport: { width, height: 900 } });
    await guardPage(p);
    await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await p.waitForTimeout(1200);
    await p.evaluate(() => { const f = document.querySelector('.ap-site-footer'); if (f) window.scrollBy(0, f.getBoundingClientRect().top - 400); });
    await p.waitForTimeout(600);
    const edge = await p.evaluate(() => { const f = document.querySelector('.ap-site-footer'); return f ? Math.round(f.getBoundingClientRect().top) : null; });
    if (edge === null) { console.log('SKIP ' + path + ' no footer'); await p.close(); continue; }
    const buf = await p.screenshot({ clip: { x: 0, y: Math.max(0, edge - 20), width, height: 40 } });
    const res = await p.evaluate(async ({ b64, width }) => {
      const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode();
      const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
      const px = (x, y) => ctx.getImageData(x, y, 1, 1).data;
      const out = [];
      for (let k = 1; k <= 9; k++) { const x = Math.round(width * k / 10); const a = px(x, 17), b = px(x, 23); out.push(Math.abs(a[0] - b[0]) + Math.abs(a[1] - b[1]) + Math.abs(a[2] - b[2])); }
      return out;
    }, { b64: buf.toString('base64'), width });
    const worst = Math.max(...res);
    const pass = worst <= 8;
    console.log((pass ? 'PASS ' : 'FAIL ') + width + ' ' + path + ' max jump ' + worst + ' ' + JSON.stringify(res));
    if (!pass) { bad.push(width + path); await p.screenshot({ path: 'reports/launch-1028/shots/seam-' + width + path.split('/').join('_') + '.png', clip: { x: 0, y: Math.max(0, edge - 120), width, height: 240 } }); }
    await p.close(); await sleep(3000);
  }
}
await browser.close();
console.log(bad.length ? 'FOOTER SEAM FAILED: ' + bad.join(' ') : 'FOOTER SEAM PASSED');
if (bad.length) process.exit(1);
