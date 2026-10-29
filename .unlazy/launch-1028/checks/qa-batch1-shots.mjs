// Before/after captures for Joseph's homepage batch 1:  node qa-batch1-shots.mjs <label>
// → reports/launch-1028/shots/qa1-<label>-<item>.png
import fs from 'node:fs';
import { DEV, launch, guardPage } from './lib.mjs';
const label = process.argv[2] || 'now';
const OUT = 'reports/launch-1028/shots/';
const browser = await launch();
const freeze = (page) => page.addStyleTag({ content: 'html.ap-motion [data-ap-reveal]{opacity:1!important;translate:none!important;transition:none!important}' });
async function shot(page, locator, name, pad = 12) {
  const el = page.locator(locator).first();
  if (!(await el.count())) { console.log('missing ' + name); return; }
  await el.scrollIntoViewIfNeeded(); await page.waitForTimeout(900);
  const b = await el.boundingBox();
  if (!b) { console.log('no box ' + name); return; }
  const vp = page.viewportSize();
  await page.screenshot({ path: OUT + 'qa1-' + label + '-' + name + '.png', clip: { x: Math.max(0, b.x - pad), y: Math.max(0, b.y - pad), width: Math.min(vp.width, b.width + pad * 2), height: Math.min(vp.height, b.height + pad * 2) } });
  console.log('shot ' + name);
}
// Desktop 1440: comparison card, platform-stack cards 2/4/6 hovered, a green outline + a gradient button hovered (mid + end), footer.
let page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await freeze(page);
await page.waitForTimeout(1200);
await shot(page, '.ap-compare--home .ap-compare-table', 'compare-1440');
for (const [n, id] of [[2, '#ap-card-ownership'], [4, '#ap-card-inspection'], [6, '#ap-card-reporting']]) {
  const card = page.locator(id).first();
  if (!(await card.count())) { console.log('missing card ' + n); continue; }
  await card.scrollIntoViewIfNeeded(); await page.waitForTimeout(600);
  await card.hover(); await page.waitForTimeout(120);
  await shot(page, id, 'card' + n + '-hover-mid-1440', 0);
  await page.waitForTimeout(700);
  await shot(page, id, 'card' + n + '-hover-end-1440', 0);
  await page.mouse.move(5, 5); await page.waitForTimeout(500);
}
const outline = page.locator('.ap-header-demo, .ap-btn-outline').first();
const grad = page.locator('.ap-header-free, .ap-btn-primary').first();
for (const [el, name] of [[outline, 'btn-outline'], [grad, 'btn-grad']]) {
  if (!(await el.count())) { console.log('missing ' + name); continue; }
  await el.scrollIntoViewIfNeeded(); await page.waitForTimeout(400);
  const b = await el.boundingBox();
  const clip = b ? { x: Math.max(0, b.x - 30), y: Math.max(0, b.y - 30), width: b.width + 60, height: b.height + 60 } : undefined;
  await page.screenshot({ path: OUT + 'qa1-' + label + '-' + name + '-rest.png', clip });
  await el.hover(); await page.waitForTimeout(140);
  await page.screenshot({ path: OUT + 'qa1-' + label + '-' + name + '-mid.png', clip });
  await page.waitForTimeout(800);
  await page.screenshot({ path: OUT + 'qa1-' + label + '-' + name + '-end.png', clip });
  await page.mouse.move(5, 5); await page.waitForTimeout(600);
  console.log('shot ' + name + ' rest/mid/end');
}
await shot(page, '.ap-site-footer', 'footer-1440');
await page.close();
// Tablet 1024: comparison, industries grid, a platform-stack card mid-scroll.
page = await browser.newPage({ viewport: { width: 1024, height: 1000 } });
await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now() + 'b', { waitUntil: 'load', timeout: 90000 });
await freeze(page);
await page.waitForTimeout(1200);
await shot(page, '.ap-compare--home .ap-compare-table', 'compare-1024');
await shot(page, '.ap-industries .ap-sector-grid', 'industries-1024');
const c2 = page.locator('#ap-card-ownership').first();
if (await c2.count()) { await c2.evaluate((el) => window.scrollTo({ top: el.getBoundingClientRect().top + window.pageYOffset - window.innerHeight * 0.35 })); await page.waitForTimeout(1200); await shot(page, '#ap-card-ownership', 'card2-scroll-1024', 0); }
await page.close();
// Phone 390: comparison card right edge, industries tabs.
page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now() + 'c', { waitUntil: 'load', timeout: 90000 });
await freeze(page);
await page.waitForTimeout(1200);
await shot(page, '.ap-compare--home .ap-compare-table', 'compare-390');
await shot(page, '.ap-site-footer', 'footer-390');
await page.close();
await browser.close();
console.log('QA1 SHOTS DONE ' + label);
