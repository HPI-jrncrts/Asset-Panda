import { DEV, launch, guardPage } from './lib.mjs';
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 390, height: 800 } });
await guardPage(page);
await page.goto(DEV + '/products/asset-panda-for-police/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.evaluate(() => document.querySelector('.ap-trust-strip').scrollIntoView());
await page.waitForTimeout(1500);
const read = () => page.evaluate(() => {
  const s = document.querySelector('.ap-trust-strip'); const kids = [...s.querySelectorAll('img')];
  const track = kids[0] && kids[0].parentElement; const cs = track ? getComputedStyle(track) : null;
  return { cls: s.className, h: Math.round(s.getBoundingClientRect().height), imgs: kids.length, visible: kids.filter((i) => { const b = i.getBoundingClientRect(); return b.left >= 0 && b.right <= innerWidth && b.width > 0; }).length, loaded: kids.filter((i) => i.complete && i.naturalWidth > 0).length, track: cs && { cls: track.className, wrap: cs.flexWrap, anim: cs.animationName, overflow: getComputedStyle(track.parentElement).overflow, w: Math.round(track.getBoundingClientRect().width) }, firstSrc: kids[0] && (kids[0].currentSrc || kids[0].src).slice(-60), widths: kids.map((i) => Math.round(i.getBoundingClientRect().width)).join(',') };
});
console.log('running:', JSON.stringify(await read()));
await page.addStyleTag({ content: '*{animation:none!important;transition:none!important}' });
await page.waitForTimeout(400);
console.log('frozen: ', JSON.stringify(await read()));
await browser.close();
