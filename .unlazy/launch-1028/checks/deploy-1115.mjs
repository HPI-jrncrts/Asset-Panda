// Kit 1.3.115 + content 1.3.4 on dev (contracts C26–C28, C31–C34, C24/C25 follow-ups). One PASS/FAIL line per
// outcome; token DEPLOY 1115 PASSED when all pass. Paced ≥3 s between page loads; HubSpot/booking requests aborted.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const open = async (path, width = 1440) => {
  const p = await browser.newPage({ viewport: { width, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + path + (path.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1500);
  return p;
};
const overlap = (a, b) => Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));

// C26: solution-hero top space + art size; inventory bubble inside its card.
const arts = [];
for (const slug of ['inventory-management', 'calibration-management']) {
  const p = await open('/solutions/' + slug + '/');
  const r = await p.evaluate(() => {
    const head = [...document.querySelectorAll('.ap-site-header__desk, .ap-site-header__mobile, header')].find((h) => h.getBoundingClientRect().height > 0);
    const eb = document.querySelector('.ap-solution-hero__eyebrow');
    const art = document.querySelector('.ap-solution-hero__visual, .ap-solution-hero__art, .ap-solution-hero .epx-hv')?.closest('[class*="ap-solution-hero__"]');
    const bubble = document.querySelector('.sp-hq__bubble'); const card = bubble && bubble.closest('.sp-hq');
    const hb = head ? head.getBoundingClientRect().bottom : 0;
    return { gap: eb ? Math.round(eb.getBoundingClientRect().top - hb) : null, artH: art ? Math.round(art.getBoundingClientRect().height) : null, bubbleOver: bubble && card ? Math.round(bubble.getBoundingClientRect().right - card.getBoundingClientRect().right) : null };
  });
  ok(r.gap !== null && r.gap <= 80, slug + ': hero copy starts ≤80px under the header', 'gap=' + r.gap);
  if (r.bubbleOver !== null) ok(r.bubbleOver <= 0, slug + ': chat bubble stays inside its card', 'overflow=' + r.bubbleOver + 'px');
  arts.push(r.artH);
  await p.close(); await sleep(3500);
}
// C27: help-desk floats spaced, crisp (opaque), above the veil.
{
  const p = await open('/solutions/help-desk-ticketing/');
  const r = await p.evaluate(() => {
    const cards = [...document.querySelectorAll('.ap-assist-hero .epx-hv--msg .epx-hv__flo, .ap-assist-hero .epx-hv--ursa .epx-hv__flo, .ap-assist-hero .epx-hv--resp .epx-hv__flo')];
    const rects = cards.map((c) => c.getBoundingClientRect());
    let worst = 0; for (let i = 0; i < rects.length; i++) for (let j = i + 1; j < rects.length; j++) { const a = rects[i], b = rects[j]; worst = Math.max(worst, Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top))); }
    const ops = cards.map((c) => { let o = 1; for (let e = c; e && e !== document.body; e = e.parentElement) o *= parseFloat(getComputedStyle(e).opacity); return Math.round(o * 100) / 100; });
    const bgs = cards.map((c) => getComputedStyle(c).backgroundColor);
    return { n: cards.length, worst: Math.round(worst), ops, bgs };
  });
  ok(r.n === 3 && r.worst === 0, 'help desk: the three floating cards do not overlap', JSON.stringify(r));
  ok(r.ops.every((o) => o >= 0.99) && r.bgs.every((b) => /rgb\(255, 255, 255\)|rgba\(255, 255, 255, 1\)/.test(b)), 'help desk: cards are solid white, no wash', JSON.stringify({ ops: r.ops, bgs: r.bgs }));
  await p.close(); await sleep(3500);
}
// C28: why-cards glass; solution-cta no card fill; problem-scene eduhub chips float; founder-quote radial splash.
{
  const p = await open('/solutions/inventory-management/');
  const r = await p.evaluate(() => {
    const cards = [...document.querySelectorAll('.ap-why-cards__card')];
    const cta = document.querySelector('.ap-solution-cta__card');
    return { glass: cards.length > 0 && cards.every((c) => /blur/.test(getComputedStyle(c).backdropFilter || getComputedStyle(c).webkitBackdropFilter || '')), n: cards.length, ctaBg: cta ? getComputedStyle(cta).backgroundImage : 'none' };
  });
  ok(r.glass, 'why-cards: cards are glass (backdrop blur)', 'cards=' + r.n);
  // Amended 2026-10-29 PM (Joseph: "All solution page, the blue background of the footer is gone"): solution pages keep
  // the blue card; the splash look is opt-in (look:"splash") and used only on /barcode-labels/.
  ok(/linear-gradient/.test(r.ctaBg), 'solution-cta: solution pages keep the blue card', r.ctaBg.slice(0, 60));
  await p.close(); await sleep(3500);
  const b = await open('/barcode-labels/');
  const s = await b.evaluate(() => { const sec = document.querySelector('.ap-solution-cta'); const card = sec && sec.querySelector('.ap-solution-cta__card'); return { splash: !!sec && sec.classList.contains('ap-solution-cta--splash'), cardBg: card ? getComputedStyle(card).backgroundImage : 'none' }; });
  // Amended again 2026-10-29 late (Joseph: every solution page, /barcode-labels/ included, ends with the blue card).
  ok(!s.splash && /linear-gradient/.test(s.cardBg), 'solution-cta: /barcode-labels/ ends with the blue card too', JSON.stringify({ splash: s.splash, cardBg: s.cardBg.slice(0, 40) }));
  await b.close(); await sleep(3500);
}
{
  const p = await open('/solutions/education/');
  const r = await p.evaluate(() => ({ chips: [1, 2, 3, 4].map((i) => { const c = document.querySelector('.apr-chip-' + i); return c ? getComputedStyle(c).animationName : 'missing'; }), viewAll: !!document.querySelector('.ap-help-all'), posts: document.querySelectorAll('.ap-mega-who-we-help .ap-mega-post').length, icons: [...document.querySelectorAll('img.ap-icon-img')].filter((i) => i.loading === 'lazy').length }));
  ok(r.chips.every((a) => a && a !== 'none' && a !== 'missing'), 'education problem-scene: the four chips float', JSON.stringify(r.chips));
  ok(r.viewAll, 'mega menu: View All Industries link present');
  ok(r.posts >= 2, 'mega menu: Who We Help cabinets carry latest posts', 'post cards=' + r.posts);
  ok(r.icons === 0, 'mega menu: no option icon is lazy-loaded', 'lazy=' + r.icons);
  await p.close(); await sleep(3500);
}
{
  const p = await open('/asset-intelligence-platform/');
  const r = await p.evaluate(() => { const f = document.querySelector('.ap-founder-quote'); return f ? getComputedStyle(f).backgroundImage : 'missing'; });
  ok(r !== 'missing' && !/linear-gradient\(rgb\(238, 242, 255\)/.test(r), 'founder-quote: no top-to-bottom tint band', r.slice(0, 80));
  await p.close(); await sleep(3500);
}
// C34: ROI page white + glass calculator + badge row.
{
  const p = await open('/roi-calculator/');
  const r = await p.evaluate(() => {
    const secs = [...document.querySelectorAll('.ap-roi-hero, .ap-pricing-calculator, .ap-solution-awards')].map((s) => ({ c: s.className.split(' ')[0], bg: getComputedStyle(s).backgroundColor }));
    const card = document.querySelector('.ap-pricing-calculator--2026 .ap-pricing-calculator__card');
    const cs = card ? getComputedStyle(card) : null;
    const badges = [...document.querySelectorAll('.ap-roi-hero img')].filter((i) => /govramp|hipaa|image-1\.svg/.test(i.src)).map((i) => i.naturalWidth > 0);
    return { secs, glassBg: cs ? cs.backgroundColor : null, blur: cs ? (cs.backdropFilter || cs.webkitBackdropFilter) : null, badges };
  });
  ok(r.secs.length >= 3 && r.secs.every((s) => /rgba?\(255, 255, 255|rgba\(0, 0, 0, 0\)/.test(s.bg)), 'ROI: every section on a white (or transparent-over-white) background', JSON.stringify(r.secs));
  ok(r.glassBg && /rgba\(255, 255, 255, 0\.8\)/.test(r.glassBg) && /blur/.test(r.blur || ''), 'ROI: calculator is an 80% glass card', r.glassBg + ' ' + r.blur);
  ok(r.badges.length === 3 && r.badges.every(Boolean), 'ROI: SOC 2 / GovRAMP / HIPAA badges load in the form card', JSON.stringify(r.badges));
  await p.close(); await sleep(3500);
}
// C32 + C33: hub filter swaps in place; blog post uses the light article header.
{
  const p = await open('/resource-center/blog/');
  const start = p.url();
  const tab = await p.evaluate(() => { const a = [...document.querySelectorAll('.ap-resource-hero a[href*="/filter/"]')].find((x) => !/is-active|aria-current/.test(x.outerHTML)); if (a) a.setAttribute('data-qa-tab', '1'); return a ? a.getAttribute('href') : null; });
  if (tab) {
    await p.evaluate(() => { window.__qaMarker = 1; });
    await p.click('[data-qa-tab="1"]');
    await p.waitForTimeout(2500);
    const r = await p.evaluate(() => ({ kept: window.__qaMarker === 1, url: location.pathname, count: (document.querySelector('[class*="resource-cards__count"], .ap-resource-cards__meta') || {}).textContent || '' }));
    ok(r.kept && r.url !== new URL(start).pathname, 'hub: filter tab swaps results in place (no page load) and updates the address', JSON.stringify({ tab, url: r.url, sameDocument: r.kept }));
  } else ok(false, 'hub: filter tab found on /resource-center/blog/');
  const post = await p.evaluate(() => { const a = document.querySelector('.ap-resource-cards a[href]'); return a ? new URL(a.href).pathname : null; });
  await p.close(); await sleep(3500);
  if (post) {
    const q = await open(post);
    const r = await q.evaluate(() => ({ h1: document.querySelectorAll('h1').length, dark: !!document.querySelector('.ap-resource-header:not(.ap-resource-header--article)'), article: !!document.querySelector('.ap-resource-header--article, .ap26-post-header, [class*="post-header"]') }));
    ok(r.h1 === 1 && !r.dark && r.article, 'blog post ' + post + ': light article header, one H1', JSON.stringify(r));
    await q.close();
  } else ok(false, 'blog post link found on the hub');
}
await browser.close();
if (problems.length) { console.log('DEPLOY 1115 FAILED: ' + problems.length); process.exit(1); }
console.log('DEPLOY 1115 PASSED');
