// Local harness for the CTA highlight + edge refraction: frames through hover-in/out for an outline and a gradient
// button, stitched per button (scratchpad liquid-test/<id>-cta-montage.png), plus tagging facts.
import { chromium } from 'playwright';
const OUT = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 760, height: 260 }, deviceScaleFactor: 2 });
await page.goto('file:///C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/index.html');
await page.waitForTimeout(400);
console.log('tagged', (await page.evaluate(() => [...document.querySelectorAll('[data-ap-cta]')].map((e) => e.id + ':' + e.getAttribute('data-ap-cta') + ':fx=' + !!e.querySelector('.ap-cta-fx') + ':vb=' + (e.querySelector('.ap-cta-fx') || { getAttribute: () => '' }).getAttribute('viewBox')))).join(' '));
for (const id of ['o1', 'g1', 'o2']) {
  await page.mouse.move(5, 5); await page.waitForTimeout(1300);
  const box = await page.locator('#' + id).boundingBox();
  const clip = { x: box.x - 6, y: box.y - 6, width: box.width + 12, height: box.height + 12 };
  const frames = [];
  const grab = async (l) => frames.push({ l, w: Math.round(clip.width), b: (await page.screenshot({ clip })).toString('base64') });
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  for (let k = 0; k < 7; k++) { await grab('in ' + k * 170); await page.waitForTimeout(140); }
  await page.waitForTimeout(300); await grab('in end');
  const st = await page.locator('#' + id).evaluate((e) => ({ bg: getComputedStyle(e).backgroundColor, color: getComputedStyle(e).color, off: e.querySelector('.ap-cta-fx__ray') ? getComputedStyle(e.querySelector('.ap-cta-fx__ray')).strokeDashoffset : null }));
  console.log(id, 'hover end', JSON.stringify(st));
  await page.mouse.move(5, 5);
  for (let k = 0; k < 5; k++) { await grab('out ' + k * 170); await page.waitForTimeout(140); }
  const m = await browser.newPage({ viewport: { width: 1600, height: 600 } });
  await m.setContent('<body style="margin:0;background:#ddd">' + frames.map((f) => '<div style="display:inline-block;margin:6px;text-align:center;font:12px Arial">' + f.l + '<br><img style="width:' + f.w + 'px" src="data:image/png;base64,' + f.b + '"></div>').join('') + '</body>');
  await m.screenshot({ path: OUT + id + '-cta-montage.png', fullPage: true }); await m.close();
}
await browser.close();
