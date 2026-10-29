// Kits 1.3.121–1.3.124 on dev: Joseph's 2026-10-29 afternoon notes, one PASS/FAIL line each.
// Token DEPLOY 1124 PASSED. Paced ≥3 s between page loads; HubSpot/booking requests aborted by guardPage.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const open = async (path, width = 1440, h = 900) => {
  const p = await browser.newPage({ viewport: { width, height: h } });
  await guardPage(p);
  await p.goto(DEV + path + (path.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(2200);
  return p;
};
const close = async (p) => { await p.close(); await sleep(3200); };

// Homepage desktop: button hover, login icon, mega menu grid, cards 4/6, industries.
{
  const p = await open('/');
  const r = await p.evaluate(() => {
    const demo = document.querySelector('.ap-header-demo');
    const tagged = [...document.querySelectorAll('[data-ap-cta]')];
    const grad = tagged.find((e) => e.getAttribute('data-ap-cta') === 'grad' && e.getBoundingClientRect().width > 60);
    const fx = grad && grad.querySelector('.ap-cta-fx');
    const fr = fx && fx.getBoundingClientRect(); const gr = grad && grad.getBoundingClientRect();
    const posts = [...document.querySelectorAll('.ap-mega-who-we-help .ap-help-panel')].map((pn) => pn.querySelectorAll('.ap-mega-post').length);
    const date = document.querySelector('.ap-mega-post__date');
    const aurora = document.querySelector('.ap-industries .ap-sector-bg-img');
    const auroraSvg = document.querySelector('.ap-industries .ap-sector-bg svg filter');
    return { liquidLeft: document.querySelectorAll('.ap-liquid, [data-ap-liquid]').length, demoKind: demo && demo.getAttribute('data-ap-cta'), intl: document.documentElement.classList.contains('ap-intl'), fxFits: !!(fr && gr && Math.abs(fr.width - gr.width) < 2 && Math.abs(fr.height - gr.height) < 2), posts, dateShown: date ? getComputedStyle(date).display !== 'none' : null, loginGrad: !!document.querySelector('.ap-login-icon .ap-login-icon__grad'), aurora: !!aurora, auroraSvg: !!auroraSvg, viewAll: !!document.querySelector('.ap-help-all') };
  });
  ok(r.liquidLeft === 0, 'buttons: no liquid markup left', 'count=' + r.liquidLeft);
  ok(r.demoKind === (r.intl ? 'outline' : 'grad'), 'header Free Demo classified by its current look', JSON.stringify({ kind: r.demoKind, intl: r.intl }));
  ok(r.fxFits, 'gradient button: edge-light layer is sized to the button');
  ok(r.posts.length >= 7 && r.posts.every((n) => n === 4), 'mega menu: every Who We Help cabinet shows 4 posts', JSON.stringify(r.posts));
  ok(r.dateShown === false, 'mega menu: post dates hidden');
  ok(r.loginGrad, 'login icon: gradient layer present');
  ok(r.aurora && !r.auroraSvg, 'industries: pre-rendered background, no live SVG blur');
  // Outline hover: header demo (if outline) fills green with white text.
  const demo = p.locator('.ap-header-demo').first();
  if (await demo.count() && r.demoKind === 'outline') {
    await demo.hover(); await p.waitForTimeout(400);
    const h = await demo.evaluate((e) => ({ bg: getComputedStyle(e).backgroundColor, color: getComputedStyle(e).color }));
    ok(h.bg === 'rgb(0, 169, 145)' && h.color === 'rgb(255, 255, 255)', 'outline button hover: green fill, white text', JSON.stringify(h));
    await p.mouse.move(5, 5);
  }
  // Cards 4 and 6.
  for (const [id, label] of [['ap-card-inspection', 'card 4 preview touches the card bottom'], ['ap-card-reporting', 'card 6 strip reaches the card edges']]) {
    const c = p.locator('#' + id); await c.scrollIntoViewIfNeeded(); await p.waitForTimeout(400);
    const bb = await c.boundingBox(); await p.mouse.move(bb.x + bb.width / 2, bb.y + 60); await p.waitForTimeout(900);
    const g = await p.evaluate((id) => { const c = document.getElementById(id).getBoundingClientRect(); const a = document.querySelector('#' + id + ' .ap-hover-art').getBoundingClientRect(); return { cl: Math.round(c.left), cr: Math.round(c.right), cb: Math.round(c.bottom), al: Math.round(a.left), ar: Math.round(a.right), ab: Math.round(a.bottom) }; }, id);
    const pass = id === 'ap-card-inspection' ? Math.abs(g.ab - g.cb) <= 3 : (g.al <= g.cl + 2 && g.ar >= g.cr - 2);
    ok(pass, label, JSON.stringify(g));
    await p.mouse.move(5, 5); await p.waitForTimeout(400);
  }
  await close(p);
}
// Phone: burger, hero spacing (16px), product hero order, footer splash.
{
  const p = await open('/products/asset-panda-for-police/', 390, 844);
  const r = await p.evaluate(() => { const svg = document.querySelector('.ap-mobile-burger .ap-burger'); const ft = document.querySelector('.ap-site-footer'); return { burger: !!svg, footerLayers: ft ? (getComputedStyle(ft).backgroundImage.match(/radial-gradient/g) || []).length : 0 }; });
  ok(r.burger, 'burger: animated SVG icon present');
  await p.click('.ap-mobile-burger'); await p.waitForTimeout(800);
  const mid = await p.evaluate(() => getComputedStyle(document.querySelector('.ap-burger__mid')).strokeDasharray);
  ok(/110/.test(mid), 'burger: opening draws the ring', 'dasharray=' + mid);
  await p.click('.ap-mobile-burger'); await p.waitForTimeout(800);
  const mid2 = await p.evaluate(() => getComputedStyle(document.querySelector('.ap-burger__mid')).strokeDasharray);
  ok(/^18/.test(mid2), 'burger: closing returns to the three lines', 'dasharray=' + mid2);
  ok(r.footerLayers >= 8, 'phone footer: round splashes', 'radial layers=' + r.footerLayers);
  await close(p);
}
// Solution pages: CTA card back, splash only on barcode labels; dashboards; sticky steps heading; help-desk hero.
{
  const p = await open('/solutions/inventory-management/');
  const r = await p.evaluate(() => { const card = document.querySelector('.ap-solution-cta__card'); const t = document.querySelector('.epx-dash__table'); return { cardBg: card ? getComputedStyle(card).backgroundImage : '', splash: !!document.querySelector('.ap-solution-cta--splash'), tableOp: t ? getComputedStyle(t).opacity : null }; });
  ok(/linear-gradient/.test(r.cardBg) && !r.splash, 'solution CTA: blue card back on solution pages', r.cardBg.slice(0, 60));
  ok(r.tableOp === '0.4', 'solution dashboard: tool image stays dimmed under the gradient on desktop', 'opacity=' + r.tableOp);
  const head = p.locator('.ap-solution-features__head').first();
  if (await head.count()) {
    await head.scrollIntoViewIfNeeded(); await p.waitForTimeout(300);
    const y1 = await head.evaluate((e) => e.getBoundingClientRect().top);
    await p.mouse.wheel(0, 300); await p.waitForTimeout(500);
    const y2 = await head.evaluate((e) => e.getBoundingClientRect().top);
    const stickTop = await head.evaluate((e) => parseFloat(getComputedStyle(e).top) || 0);
    ok(Math.abs(y2 - stickTop) < 3 && y1 > y2, 'solution steps: heading stays pinned while the steps scroll', 'top ' + Math.round(y1) + ' → ' + Math.round(y2));
  }
  await close(p);
  const q = await open('/solutions/inventory-management/', 390, 844);
  const t = await q.evaluate(() => { const e = document.querySelector('.epx-dash__table'); const c = getComputedStyle(e); return { op: c.opacity, border: c.borderTopWidth }; });
  ok(t.op === '1' && t.border !== '0px', 'solution dashboard on phones: full strength, framed', JSON.stringify(t));
  await close(q);
  const b = await open('/barcode-labels/');
  // Amended 2026-10-29 late (Joseph): /barcode-labels/ ends with the blue card like every solution page.
  ok(await b.evaluate(() => !document.querySelector('.ap-solution-cta--splash') && !!document.querySelector('.ap-solution-cta__card')), 'barcode labels: CTA is the blue card');
  await close(b);
  const h = await open('/solutions/help-desk-ticketing/');
  const hb = await h.evaluate(() => getComputedStyle(document.querySelector('.ap-assist-hero__orbs')).backgroundImage);
  ok(/radial-gradient/.test(hb) && !/url\(/.test(hb), 'help-desk hero: Ursa gradient, no orbs image', hb.slice(0, 60));
  const sp = await h.evaluate(() => getComputedStyle(document.querySelector('.ap-assist-problem'), '::before').maskImage || getComputedStyle(document.querySelector('.ap-assist-problem'), '::before').webkitMaskImage);
  ok(/radial-gradient/.test(sp || ''), 'Ursa/help-desk "What is Ursa" splash confined to the middle', (sp || '').slice(0, 50));
  await close(h);
}
// AIP phone wires, hub puddle, product dashboard eager.
{
  const p = await open('/asset-intelligence-platform/', 390, 844);
  await p.locator('.ap-asset-scene__stage').first().scrollIntoViewIfNeeded(); await p.waitForTimeout(1500);
  const w = await p.evaluate(() => { const s = document.querySelector('.ap-asset-scene__stage'); return { drawn: s.getAttribute('data-ap-wires'), paths: s.querySelectorAll('.ap-asset-scene__wires path').length, discs: s.querySelectorAll('.ap-asset-scene__disc').length }; });
  ok(w.drawn === 'drawn' && w.paths === 3 && w.discs === 3, 'AIP phone: three lines from the orb to discs on the cards', JSON.stringify(w));
  await close(p);
  const e = await open('/solutions/education/');
  // Amended 2026-10-29 late (Joseph: "move the gradient … between the 2 sections"): the puddle is drawn under the first row.
  ok(await e.evaluate(() => { const row = document.querySelector('.ap-hub-intel--gov > .apg-int-row:first-child, .ap-hub-intel--eduhub .apu-container > .apu-block:first-child'); return !!row && /radial-gradient/.test(getComputedStyle(row, '::after').backgroundImage); }), 'hub intelligence section: gradient puddle between the rows');
  await close(e);
  const g = await open('/products/asset-panda-for-higher-education/');
  ok(await g.evaluate(() => [...document.querySelectorAll('.ap-dashboard-showcase img')].slice(0, 2).every((i) => i.loading === 'eager')), 'product dashboard showcase: backdrop and dashboard load with the page');
  await close(g);
}
// Integrations: choosing a category shuffles the cards (animations run, then settle with the right set shown).
{
  const p = await open('/integrations/');
  const radio = p.locator('input[type="radio"]').filter({ hasNot: p.locator('[checked]') }).nth(3);
  if (await radio.count()) {
    await radio.scrollIntoViewIfNeeded(); await p.waitForTimeout(300);
    const before = await p.evaluate(() => [...document.querySelectorAll('[data-ap-intdir-card]')].filter((c) => !c.hidden).length);
    await p.evaluate(() => { const r = document.querySelector('input[data-ap-intdir-cat][value="collaboration"]') || [...document.querySelectorAll('input[data-ap-intdir-cat]')][3]; r.click(); });
    await p.waitForTimeout(120);
    const mid = await p.evaluate(() => document.getAnimations().filter((a) => a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest('[data-ap-intdir-card]')).length);
    await p.waitForTimeout(1000);
    const after = await p.evaluate(() => ({ shown: [...document.querySelectorAll('[data-ap-intdir-card]')].filter((c) => !c.hidden && !c.classList.contains('is-leaving')).length, running: document.getAnimations().filter((a) => a.playState === 'running' && a.effect && a.effect.target && a.effect.target.closest && a.effect.target.closest('[data-ap-intdir-card]')).length }));
    ok(mid > 0 && after.running === 0 && after.shown > 0 && after.shown !== before, 'integrations: picking a category shuffles the cards, then settles', JSON.stringify({ before, mid, after }));
  } else ok(false, 'integrations: category radios found');
  await close(p);
}
await browser.close();
if (problems.length) { console.log('DEPLOY 1124 FAILED: ' + problems.length); process.exit(1); }
console.log('DEPLOY 1124 PASSED');
