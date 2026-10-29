// Joseph's product-page batch 2 on dev (see gates/leaf-1.6.2.md G1). Five product pages at 390: badges hidden,
// chip strip visible inside the hero above the art with the art's top 8–14px below the strip, strip track animating;
// at 1440: every connector line starts within 8px of the panda tile and ends within 8px of its card's edge; Local
// Government list: no data-ap-reveal, hover-shadow rule present; footer Company column: "Our Technology" →
// /asset-intelligence-platform/.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const PAGES = ['local-government', 'public-works', 'fire', 'police', 'k12'];
const problems = [];
const ok = (cond, label, detail) => { console.log((cond ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!cond) problems.push(label); };
const browser = await launch();
for (const slug of PAGES) {
  const url = DEV + '/products/asset-panda-for-' + slug + '/?nc=' + Date.now();
  const m = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await guardPage(m);
  await m.goto(url, { waitUntil: 'load', timeout: 90000 });
  await m.waitForTimeout(1800);
  // The cards float on a loop (±8 px): cancel every animation except the chip marquee so the layout position is read.
  await m.evaluate(() => { document.getAnimations().forEach((a) => { if (!(a.animationName && /Chips/i.test(a.animationName))) a.cancel(); }); });
  await m.waitForTimeout(200);
  const r = await m.evaluate(() => {
    const vis = (el) => !!el && getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 0;
    const hero = document.querySelector('.ap-vhero');
    const strip = document.querySelector('.ap-vhero__feats');
    const track = document.querySelector('.ap-vhero__feats-track');
    const certs = document.querySelector('.ap-vhero__certs');
    const layers = [...document.querySelectorAll('.ap-vhero__dash, .ap-vhero__person, .ap-vhero__card, .ap-vhero__mobcards')].filter(vis);
    const artTop = layers.length ? Math.min(...layers.map((l) => l.getBoundingClientRect().top + window.pageYOffset)) : null;
    const h = hero ? hero.getBoundingClientRect() : null;
    const cb = certs ? certs.getBoundingClientRect() : null;
    return { stripVisible: vis(strip), stripInHero: !!(strip && hero && hero.contains(strip)), stripTop: strip ? Math.round(strip.getBoundingClientRect().top + window.pageYOffset) : null, stripBottom: strip ? Math.round(strip.getBoundingClientRect().bottom + window.pageYOffset) : null, heroBottom: h ? Math.round(h.bottom + window.pageYOffset) : null, artTop: artTop === null ? null : Math.round(artTop), certsVisible: vis(certs), certsBottom: cb ? Math.round(cb.bottom + window.pageYOffset) : null, trackAnim: track ? getComputedStyle(track).animationName : '', mflow: !!(hero && hero.classList.contains('ap-vhero--mflow')) };
  });
  // Corrected phone order (QA 2026-10-29): badges under the button, art 10px under the badges, icon strip at the
  // foot of the hero.
  const gap = r.artTop !== null && r.certsBottom !== null ? r.artTop - r.certsBottom : null;
  ok(r.certsVisible, slug + ' 390: compliance badges shown under the button');
  ok(gap !== null && gap >= 8 && gap <= 14, slug + ' 390: art starts 10px under the badges', 'gap=' + gap + 'px (art top ' + r.artTop + ', badges bottom ' + r.certsBottom + ', mflow=' + r.mflow + ')');
  ok(r.stripVisible && r.stripInHero && r.heroBottom - r.stripBottom <= 2 && r.stripTop > r.certsBottom + 100, slug + ' 390: icon strip at the foot of the hero, below the art', 'strip ' + r.stripTop + '-' + r.stripBottom + ' hero bottom ' + r.heroBottom);
  ok(r.trackAnim && r.trackAnim !== 'none', slug + ' 390: chip track auto-swipes', 'animation=' + r.trackAnim);
  await m.close();
  await sleep(2000);
  const d = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(d);
  await d.goto(url + 'd', { waitUntil: 'load', timeout: 90000 });
  await d.waitForTimeout(2500);
  // The cards float a few px on a loop; pause every animation, let the kit redraw the lines against the paused
  // positions, then measure — otherwise the end points are read at a different phase than they were drawn.
  await d.evaluate(() => { document.getAnimations().forEach((a) => a.pause()); window.dispatchEvent(new Event('resize')); });
  await d.waitForTimeout(400);
  const g = await d.evaluate(() => {
    const hero = document.querySelector('.ap-vhero');
    const svg = document.querySelector('.ap-vhero__lines--d');
    const badge = document.querySelector('.ap-vhero__badge');
    if (!svg || !badge) return { error: 'no lines svg or badge' };
    const sr = svg.getBoundingClientRect();
    const sx = sr.width / 1440, sy = sr.height / 550;
    const br = badge.getBoundingClientRect();
    const cards = [...document.querySelectorAll('.ap-vhero__card')].map((c) => c.getBoundingClientRect());
    const distToRect = (x, y, r) => { const dx = Math.max(r.left - x, 0, x - r.right); const dy = Math.max(r.top - y, 0, y - r.bottom); return Math.sqrt(dx * dx + dy * dy); };
    const lines = [...svg.querySelectorAll('line')].map((l, i) => { const x1 = +l.getAttribute('x1') * sx + sr.left, y1 = +l.getAttribute('y1') * sy + sr.top, x2 = +l.getAttribute('x2') * sx + sr.left, y2 = +l.getAttribute('y2') * sy + sr.top; const card = cards[i]; return { i, startToTile: Math.round(distToRect(x1, y1, br)), endToCard: card ? Math.round(distToRect(x2, y2, card)) : null, hidden: getComputedStyle(l.parentNode).display === 'none' }; });
    return { measured: hero.getAttribute('data-ap-vhero-lines') === 'measured', lines };
  });
  if (g.error) ok(false, slug + ' 1440: connector lines present', g.error);
  else {
    const live = g.lines.filter((l) => !l.hidden && l.endToCard !== null);
    ok(g.measured && live.length > 0 && live.every((l) => l.startToTile <= 8 && l.endToCard <= 8), slug + ' 1440: every connector line runs tile → card', JSON.stringify(g.lines));
  }
  await d.close();
  await sleep(2000);
}
const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(p);
await p.goto(DEV + '/products/asset-panda-for-local-government/?nc=' + Date.now() + 'l', { waitUntil: 'load', timeout: 90000 });
await p.waitForTimeout(1200);
const sl = await p.evaluate(() => { const s = document.querySelector('.ap-sticky-list'); const cards = s ? s.querySelectorAll('.ap-sticky-list__card') : []; const rev = s ? s.querySelectorAll('[data-ap-reveal]').length : -1; const hasHover = [...document.styleSheets].some((ss) => { try { return [...ss.cssRules].some((r) => /ap-sticky-list__card:hover/.test(r.cssText || '') || (r.cssRules && [...r.cssRules].some((q) => /ap-sticky-list__card:hover/.test(q.cssText || '')))); } catch (e) { return false; } }); const foot = [...document.querySelectorAll('footer a')].find((a) => /Our Technology/i.test(a.textContent)); return { cards: cards.length, reveal: rev, hasHover, footerLink: foot ? foot.getAttribute('href') : null }; });
ok(sl.cards > 0 && sl.reveal === 0, 'Local Government list: cards present, none with data-ap-reveal', 'cards=' + sl.cards + ' reveal=' + sl.reveal);
ok(sl.hasHover, 'Local Government list: hover shadow rule shipped');
ok(sl.footerLink === '/asset-intelligence-platform/', 'footer Company column links Our Technology → /asset-intelligence-platform/', 'href=' + sl.footerLink);
await p.close();
await browser.close();
if (problems.length) { console.log('QA BATCH2 FAILED: ' + problems.length); process.exit(1); }
console.log('QA BATCH2 PASSED pages=' + PAGES.length);
