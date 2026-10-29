// Why does the AIP hero panda look stretched? Report the rendered box vs the natural size of what the browser chose.
import { DEV, launch, guardPage } from './lib.mjs';
const browser = await launch();
for (const [w, p] of [[1440, '/asset-intelligence-platform/'], [390, '/asset-intelligence-platform/'], [1440, '/ursa-ai/']]) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 }, deviceScaleFactor: w < 500 ? 2.625 : 1 });
  await guardPage(page);
  await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(2500);
  const r = await page.evaluate(async () => {
    const img = document.querySelector('.ap-orbit-hero__panda');
    if (!img) return 'no panda img';
    const cs = getComputedStyle(img);
    const b = img.getBoundingClientRect();
    const cands = (img.getAttribute('srcset') || '').split(',').map((s) => s.trim()).filter(Boolean);
    const sizes = [];
    for (const c of cands) { const u = c.split(' ')[0]; const im = new Image(); im.src = u; try { await im.decode(); sizes.push(u.replace(/.*\//, '') + ' ' + im.naturalWidth + 'x' + im.naturalHeight); } catch (e) { sizes.push(u.replace(/.*\//, '') + ' decode-failed'); } }
    return { src: (img.currentSrc || img.src).replace(/.*\//, ''), natural: img.naturalWidth + 'x' + img.naturalHeight, box: Math.round(b.width) + 'x' + Math.round(b.height), attrs: img.getAttribute('width') + 'x' + img.getAttribute('height'), objectFit: cs.objectFit, height: cs.height, transform: cs.transform, sizesAttr: img.getAttribute('sizes'), candidates: sizes };
  });
  console.log(w + ' ' + p + ' ' + JSON.stringify(r));
  await page.close();
}
await browser.close();
