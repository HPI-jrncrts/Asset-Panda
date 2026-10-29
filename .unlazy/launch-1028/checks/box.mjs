// Bounding boxes of a selector on a dev page (embeds allowed, submissions blocked).
//   node box.mjs <path> <selector> [width]  → one JSON line per match
import { DEV, launch } from './lib.mjs';
const [p, sel, w = '1440'] = process.argv.slice(2);
const browser = await launch();
const page = await browser.newPage({ viewport: { width: +w, height: 900 } });
await page.route(/google-analytics|googletagmanager|clarity\.ms|facebook|linkedin|doubleclick|stape|data\.assetpanda/i, (r) => r.abort());
await page.route(/hsforms\.com\/submissions|forms\.hubspot\.com\/.*submit|\/wp-json\/.*(demo|quote|submit|booked)|\/api\/(book|booking)/i, (r) => r.abort());
await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); } window.scrollTo(0, 0); });
await page.waitForTimeout(3500);
const boxes = await page.evaluate((s) => [...document.querySelectorAll(s)].map((el) => { const b = el.getBoundingClientRect(); return { cls: (el.className || el.tagName).toString().slice(0, 50), x: Math.round(b.left), y: Math.round(b.top + scrollY), w: Math.round(b.width), h: Math.round(b.height), forms: el.querySelectorAll('form, iframe').length }; }), sel);
await browser.close();
for (const b of boxes) console.log(JSON.stringify(b));
if (!boxes.length) console.log('no match');
