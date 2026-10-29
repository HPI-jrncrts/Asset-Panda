// No faded or stuck content: scroll each listed dev page like a reader at 1440 and 390; every revealed block that
// is fully on screen must be fully opaque once settled, nothing may stay unrevealed at the end, and the page
// must log no JavaScript errors.   node fade.mjs --paths <json> [--limit N] [--control]
// --control injects a half-transparent revealed block on the first page; the check must then FAIL.
import { DEV, launch, guardPage, readPaths, arg, flag, sleep } from './lib.mjs';

const paths = readPaths(arg('--paths', '.unlazy/launch-1028/pages.json')).slice(0, +arg('--limit', 100000));
const browser = await launch();
const fails = [];
let i = 0;
for (const p of paths) {
  for (const w of [1440, 390]) {
    const page = await browser.newPage({ viewport: { width: w, height: w > 1000 ? 900 : 780 } });
    await guardPage(page);
    const errs = [];
    page.on('pageerror', (e) => errs.push(e.message.slice(0, 120)));
    try {
      await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
      await page.waitForTimeout(700);
      if (flag('--control') && i === 0) await page.evaluate(() => { const d = document.createElement('div'); d.setAttribute('data-ap-reveal', ''); d.className = 'ap-inview control-faded'; d.style.cssText = 'height:40px;opacity:.5'; document.querySelector('main').prepend(d); });
      const r = await page.evaluate(async () => {
        const bad = new Set();
        const H = () => document.documentElement.scrollHeight;
        const step = Math.round(innerHeight * 0.45);
        // Kit-wide reveals plus components' own scroll-linked entrances (…__rev, view() timelines).
        const onScreenFaded = () => [...document.querySelectorAll('[data-ap-sr], [data-ap-reveal], [class*="__rev"]')].filter((el) => { const b = el.getBoundingClientRect(); return b.height > 0 && b.top >= 0 && b.bottom <= innerHeight && +getComputedStyle(el).opacity < 0.98; });
        for (let y = 0; y <= H(); y += step) {
          window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 800));
          // A staggered reveal may still be in flight (up to ~1.4 s for a six-step row): give it one more
          // settle window and flag only what is STILL translucent — that is a stuck block, not a late one.
          if (onScreenFaded().length) {
            await new Promise((r) => setTimeout(r, 1600));
            for (const el of onScreenFaded()) bad.add((el.className || el.tagName).toString().slice(0, 48));
          }
        }
        window.scrollTo(0, H()); await new Promise((r) => setTimeout(r, 900));
        const never = [...document.querySelectorAll('[data-ap-reveal]:not(.ap-inview)')].filter((el) => el.getBoundingClientRect().height > 0).length;
        return { bad: [...bad], never };
      });
      if (r.bad.length || r.never || errs.length) fails.push(p + ' @' + w + (r.bad.length ? ' faded: ' + r.bad.slice(0, 4).join(' | ') : '') + (r.never ? ' never-revealed=' + r.never : '') + (errs.length ? ' js-errors: ' + errs.slice(0, 2).join(' | ') : ''));
    } catch (e) {
      fails.push(p + ' @' + w + ' load error ' + String(e.message).slice(0, 100));
    }
    await page.close();
    await sleep(1500);
  }
  i++;
}
await browser.close();
for (const f of fails) console.log('FAIL ' + f);
console.log('pages=' + paths.length + ' failures=' + fails.length);
if (fails.length) { console.log('FADE CHECK FAILED'); process.exit(1); }
console.log('FADE CHECK PASSED pages=' + paths.length);
