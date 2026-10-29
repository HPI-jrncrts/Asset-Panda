// Partners page (dev 2321, contract C40) rebuilt from Figma 7368:176529 as kit sections (kit 1.3.131). At 1440, 1024
// and 390: kit 1.3.131 is served; the five sections render in Figma order (hero, steps, quote, awards, registration);
// no horizontal overflow, no broken image; the registration card is wired to the live HubSpot partner form (024ab0e4…);
// the hero's Partner Registration button jumps to #partner-registration and the section lands below the sticky header.
// Saves a full-page capture per width for the side-by-side review against the Figma frame. Never submits the form.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const SHOTS = 'reports/launch-1028/shots/partners';
fs.mkdirSync(SHOTS, { recursive: true });
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const ORDER = ['ap-solution-hero', 'ap-solution-features', 'ap-founder-quote', 'ap-solution-awards', 'ap-demo-cta'];
const browser = await launch();
for (const width of [1440, 1024, 390]) {
  const p = await browser.newPage({ viewport: { width, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + '/partners/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1500);
  const r = await p.evaluate(async (ORDER) => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((res) => setTimeout(res, 80)); }
    window.scrollTo(0, 0); await new Promise((res) => setTimeout(res, 300));
    const kit = [...document.querySelectorAll('link[rel=stylesheet],script[src]')].map((e) => e.href || e.src).find((h) => /asset-panda-ui-kit\/.*ver=/.test(h)) || '';
    const found = ORDER.map((c) => { const el = document.querySelector('main .' + c + ', .wp-site-blocks .' + c); return el ? Math.round(el.getBoundingClientRect().top + window.pageYOffset) : null; });
    const broken = [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 0).map((i) => i.currentSrc || i.src).slice(0, 3);
    return { ver: (kit.match(/ver=([\d.]+)/) || [])[1] || null, found, broken, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth, anchor: !!document.getElementById('partner-registration') };
  }, ORDER);
  const vn = String(r.ver || '0.0.0').split('.').map(Number);
  ok(vn[0] === 1 && (vn[1] > 3 || (vn[1] === 3 && vn[2] >= 131)), width + ': kit 1.3.131 or later served', 'ver=' + r.ver);
  const inOrder = r.found.every((t) => t !== null) && r.found.every((t, i) => i === 0 || t > r.found[i - 1]);
  ok(inOrder, width + ': hero, steps, quote, awards, registration render in order', JSON.stringify(r.found));
  ok(!r.overflow, width + ': no horizontal overflow');
  ok(r.broken.length === 0, width + ': no broken image', r.broken.join(' '));
  ok(r.anchor, width + ': #partner-registration anchor present');
  // The guard aborts every HubSpot host, so the form cannot draw here: check the card is wired to the live form
  // (portal 4155061, form 024ab0e4…, region na2). The drawn form is checked once in a real browser, never submitted.
  const form = await p.evaluate(() => { const card = document.getElementById('partner-registration'); if (!card) return null; const h = card.outerHTML; return { portal: h.includes('4155061'), form: h.includes('024ab0e4-66a2-49d1-a857-6357604454fb'), region: /na2/.test(h) }; });
  ok(!!form && form.portal && form.form && form.region, width + ': registration card is wired to the live partner form', JSON.stringify(form));
  // Anchor jump from the hero button.
  const btn = p.locator('.ap-solution-hero a[href="#partner-registration"]').first();
  if (await btn.count()) {
    await btn.click(); await p.waitForTimeout(1500);
    const land = await p.evaluate(() => { const s = document.getElementById('partner-registration'); const h = document.querySelector('.ap-site-header, header'); return { top: Math.round(s.getBoundingClientRect().top), header: h ? Math.round(h.getBoundingClientRect().bottom) : 0 }; });
    ok(land.top >= land.header - 2 && land.top < 300, width + ': Partner Registration jumps to the form below the header', JSON.stringify(land));
  } else ok(false, width + ': hero Partner Registration button present');
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(400);
  await p.screenshot({ path: SHOTS + '/dev-' + width + '.png', fullPage: true });
  await p.close(); await sleep(3500);
}
await browser.close();
if (problems.length) { console.log('PARTNERS FAILED: ' + problems.length); process.exit(1); }
console.log('PARTNERS PASSED');
