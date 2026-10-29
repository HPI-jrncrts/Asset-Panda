// Hover each top-level menu and screenshot the open panel.   node menu-shots.mjs <url|file> <outPrefix> [width]
import path from 'node:path';
import { launch, guardPage } from './lib.mjs';
const [src, prefix, w = '1440'] = process.argv.slice(2);
const url = /^https?:/.test(src) ? src : 'file:///' + path.resolve(src).split(path.sep).join('/');
const browser = await launch();
const page = await browser.newPage({ viewport: { width: +w, height: 760 } });
await guardPage(page);
await page.goto(url, { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(800);
for (const name of ['Products', 'Solutions', 'Who We Help', 'Resources']) {
  const top = page.locator('.ap-nav-item', { hasText: name }).first();
  await top.hover();
  await page.waitForTimeout(900);
  const file = `${prefix}-${name.toLowerCase().replace(/\s+/g, '-')}-${w}.png`;
  await page.screenshot({ path: file });
  const info = await page.evaluate((n) => { const item = [...document.querySelectorAll('.ap-nav-item')].find((x) => x.textContent.includes(n)); const p = item && item.querySelector('.ap-mega-panel'); if (!p) return 'no panel'; const cs = getComputedStyle(p); const box = p.querySelector('.ap-icon-box'); return 'panel ' + Math.round(p.getBoundingClientRect().height) + 'px, bg ' + cs.backgroundColor + ', blur ' + (cs.backdropFilter || cs.webkitBackdropFilter) + (box ? ', icon-box border ' + getComputedStyle(box).borderTopWidth + ' ' + getComputedStyle(box).borderTopStyle + ' ' + getComputedStyle(box).borderTopColor : ''); }, name);
  console.log(name + ': ' + info + ' → ' + file);
  await page.mouse.move(10, 400);
  await page.waitForTimeout(400);
}
await browser.close();
