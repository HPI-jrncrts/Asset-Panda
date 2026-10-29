// The Who We Help mega menu on dev lists exactly Figma's seven categories in order, every item link is
// same-site and answers 200 without redirecting, every panel image loads, and the phone menu shows the
// same seven groups.
import { DEV, launch, guardPage, head, sleep } from './lib.mjs';
const EXPECT = ['Public Sector', 'Education', 'Healthcare', 'Industry & Field Operations', 'Energy & Utilities', 'Transportation & Logistics', 'Business & Nonprofit'];
const norm = (s) => s.replace(/\s+/g, ' ').replace(/&amp;/g, '&').trim();
const browser = await launch();
const problems = [];
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
const top = page.locator('.ap-nav-item', { hasText: 'Who We Help' }).first();
await top.hover();
await page.waitForTimeout(800);
const tabCount = await page.locator('.ap-nav-item:has-text("Who We Help") .ap-help-tab').count();
const data = { tabs: [], links: [], imgs: [], visible: false };
// Walk every category: hover its tab (icons in hidden tabs load lazily), then read the active panel.
for (let i = 0; i < tabCount; i++) {
  const tab = page.locator('.ap-nav-item:has-text("Who We Help") .ap-help-tab').nth(i);
  await tab.hover().catch(() => {});
  // Icons in a tab load lazily on first hover: give the shown panel's images up to 8 s to finish,
  // and for any that still fail, fetch them once more to report the HTTP status (a 429 is the
  // Cloudflare rate limit, a 404 a missing file).
  await page.waitForFunction(() => { const item = [...document.querySelectorAll('.ap-nav-item')].find((n) => /Who We Help/i.test(n.textContent)); const shown = item ? [...item.querySelectorAll('.ap-help-panel')].filter((p) => p.offsetHeight > 0) : []; return shown.every((p) => [...p.querySelectorAll('img')].every((im) => im.complete)); }, null, { timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(300);
  const d = await page.evaluate((idx) => {
    const item = [...document.querySelectorAll('.ap-nav-item')].find((n) => /Who We Help/i.test(n.textContent));
    const panel = item && item.querySelector('.ap-mega-panel');
    if (!panel) return null;
    const tabs = [...panel.querySelectorAll('.ap-help-tab')];
    const name = tabs[idx].textContent.replace(/\s+/g, ' ').replace(/[›>»]+\s*$/, '').trim();
    const shown = [...panel.querySelectorAll('.ap-help-panel')].filter((p) => p.offsetHeight > 0 && getComputedStyle(p).visibility !== 'hidden');
    const links = shown.flatMap((p) => [...p.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')));
    const imgs = shown.flatMap((p) => [...p.querySelectorAll('img')].filter((im) => im.getBoundingClientRect().width > 0).map((im) => [im.currentSrc || im.src, im.complete && im.naturalWidth > 0]));
    return { name, links, imgs, visible: getComputedStyle(panel).display !== 'none' && panel.offsetHeight > 0 };
  }, i);
  if (!d) { data.tabs = null; break; }
  data.tabs.push(d.name); data.links.push(...d.links); data.imgs.push(...d.imgs); data.visible = data.visible || d.visible;
}
if (!tabCount || data.tabs === null) problems.push('Who We Help panel not found');
else {
  if (!data.visible) problems.push('panel not visible on hover');
  if (JSON.stringify(data.tabs.map(norm)) !== JSON.stringify(EXPECT)) problems.push('categories ' + JSON.stringify(data.tabs) + ' expected ' + JSON.stringify(EXPECT));
  for (const [src, ok] of data.imgs) {
    if (ok) continue;
    const status = await page.evaluate(async (u) => { try { const r = await fetch(u, { cache: 'reload' }); return r.status; } catch (e) { return 'error'; } }, src).catch(() => 'error');
    problems.push('panel image did not load (HTTP ' + status + ') ' + src.replace(/^https?:\/\/[^/]+/, '').slice(0, 90));
  }
  const seen = new Set();
  for (let href of data.links) {
    if (/^(mailto:|tel:|#)/.test(href)) continue;
    if (href.startsWith('/')) href = DEV + href;
    if (!href.startsWith(DEV)) { problems.push('off-site link ' + href.slice(0, 90)); continue; }
    if (seen.has(href)) continue; seen.add(href);
    const s = await head(href);
    if (s !== 200) problems.push('link ' + s + ' ' + href.replace(DEV, ''));
    await sleep(600);
  }
}
await page.close();
const m = await browser.newPage({ viewport: { width: 390, height: 800 } });
await guardPage(m);
await m.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await m.locator('.ap-mobile-burger').first().click();
await m.waitForTimeout(600);
const mobile = await m.evaluate(() => (document.querySelector('.ap-mobile-menu') || document.body).textContent.replace(/\s+/g, ' '));
for (const c of EXPECT) if (!mobile.includes(c)) problems.push('phone menu missing ' + c);
await m.close();
await browser.close();
for (const x of problems) console.log('PROBLEM ' + x);
if (problems.length) { console.log('MEGAMENU CHECK FAILED'); process.exit(1); }
console.log('MEGAMENU CHECK PASSED categories=' + EXPECT.length);
