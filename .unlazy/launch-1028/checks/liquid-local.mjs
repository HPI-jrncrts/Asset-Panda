// Local harness for the liquid CTA hover: frames every 120ms through hover-in and hover-out for an outline and a
// gradient button, stitched into one montage per button, plus a rest shot and corner pixel samples at the end
// of the sweep (the pixel 2px inside each corner, on the diagonal, must be the fill colour).
import { chromium } from 'playwright';
const OUT = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 760, height: 260 }, deviceScaleFactor: 2 });
await page.goto('file:///C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/index.html');
await page.waitForTimeout(400);
console.log('classified', (await page.evaluate(() => [...document.querySelectorAll('[data-ap-liquid]')].map((e) => e.id + ':' + e.getAttribute('data-ap-liquid') + ':lbl=' + !!e.querySelector('.ap-liquid-lbl') + ':bw=' + e.style.getPropertyValue('--ap-liquid-bw') + ':px=' + e.style.getPropertyValue('--ap-liquid-px')))).join(' '));
await page.screenshot({ path: OUT + 'rest.png' });
const montage = async (id, frames) => {
  const m = await browser.newPage({ viewport: { width: 1600, height: 900 } });
  await m.setContent('<body style="margin:0;background:#ddd">' + frames.map((f, i) => '<div style="display:inline-block;margin:6px;text-align:center;font:12px Arial">' + f.label + '<br><img style="width:' + f.w + 'px" src="data:image/png;base64,' + f.b64 + '"></div>').join('') + '</body>');
  await m.screenshot({ path: OUT + id + '-montage.png', fullPage: true });
  await m.close();
};
for (const id of ['o1', 'g1', 'o2']) {
  await page.mouse.move(5, 5);
  await page.waitForTimeout(1200);
  const box = await page.locator('#' + id).boundingBox();
  const clip = { x: box.x - 6, y: box.y - 6, width: box.width + 12, height: box.height + 12 };
  const frames = [];
  const grab = async (label) => frames.push({ label, w: Math.round(clip.width), b64: (await page.screenshot({ clip })).toString('base64') });
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  const t0 = Date.now();
  for (let k = 0; k < 8; k++) { await grab('in ' + (Date.now() - t0) + 'ms'); await page.waitForTimeout(90); }
  await page.waitForTimeout(400);
  await grab('in end');
  // Corner samples 2px inside the pill edge along each corner diagonal.
  const buf = await page.screenshot({ type: 'png' });
  const px = await page.evaluate(async ({ b64, box }) => {
    const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode();
    const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
    const s = (x, y) => { const d = ctx.getImageData(Math.round(x * 2), Math.round(y * 2), 1, 1).data; return [d[0], d[1], d[2]].join(','); };
    const r = parseFloat(getComputedStyle(document.querySelector('#' + box.id)).borderTopLeftRadius) || 0;
    const rr = Math.min(r, box.height / 2); const d = rr - (rr / Math.SQRT2) + 2; // 2px inside the curve on the 45° diagonal
    return { tl: s(box.x + d, box.y + d), tr: s(box.x + box.width - d, box.y + d), bl: s(box.x + d, box.y + box.height - d), br: s(box.x + box.width - d, box.y + box.height - d), edgeTop: s(box.x + box.width / 2, box.y + 2), edgeLeft: s(box.x + 2, box.y + box.height / 2) };
  }, { b64: buf.toString('base64'), box: { ...box, id } });
  console.log(id, 'end corners', JSON.stringify(px));
  await page.mouse.move(5, 5);
  const t1 = Date.now();
  for (let k = 0; k < 8; k++) { await grab('out ' + (Date.now() - t1) + 'ms'); await page.waitForTimeout(90); }
  await page.waitForTimeout(400);
  await grab('out end');
  await montage(id, frames);
}
await browser.close();
