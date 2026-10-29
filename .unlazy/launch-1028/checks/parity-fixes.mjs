// Tracking/embed parity fixes applied on dev 2026-10-29 (contract C39; report reports/launch-1028/tracking-parity/
// REPORT.md): the 7 pages whose live versions carry their own JSON-LD (SoftwareApplication, Service, Organization,
// CollectionPage, ItemList…) now print it from _ap_jsonld as valid JSON, with no FAQPage among those blocks (the kit
// prints FAQPage itself, so it must not appear twice); the pricing thank-you page (2411) no longer shows a Cal.com
// calendar and carries live's current text (live removed the calendar 2026-10-23); and staging still prints no GTM, Cookiebot or HubSpot preconnect (the site
// plugin 1.2.1 keeps those to www). Saves a capture of the pricing thank-you page.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const SHOTS = 'reports/launch-1028/shots/parity';
fs.mkdirSync(SHOTS, { recursive: true });
const PAGES = {
  '/': ['SoftwareApplication', 'Service'],
  '/products/asset-panda-for-local-government/': ['Organization', 'SoftwareApplication'],
  '/products/asset-panda-for-police/': ['Organization', 'SoftwareApplication'],
  '/solutions/fixed-asset-tracking/': ['SoftwareApplication', 'WebPage'],
  '/solutions/government/': ['CollectionPage', 'ItemList'],
  '/asset-intelligence-platform/': ['SoftwareApplication', 'WebPage'],
  '/thank-you-fire/': ['Organization', 'SoftwareApplication'],
};
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(p);
for (const [path, want] of Object.entries(PAGES)) {
  const res = await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  const r = await p.evaluate(() => {
    const own = [...document.querySelectorAll('script.ap-content-jsonld')];
    const types = new Set(); let bad = 0;
    const walk = (o) => { if (Array.isArray(o)) o.forEach(walk); else if (o && typeof o === 'object') { [].concat(o['@type'] || []).forEach((t) => types.add(t)); Object.values(o).forEach(walk); } };
    for (const s of own) { try { walk(JSON.parse(s.textContent)); } catch (e) { bad++; } }
    const faqAll = [...document.querySelectorAll('script[type="application/ld+json"]')].filter((s) => /"FAQPage"/.test(s.textContent)).length;
    return { blocks: own.length, bad, types: [...types], faqInOwn: types.has('FAQPage'), faqAll, gtm: !!document.querySelector('script[src*="googletagmanager.com/gtm.js"]'), hsHint: !!document.querySelector('link[rel=preconnect][href*="hsforms"]') };
  });
  const missing = want.filter((t) => !r.types.includes(t));
  ok(res.status() === 200 && r.blocks > 0 && r.bad === 0 && !missing.length, path + ': live JSON-LD printed and valid', JSON.stringify({ blocks: r.blocks, types: r.types.slice(0, 8), missing }));
  ok(!r.faqInOwn && r.faqAll <= 1, path + ': FAQPage not duplicated', 'FAQPage blocks on page=' + r.faqAll);
  ok(!r.gtm && !r.hsHint, path + ': staging still prints no GTM or HubSpot preconnect');
  await sleep(3200);
}
await p.goto(DEV + '/thank-you-pricing-multiform/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await p.waitForTimeout(2500);
const ty = await p.evaluate(() => ({ cal: document.querySelectorAll('[data-ap-cal], iframe[src*="cal.com"]').length, hero: !!document.querySelector('.ap-thanks-hero'), overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth, liveCopy: /personalized quote/.test(document.querySelector('.ap-thanks-hero')?.textContent || ''), staleCopy: /calendar below/i.test(document.body.textContent) }));
ok(ty.hero && ty.cal === 0 && !ty.overflow && ty.liveCopy && !ty.staleCopy, '/thank-you-pricing-multiform/: thank-you hero shows the live text with no calendar, as live', JSON.stringify(ty));
await p.screenshot({ path: SHOTS + '/pricing-thanks-1440.png' });
await browser.close();
if (problems.length) { console.log('PARITY FIXES FAILED: ' + problems.length); process.exit(1); }
console.log('PARITY FIXES PASSED');
