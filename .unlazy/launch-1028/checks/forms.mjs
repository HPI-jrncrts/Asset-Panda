// Every form on the listed pages renders inside a kit wrapper that carries data-ap-form-skin (the styled form
// UI), no HubSpot default stylesheet is attached, and no two forms on one page share a form identity.
//   node forms.mjs --paths <json> [--limit N] [--control]
// --control strips the skin marker from the first wrapper on the first page; the check must then FAIL.
//
// PROPOSED amendments (leaf-1.1.3, 2026-10-28): (a) the kit's own inlined sheets (id ap-ui-*) are the skin, not
// HubSpot's default CSS; (b) HubSpot's src-less iframe forms (iframe.hs-form-iframe) count as forms; (c) the
// booking pages' Calendly / Cal.com iframes count as forms and their hosts are allowed for read-only GETs;
// (d) the page is walked once so lazily mounted forms (demo-cta) draw before counting.
import { DEV, launch, guardPage, readPaths, arg, flag, sleep } from './lib.mjs';

const SEL = 'form, iframe[src*="hsforms"], iframe[src*="hubspot"], iframe.hs-form-iframe, iframe[id^="hs-form-iframe"], iframe[src*="calendly.com"], iframe[src*="cal.com"]';
const paths = readPaths(arg('--paths', '.unlazy/launch-1028/forms-pages.json')).slice(0, +arg('--limit', 100000));
const browser = await launch();
const fails = [];
let first = true;
for (const p of paths) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  // Let the HubSpot embed script and form HTML in (read-only) but never a submit.
  await page.route(/google-analytics|googletagmanager|clarity\.ms|facebook|linkedin|doubleclick|stape|data\.assetpanda/i, (r) => r.abort());
  await page.route(/hsforms\.com\/submissions|forms\.hubspot\.com\/.*submit|\/wp-json\/.*(demo|quote|submit)/i, (r) => r.abort());
  // The booking pages' schedulers (read-only GETs; booking endpoints stay aborted). Page routes outrank the guard.
  await page.route(/https:\/\/(assets\.calendly\.com|[a-z0-9.-]*calendly\.com|app\.cal\.com|[a-z0-9.-]*\.cal\.com)\//i, (r) =>
    (/\/api\/(book|booking|v2\/bookings)|scheduled_events|\/submit/i.test(r.request().url()) || r.request().method() !== 'GET') ? r.abort() : r.continue());
  try {
    await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await page.waitForFunction((sel) => document.querySelector(sel) !== null, SEL, { timeout: 20000 }).catch(() => {});
    // demo-cta mounts its form on the first scroll / in idle time: walk the page so lazy mounts fire.
    await page.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); } window.scrollTo(0, 0); });
    await page.waitForTimeout(4000);
    if (flag('--control') && first) await page.evaluate(() => { const w = document.querySelector('[data-ap-form-skin]'); if (w) w.removeAttribute('data-ap-form-skin'); });
    const r = await page.evaluate((sel) => {
      const forms = [...document.querySelectorAll(sel)].filter((f) => !/wp-admin|search/i.test(f.className + ' ' + (f.getAttribute('role') || '') + ' ' + (f.id || '')) && f.getBoundingClientRect().height > 40);
      const ids = forms.map((f) => (f.getAttribute('data-form-id') || f.id || f.getAttribute('src') || f.getAttribute('action') || '').replace(/^hsForm_/, '').slice(0, 80));
      const outside = forms.filter((f) => !f.closest('[data-ap-form-skin]')).map((f) => (f.id || f.className || f.tagName).toString().slice(0, 60));
      // HubSpot-authored styles only: the kit's own inlined sheets (id ap-ui-*) are the skin, not HubSpot's default.
      const defaultCss = [...document.querySelectorAll('link[rel="stylesheet"], style')].filter((s) => !/^ap-ui-/.test(s.id || '')).some((s) => /hsforms|hubspot/i.test(s.href || '') || /\.hs-form\s*\{|hs-form-field/.test(s.textContent || '') && !(s.closest && s.closest('[data-ap-form-skin]')));
      const dup = ids.filter((id, i) => id && ids.indexOf(id) !== i);
      return { n: forms.length, outside, dup, defaultCss };
    }, SEL);
    if (!r.n) fails.push(p + ' no form found');
    if (r.outside.length) fails.push(p + ' form outside the kit skin: ' + r.outside.join(' | '));
    if (r.dup.length) fails.push(p + ' duplicate form: ' + r.dup.join(' | '));
    if (r.defaultCss) fails.push(p + ' HubSpot default form stylesheet present');
  } catch (e) { fails.push(p + ' load error ' + String(e.message).slice(0, 100)); }
  await page.close();
  first = false;
  await sleep(1500);
}
await browser.close();
for (const f of fails) console.log('FAIL ' + f);
console.log('pages=' + paths.length + ' failures=' + fails.length);
if (fails.length) { console.log('FORM CHECK FAILED'); process.exit(1); }
console.log('FORM CHECK PASSED pages=' + paths.length);
