// Gap from the header's bottom edge to the hero eyebrow on each hero type (QA 2026-10-29: ~7px on phones).
// node hero-gap.mjs [width]  → prints one line per page; with --expect N passes when every gap is within N±3.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const width = Number(process.argv[2] || 390);
const ei = process.argv.indexOf('--expect'); const expect = ei > -1 ? Number(process.argv[ei + 1]) : null;
const PAGES = ['/', '/products/asset-panda-for-police/', '/products/asset-panda-for-k12/', '/solutions/inventory-management/', '/solutions/help-desk-ticketing/', '/solutions/education/', '/ursa-ai/', '/asset-intelligence-platform/', '/tutorials/', '/resource-center/blog/', '/roi-calculator/', '/pricing/'];
const browser = await launch();
const p = await browser.newPage({ viewport: { width, height: 900 } });
await guardPage(p);
const bad = [];
for (const path of PAGES) {
  await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1200);
  const r = await p.evaluate(() => {
    const head = [...document.querySelectorAll('.ap-site-header__mob, .ap-site-header__desk')].find((h) => h.getBoundingClientRect().height > 0 && getComputedStyle(h).display !== 'none');
    const main = document.querySelector('main') || document.body;
    const hero = main.querySelector('section[class*="hero"], [class*="-hero"]');
    const eb = hero && [...hero.querySelectorAll('[class*="eyebrow"]')].find((e) => e.getBoundingClientRect().height > 0);
    const hb = head ? head.getBoundingClientRect().bottom : 0;
    return { hero: hero ? hero.className.split(' ')[0] : null, eyebrow: eb ? eb.className.split(' ')[0] : null, gap: eb ? Math.round(eb.getBoundingClientRect().top - hb) : null, heroTop: hero ? Math.round(hero.getBoundingClientRect().top - hb) : null };
  });
  console.log(path.padEnd(42) + JSON.stringify(r));
  if (expect !== null && r.eyebrow && Math.abs(r.gap - expect) > 3) bad.push(path);
  await sleep(3200);
}
await browser.close();
if (expect !== null) { console.log(bad.length ? 'HERO GAP FAILED: ' + bad.join(' ') : 'HERO GAP PASSED'); if (bad.length) process.exit(1); }
