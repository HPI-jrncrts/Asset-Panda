// Full-page screenshots of dev pages after a reader-style scroll (so reveals have run), at 1440 and 390.
//   node shot.mjs <path> [<path>...]   → reports/launch-1028/shots/<slug>-<w>.png  (+ a half-scale copy)
import fs from 'node:fs';
import { DEV, ROOT, launch, guardPage, sleep } from './lib.mjs';
const out = ROOT + '/reports/launch-1028/shots';
fs.mkdirSync(out, { recursive: true });
const browser = await launch();
for (const p of process.argv.slice(2).filter((a) => a.startsWith('/'))) {
  const slug = p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'home';
  for (const w of [1440, 390]) {
    const page = await browser.newPage({ viewport: { width: w, height: 900 } });
    if (process.argv.includes('--forms')) {
      // Form pages: let the HubSpot / Cal.com embeds draw (read-only GETs) so the card shows its fields; submissions stay blocked.
      await page.route(/google-analytics|googletagmanager|clarity\.ms|facebook|linkedin|doubleclick|stape|data\.assetpanda/i, (r) => r.abort());
      await page.route(/hsforms\.com\/submissions|forms\.hubspot\.com\/.*submit|\/wp-json\/.*(demo|quote|submit|booked)|\/api\/(book|booking)/i, (r) => r.abort());
    } else {
      await guardPage(page);
    }
    let limited = 0, failed = 0;
    page.on('response', (r) => { if (/\/wp-content\//.test(r.url())) { if (r.status() === 429) limited++; else if (r.status() >= 400) failed++; } });
    await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await page.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 150)); } window.scrollTo(0, 0); });
    await page.waitForTimeout(process.argv.includes('--forms') ? 4000 : 900);
    // At-rest capture: a full-page screenshot stretches the viewport to the page height, which leaves
    // scroll-linked entrances (animation-timeline: view()) at their start frame. Freeze every animation
    // and transition and drop the motion classes so the shot shows what a reader sees once settled.
    await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
    await page.evaluate(() => { document.documentElement.classList.remove('ap-motion', 'ap-sdriven'); for (const el of document.querySelectorAll('[data-ap-reveal]')) el.classList.add('ap-inview', 'ap-reveal-done'); });
    await page.waitForTimeout(300);
    const f = `${out}/${slug}-${w}.png`;
    await page.screenshot({ path: f, fullPage: true });
    console.log(f + (limited ? '  RATE-LIMITED assets: ' + limited : '') + (failed ? '  FAILED assets: ' + failed : ''));
    await page.close();
    await sleep(2500);
  }
}
await browser.close();
