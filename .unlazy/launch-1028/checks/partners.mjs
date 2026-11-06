// Partners page (dev 2321, contract C40) rebuilt from Figma 7368:176529 as kit sections (kit 1.3.131). At 1440, 1024
// and 390: kit 1.3.131 is served; the five sections render in Figma order (hero, steps, quote, awards, registration);
// no horizontal overflow, no broken image; the registration card is wired to the live HubSpot partner form (024ab0e4…);
// the hero's Partner Registration button jumps to #partner-registration and the section lands below the sticky header.
// Saves a full-page capture per width for the side-by-side review against the Figma frame. Never submits the form.
// Amended 2026-10-30 (Joseph: the Partners doc 1dwK8MUH…, "Asset Panda Partners & Resellers"): the page now follows the
// doc: hero, buyer / reseller choice, #purchasing-partners, the government-contracts call to action, #reseller-program,
// the process steps, then the registration form; the quote and awards strip are gone. The hero's Find a Purchasing
// Partner button jumps to #purchasing-partners and the reseller section's Apply button to #partner-registration.
// Amended 2026-10-31 (Joseph's Partners revisions, kit 1.3.162): the buyer / reseller choice is two call-out cards at
// the foot of the hero (.ap-solution-hero__choice, each with its button); the UK partner joins the US section
// (page-cards--joined) and stands centred (--few); Government Procurement is the Education hub's integration row
// (hub-intel eduhub) linking /purchasing-contracts/; Become a Partner is the asset-scene with the splash
// (#reseller-program); the process is the Ursa AI timeline (continuity-columns); Channel Partnerships is the
// ursa-band with the panda mark; Looking for integrations? is the dark closing card (platform-cta) to /integrations/.
// Amended again 2026-10-31 (round 3, kit 1.3.165): Buy / Resell = one glass card in the hero with text links; the
// partners = glass cards with logos (why-cards #purchasing-partners: United States 4, International / UK 1); the
// reseller scene's cards sit on their icon discs (asset-scene--attached); registration = Our People's layout with the
// form (demo-cta--values); Channel Partnerships = the call-out bar with the panda mark (problem-callouts--bar).
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const SHOTS = 'reports/launch-1028/shots/partners';
fs.mkdirSync(SHOTS, { recursive: true });
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const ORDER = ['.ap-solution-hero', '#purchasing-partners', '.ap-hub-intel', '#reseller-program', '.ap-continuity-columns', '#partner-registration', '.ap-problem-callouts--bar', '.ap-platform-cta'];
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
    const found = ORDER.map((c) => { const el = document.querySelector(c); return el ? Math.round(el.getBoundingClientRect().top + window.pageYOffset) : null; });
    const broken = [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 0).map((i) => i.currentSrc || i.src).slice(0, 3);
    return { ver: (kit.match(/ver=([\d.]+)/) || [])[1] || null, found, broken, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth, anchor: !!document.getElementById('partner-registration') };
  }, ORDER);
  const vn = String(r.ver || '0.0.0').split('.').map(Number);
  ok(vn[0] === 1 && (vn[1] > 3 || (vn[1] === 3 && vn[2] >= 131)), width + ': kit 1.3.131 or later served', 'ver=' + r.ver);
  const inOrder = r.found.every((t) => t !== null) && r.found.every((t, i) => i === 0 || t > r.found[i - 1]);
  ok(inOrder, width + ': hero, purchasing partners, government row, reseller program, steps, registration, channel bar, integrations card render in order', JSON.stringify(r.found));
  ok(!r.overflow, width + ': no horizontal overflow');
  ok(r.broken.length === 0, width + ': no broken image', r.broken.join(' '));
  ok(r.anchor, width + ': #partner-registration anchor present');
  // The guard aborts every HubSpot host, so the form cannot draw here: check the card is wired to the live form
  // (portal 4155061, form 024ab0e4…, region na2). The drawn form is checked once in a real browser, never submitted.
  const form = await p.evaluate(() => { const card = document.getElementById('partner-registration'); if (!card) return null; const h = card.outerHTML; return { portal: h.includes('4155061'), form: h.includes('024ab0e4-66a2-49d1-a857-6357604454fb'), region: /na2/.test(h) }; });
  ok(!!form && form.portal && form.form && form.region, width + ': registration card is wired to the live partner form', JSON.stringify(form));
  // Anchor jumps: the hero's Find a Purchasing Partner button, then the reseller section's Apply button.
  for (const [sel, target, label] of [['.ap-solution-hero a[href="#purchasing-partners"]', 'purchasing-partners', 'Find a Purchasing Partner'], ['#reseller-program a[href="#partner-registration"]', 'partner-registration', 'Apply to Become a Partner']]) {
    await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(300);
    const btn = p.locator(sel).first();
    if (await btn.count()) {
      await btn.scrollIntoViewIfNeeded(); await btn.click(); await p.waitForTimeout(1500);
      const land = await p.evaluate((t) => { const s = document.getElementById(t); const h = document.querySelector('.ap-site-header, header'); return { top: Math.round(s.getBoundingClientRect().top), header: h ? Math.round(h.getBoundingClientRect().bottom) : 0 }; }, target);
      ok(land.top >= land.header - 2 && land.top < 300, width + ': ' + label + ' jumps to #' + target + ' below the header', JSON.stringify(land));
    } else ok(false, width + ': ' + label + ' button present');
  }
  const links = await p.evaluate(() => ({
    gov: !!document.querySelector('.ap-hub-intel a[href="/purchasing-contracts/"]'),
    integ: !!document.querySelector('.ap-platform-cta a[href="/integrations/"]'),
    choices: [...document.querySelectorAll('.ap-solution-hero__choice .ap-solution-hero__choice-link')].map((a) => a.getAttribute('href')),
    joined: [...document.querySelectorAll('#purchasing-partners .ap-why-cards__grid')].map((g) => g.children.length).join('/') + ' logos ' + [...document.querySelectorAll('#purchasing-partners .ap-why-cards__logo img')].filter((i) => i.naturalWidth > 0).length,
    splash: !!document.querySelector('#reseller-program.ap-asset-scene--splash.ap-asset-scene--attached'),
    panda: !!document.querySelector('.ap-problem-callouts--bar .ap-problem-callouts__cta-logo img'),
  }));
  ok(links.gov, width + ': government row links /purchasing-contracts/');
  ok(links.integ, width + ': integrations card links /integrations/');
  ok(JSON.stringify(links.choices) === JSON.stringify(['#purchasing-partners', '#reseller-program']), width + ': hero choice links jump to the two sections', JSON.stringify(links.choices));
  ok(links.joined === '4/1 logos 5', width + ': partners: United States 4 and UK 1, five logos load', links.joined);
  ok(links.splash && links.panda, width + ': reseller scene carries the splash with attached cards, the channel bar the panda mark', JSON.stringify(links));
  await p.evaluate(() => window.scrollTo(0, 0)); await p.waitForTimeout(400);
  await p.screenshot({ path: SHOTS + '/dev-' + width + '.png', fullPage: true });
  await p.close(); await sleep(3500);
}
await browser.close();
if (problems.length) { console.log('PARTNERS FAILED: ' + problems.length); process.exit(1); }
console.log('PARTNERS PASSED');
