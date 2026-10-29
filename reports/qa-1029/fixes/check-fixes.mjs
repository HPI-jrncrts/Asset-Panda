// After-deploy check for the QA 2026-10-29 fixes F1–F4 on dev. Read-only, paced.
//   cd "C:/Sample Code" && MSYS_NO_PATHCONV=1 node reports/qa-1029/fixes/check-fixes.mjs
// Prints PASS/FAIL per check and "QA FIXES n/N PASSED" at the end.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from 'file:///C:/Sample%20Code/.unlazy/launch-1028/checks/lib.mjs';
const b = await launch(); let pass = 0, total = 0;
const ok = (name, cond, info) => { total++; if (cond) pass++; console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}  ${info || ''}`); };
// --inject: fixes not deployed yet (F6–F11) are loaded into the real dev page before the assertions run, so the
// same checks prove them before shipping. Without it every check reads dev exactly as served.
const INJECT = process.argv.includes('--inject');
const FIXDIR = new URL('.', import.meta.url);
const PENDING_CSS = ['F6-site-header.style.append.css', 'F7-tokens.cta-classes.append.css', 'F8-tokens.gradient-per-line.append.css', 'F9-intel-dashboard.style.append.css', 'F10-asset-panda-content.ap-content.append.css', 'F11-asset-scene.style.append.css'].map((f) => fs.readFileSync(new URL(f, FIXDIR), 'utf8')).join('\n');
const F6_MARKUP = '<p>Log in</p><a href="https://assetpanda.app">Asset Panda Pro<small>with Ursa AI</small></a><a href="https://login.assetpanda.com/users/sign_in">Asset Panda Classic<small>login.assetpanda.com</small></a>';
async function open(path, w, h) { const p = await b.newPage({ viewport: { width: w, height: h }, isMobile: w < 500, hasTouch: w < 500 }); await guardPage(p); await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  if (INJECT) { await p.addStyleTag({ content: PENDING_CSS }); await p.evaluate((html) => { const nav = document.querySelector('.ap-site-header__mob .ap-mobile-menu'); if (nav && !nav.querySelector('.ap-mobile-menu-login')) { const d = document.createElement('div'); d.className = 'ap-mobile-menu-login'; d.innerHTML = html; nav.appendChild(d); } }, F6_MARKUP); }
  await p.waitForTimeout(1800); return p; }
// F1: /get-a-demo/ testimonial author row fully inside the card at laptop sizes
for (const [w, h] of [[1440, 790], [1440, 900], [1366, 657], [1920, 960], [1024, 768]]) {
  const p = await open('/get-a-demo/', w, h);
  const r = await p.evaluate(() => { const c = document.querySelector('.ap-d26-v1-side .ap-d26-qcard'); if (!c) return null; const cb = c.getBoundingClientRect().bottom; return [...c.querySelectorAll('.ap-d26-qauthor')].every((a) => a.getBoundingClientRect().bottom <= cb + 0.5) && !!document.documentElement.hasAttribute('data-ap-qfit'); });
  ok(`F1 demo card author visible ${w}x${h}`, r === true, r === null ? '(card not found)' : ''); await p.close(); await sleep(3300);
}
// F2: no light 300-weight body text left on the reskinned pages
for (const path of ['/careers/', '/client-reviews/', '/faq/', '/our-company/', '/resource-center/', '/tutorials/']) {
  const p = await open(path, 1440, 900);
  const n = await p.evaluate(() => [...document.querySelectorAll('main p, main li')].filter((e) => e.textContent.trim().length > 20 && getComputedStyle(e).fontWeight === '300').length);
  ok(`F2 body weight 400 ${path}`, n === 0, `${n} light paragraphs`); await p.close(); await sleep(3300);
}
// F3: Education hub logo band on phones: compact and caption on screen
{ const p = await open('/solutions/education/', 390, 844);
  const r = await p.evaluate(() => { const s = document.querySelector('.ap-trust-strip--eduhub .apt-inner'), l = document.querySelector('.ap-trust-strip--eduhub .apt-label'); if (!s || !l) return null; const lb = l.getBoundingClientRect(); return { h: Math.round(s.getBoundingClientRect().height), lx: Math.round(lb.left), lr: Math.round(lb.right) }; });
  ok('F3 education logo band 390', r && r.h <= 220 && r.lx >= 0 && r.lr <= 391, JSON.stringify(r)); await p.close(); await sleep(3300); }
// F4: soft sections are white
for (const path of ['/careers/', '/contact/', '/our-company/', '/solutions/government/a-practical-guide-to-securing-mission-critical-equipment-for-government-agencies/']) {
  const p = await open(path, 1440, 900);
  const bad = await p.evaluate(() => [...document.querySelectorAll('.ap-page-cards--soft, .ap-page-split--soft, .ap-card-grid--soft')].map((e) => getComputedStyle(e).backgroundColor).filter((c) => c !== 'rgb(255, 255, 255)'));
  ok(`F4 soft sections white ${path}`, bad.length === 0, bad.join(',')); await p.close(); await sleep(3300);
}
// F1 also covers /get-a-quote-pro/ (same demo-booking card)
{ const p = await open('/get-a-quote-pro/', 1440, 790);
  const r = await p.evaluate(() => { const c = document.querySelector('.ap-d26-v1-side .ap-d26-qcard'); if (!c) return null; const cb = c.getBoundingClientRect().bottom; return [...c.querySelectorAll('.ap-d26-qauthor')].every((a) => a.getBoundingClientRect().bottom <= cb + 0.5) && document.documentElement.hasAttribute('data-ap-qfit'); });
  ok('F1 quote card author visible /get-a-quote-pro/ 1440x790', r === true, r === null ? '(card not found)' : ''); await p.close(); await sleep(3300); }
// F5: login-splash art fits the phone screen
{ const p = await open('/login-splash-1-a/', 390, 844);
  const r = await p.evaluate(() => Math.max(...[...document.querySelectorAll('.ap-login-splash__art img')].map((i) => i.getBoundingClientRect().right)));
  ok('F5 login-splash art inside 390px', r <= 391, `rightmost ${Math.round(r)}px`); await p.close(); await sleep(3300); }
// F6: phones can log in from the drawer (both logins, visible once the drawer is open)
{ const p = await open('/', 390, 844);
  await p.locator('.ap-site-header__mob .ap-mobile-burger').first().click(); await p.waitForTimeout(700);
  await p.evaluate(() => { const m = document.querySelector('.ap-mobile-menu'); if (m) m.scrollTop = m.scrollHeight; }); await p.waitForTimeout(300);
  const r = await p.evaluate(() => [...document.querySelectorAll('.ap-mobile-menu-login a')].filter((a) => { const b = a.getBoundingClientRect(); return b.width > 100 && b.height >= 40 && b.top >= 0 && b.bottom <= innerHeight; }).map((a) => a.getAttribute('href')));
  ok('F6 phone drawer shows both logins', r.some((h) => /assetpanda\.app/.test(h)) && r.some((h) => /login\.assetpanda\.com/.test(h)), JSON.stringify(r)); await p.close(); await sleep(3300); }
// F7: the four missed CTA classes follow the sitewide button scale (42.8px/14px/800 desktop, 36px/13px phones)
for (const [path, w] of [['/integrations/slack/', 1440], ['/integrations/slack/', 390], ['/contact/', 1440], ['/solutions/asset-management-ot-ga/', 1440]]) {
  const p = await open(path, w, 900);
  const r = await p.evaluate((w) => { const want = w > 1100 ? [42.8, '14px'] : [36, '13px']; const els = [...document.querySelectorAll('.ap-integration-detail__btn, .ap-integration-more__btn, .ap-card-grid__btn, .ap-article-cta__btn')]; return { n: els.length, bad: els.filter((a) => { const cs = getComputedStyle(a); return Math.abs(a.getBoundingClientRect().height - want[0]) > 1 || cs.fontSize !== want[1] || cs.fontWeight !== '800'; }).map((a) => a.textContent.trim().slice(0, 20)) }; }, w);
  ok(`F7 CTA size ${path} @${w}`, r.n > 0 && r.bad.length === 0, JSON.stringify(r)); await p.close(); await sleep(3300);
}
// F8: wrapped gradient phrases restart their ramp on every line (box-decoration-break: clone)
{ const p = await open('/solutions/leased-equipment-tracking/', 1440, 900);
  const r = await p.evaluate(() => { const g = document.querySelector('.ap-solution-hero .epx-g'); if (!g) return null; const cs = getComputedStyle(g); return { lines: g.getClientRects().length, clone: (cs.boxDecorationBreak || cs.webkitBoxDecorationBreak) === 'clone' }; });
  ok('F8 hero gradient per line (leased equipment)', r && r.lines > 1 && r.clone, JSON.stringify(r)); await p.close(); await sleep(3300); }
// F9: the Equipment dashboard stat card fits "Ready for deployment" on one line, clear of the avatar
{ const p = await open('/solutions/equipment-management/', 1440, 900);
  await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important}' });
  const r = await p.evaluate(() => { const sub = [...document.querySelectorAll('.ap-intel-dashboard .epx-tip__sub')].find((e) => /Ready for/.test(e.textContent)); if (!sub) return null; const sr = sub.getBoundingClientRect(); const under = [...document.querySelectorAll('.ap-intel-dashboard .epx-dash__wrap img:not(.epx-dash__table)')].some((im) => { const q = im.getBoundingClientRect(); return Math.min(q.right, sr.right) - Math.max(q.left, sr.left) > 3 && Math.min(q.bottom, sr.bottom) - Math.max(q.top, sr.top) > 3; }); return { lines: Math.round(sr.height / parseFloat(getComputedStyle(sub).lineHeight || 20)), under }; });
  ok('F9 equipment stat card one line, no avatar over text', r && r.lines <= 1 && !r.under, JSON.stringify(r)); await p.close(); await sleep(3300); }
// F10: resource-post buttons follow the CTA scale
for (const w of [1440, 390]) { const p = await open('/resource-center/compare/best-police-asset-management-software/', w, 900);
  const r = await p.evaluate((w) => { const want = w > 1100 ? [42.8, '14px'] : [36, '13px']; const els = [...document.querySelectorAll('.ap26-article .ap26-cta-mini__btn, .ap26-article p.ap-jump a, .ap26-article .wp-block-button__link')]; return { n: els.length, bad: els.filter((a) => Math.abs(a.getBoundingClientRect().height - want[0]) > 1 || getComputedStyle(a).fontSize !== want[1]).length }; }, w);
  ok(`F10 resource buttons @${w}`, r.n > 0 && r.bad === 0, JSON.stringify(r)); await p.close(); await sleep(3300); }
// F11: AIP phone — the stacked cards and the intro sit above the connector lines
{ const p = await open('/asset-intelligence-platform/', 390, 844);
  await p.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 450) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); } }); await p.waitForTimeout(800);
  const r = await p.evaluate(() => { const c = document.querySelector('.ap-asset-scene__card--field'), h = document.querySelector('.ap-asset-scene__head'); if (!c || !h) return null; const cc = getComputedStyle(c), hc = getComputedStyle(h); return { card: cc.position + '/' + cc.zIndex, head: hc.position + '/' + hc.zIndex }; });
  ok('F11 AIP phone cards above wires', r && r.card === 'relative/1' && r.head === 'relative/2', JSON.stringify(r)); await p.close(); await sleep(3300); }
await b.close();
console.log(`QA FIXES ${pass}/${total} ${pass === total ? 'PASSED' : 'FAILED'}`);
