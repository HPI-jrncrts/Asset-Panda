// Preview a local kit stylesheet against a live dev page before deploying: loads the page, appends the local CSS
// (and optionally a local JS file) after the page's own, and screenshots the viewport or a selector at a width.
// Usage: node css-preview.mjs <url-path> <width> <out.png> [--css ui/components/x/style.css ...] [--js ui/src/motion.js]
//        [--sel .ap-vhero] [--full] [--scroll 1800] [--dpr 2]
import fs from 'node:fs';
import { DEV, launch, guardPage } from './lib.mjs';
const a = process.argv.slice(2);
const path = a[0], width = +a[1], out = a[2];
const css = [], js = [];
let sel = null, full = false, scroll = 0, dpr = 1;
for (let i = 3; i < a.length; i++) {
  if (a[i] === '--css') css.push(a[++i]);
  else if (a[i] === '--js') js.push(a[++i]);
  else if (a[i] === '--sel') sel = a[++i];
  else if (a[i] === '--full') full = true;
  else if (a[i] === '--scroll') scroll = +a[++i];
  else if (a[i] === '--dpr') dpr = +a[++i];
}
const browser = await launch();
const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: dpr });
await guardPage(page);
await page.goto(DEV + path + (path.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
for (const f of css) await page.addStyleTag({ content: fs.readFileSync(f, 'utf8') });
for (const f of js) await page.addScriptTag({ content: fs.readFileSync(f, 'utf8') });
await page.waitForTimeout(900);
if (scroll) { await page.evaluate((y) => window.scrollTo(0, y), scroll); await page.waitForTimeout(400); }
if (sel) await page.locator(sel).first().screenshot({ path: out });
else await page.screenshot({ path: out, fullPage: full });
const facts = await page.evaluate((sel) => { const el = sel ? document.querySelector(sel) : document.body; const r = el.getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top + scrollY) }; }, sel);
console.log('preview ' + path + ' @' + width + ' -> ' + out + ' ' + JSON.stringify(facts) + ' css=' + css.length + ' js=' + js.length);
const probeIdx = a.indexOf('--probe');
if (probeIdx > -1) {
  const sels = a[probeIdx + 1].split(',');
  const rows = await page.evaluate((sels) => sels.map((s) => { const el = document.querySelector(s); if (!el) return s + ': (none)'; const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return s + ': rect ' + [Math.round(r.left), Math.round(r.top + scrollY), Math.round(r.width), Math.round(r.height)].join(',') + ' | display ' + cs.display + ' pos ' + cs.position + ' | translate ' + cs.translate + ' | transform ' + cs.transform + ' | opacity ' + cs.opacity + ' | visibility ' + cs.visibility + ' | --u ' + cs.getPropertyValue('--u').trim() + ' | containerType ' + cs.containerType + ' | overflow ' + cs.overflow; }), sels);
  for (const r of rows) console.log('  ' + r);
}
await browser.close();
