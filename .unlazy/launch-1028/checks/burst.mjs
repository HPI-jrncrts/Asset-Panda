// A real visitor's burst: open --n heavy pages back to back in one fresh browser (no pacing) and count every
// image/CSS/JS response that is rate-limited (429) or missing (4xx/5xx). Dev sits behind Cloudflare; a low
// rate-limit rule turns a normal browsing session into blank heroes and logos.   node burst.mjs [--n 5]
import { DEV, launch, guardPage, readPaths, arg } from './lib.mjs';
const paths = readPaths(arg('--paths', '.unlazy/launch-1028/perf-pages.json')).slice(0, +arg('--n', 5));
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
await guardPage(page);
const bad = []; let total = 0;
page.on('response', (r) => { const u = r.url(); if (!/\/wp-content\//.test(u)) return; total++; if (r.status() === 429 || r.status() >= 400) bad.push(r.status() + ' ' + u.replace(DEV, '').slice(0, 90)); });
for (const p of paths) {
  await page.goto(DEV + p, { waitUntil: 'networkidle', timeout: 90000 }).catch(() => {});
  await page.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); } });
  await page.waitForTimeout(800);
}
await browser.close();
const limited = bad.filter((b) => b.startsWith('429')).length;
for (const b of bad.slice(0, 15)) console.log('BAD ' + b);
console.log('pages=' + paths.length + ' assets=' + total + ' rateLimited=' + limited + ' otherErrors=' + (bad.length - limited));
if (bad.length) { console.log('BURST CHECK FAILED'); process.exit(1); }
console.log('BURST CHECK PASSED pages=' + paths.length + ' assets=' + total);
