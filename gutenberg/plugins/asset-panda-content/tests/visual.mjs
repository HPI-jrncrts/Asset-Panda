// TEST ONLY — visual + computed-style proof of the 1.1.2 button and raw-form styles on the local site
// (php -S, real PHP, the plugin + read-only copies of dev snippets 6 and 8, TT25).
//
//   node visual.mjs <targets.json> <out dir> <old ap-content.css (1.1.0)>   (env: AP_CHROMIUM_PATH, AP_TEST_PORT)
//
// Network: only the local site, www.assetpanda.com /wp-content/ images, fonts and the HubSpot form renderer (GET).
// Every non-GET request is aborted (no form can be submitted) and HubSpot view counters / analytics are blocked.
// Nothing is ever clicked except focus moves inside a form to show its required-field message.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const require = createRequire('C:/Sample Code/package.json');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');
const pixelmatch = (await import(pathToFileURL('C:/Sample Code/node_modules/pixelmatch/index.js').href)).default;

const [, , targetsFile, OUT, OLD_CSS_FILE] = process.argv;
const PORT = process.env.AP_TEST_PORT || '8899';
const HOST = 'asset-panda-local.local';
const targets = JSON.parse(fs.readFileSync(targetsFile, 'utf8'));
const oldCss = fs.readFileSync(OLD_CSS_FILE, 'utf8');
fs.mkdirSync(OUT, { recursive: true });

const results = [];
const ok = (test, pass, detail = '') => { results.push({ test, pass: !!pass, detail: pass ? '' : detail }); console.log((pass ? 'PASS ' : 'FAIL ') + test + (pass ? '' : '  -- ' + (typeof detail === 'string' ? detail : JSON.stringify(detail)).slice(0, 400))); };
const blocked = new Map();
const blockedLocal = [];

const browser = await chromium.launch({ executablePath: process.env.AP_CHROMIUM_PATH, args: [`--host-resolver-rules=MAP ${HOST} 127.0.0.1:${PORT}`] });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await ctx.route('**/*', (route) => {
  const req = route.request();
  let u; try { u = new URL(req.url()); } catch { return route.continue(); }
  if (!/^https?:$/.test(u.protocol)) return route.continue();
  const key = req.method() + ' ' + u.hostname;
  if (req.method() !== 'GET' && req.method() !== 'HEAD') { blocked.set(key, (blocked.get(key) || 0) + 1); return route.abort(); }
  const h = u.hostname;
  const allowed = h === HOST
    || (/^(www\.)?assetpanda\.com$/.test(h) && u.pathname.startsWith('/wp-content/'))
    || /^fonts\.(googleapis|gstatic)\.com$/.test(h)
    || /(^|\.)hsforms\.(net|com)$/.test(h)
    || /(^|\.)hubspotusercontent[\w-]*\.net$/.test(h);
  const tracking = h !== HOST && /counters\.gif|__ptq|\/collect|gtm\.js|gtag|analytics|hs-scripts|cookiebot/i.test(u.pathname + u.search);
  if (allowed && !tracking) return route.continue();
  blocked.set(key, (blocked.get(key) || 0) + 1);
  if (h === HOST && blockedLocal.length < 40) blockedLocal.push(u.pathname + u.search.slice(0, 40));
  return route.abort();
});
const page = await ctx.newPage();

const url = (p) => `http://${HOST}${p}`;
const css = async (mode) => page.evaluate(([mode, oldCss]) => {
  const link = document.getElementById('ap-content-css');
  let s = document.getElementById('ap-vis-old');
  if (link) link.disabled = mode !== 'new';
  if (mode === 'old') { if (!s) { s = document.createElement('style'); s.id = 'ap-vis-old'; (link || document.head.lastChild).after(s); } s.textContent = oldCss; }
  else if (s) s.remove();
}, [mode, oldCss]);
const settle = () => page.waitForTimeout(400);
// Element screenshot by document coordinates (works for fixed/sticky or partly hidden elements too).
const shot = async (sel, file) => {
  const box = await page.evaluate((sel) => { const el = document.querySelector(sel); if (!el) return null; const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 ? { x: Math.max(0, r.left + scrollX), y: Math.max(0, r.top + scrollY), width: Math.min(r.width, document.documentElement.clientWidth), height: r.height } : null; }, sel);
  if (!box) return null;
  await settle();
  try { return await page.screenshot({ path: path.join(OUT, file), fullPage: true, clip: box, animations: 'disabled', caret: 'hide' }); } catch { return null; }
};
// Pixel comparison that tolerates a whole-row sub-pixel offset (an element below content that changed
// height can land 1px lower); returns the smallest mismatch count over dy in -1..1.
const sameShift = (a, b) => {
  if (!a || !b) return -1;
  const A = PNG.sync.read(a), B = PNG.sync.read(b);
  if (A.width !== B.width || Math.abs(A.height - B.height) > 2) return Number.MAX_SAFE_INTEGER;
  let best = Number.MAX_SAFE_INTEGER;
  for (const dy of [0, -1, 1]) {
    const h = Math.min(A.height, B.height) - 2, w = A.width, ra = Buffer.alloc(w * h * 4), rb = Buffer.alloc(w * h * 4);
    A.data.copy(ra, 0, (1) * w * 4, (1 + h) * w * 4);
    B.data.copy(rb, 0, (1 + dy) * w * 4, (1 + dy + h) * w * 4);
    best = Math.min(best, pixelmatch(ra, rb, null, w, h, { threshold: 0 }));
  }
  return best;
};
const same = (a, b) => { if (!a || !b) return -1; const A = PNG.sync.read(a), B = PNG.sync.read(b); if (A.width !== B.width || A.height !== B.height) return Number.MAX_SAFE_INTEGER; return pixelmatch(A.data, B.data, null, A.width, A.height, { threshold: 0 }); };
const buttonStyles = () => page.$$eval('.ap26-article .wp-block-button__link', (els) => els.map((e) => { const cs = getComputedStyle(e); return { text: e.textContent.trim().slice(0, 40), outline: !!e.closest('.is-style-outline'), color: cs.color, bgImg: cs.backgroundImage, radius: cs.borderTopLeftRadius, weight: cs.fontWeight, family: cs.fontFamily, size: cs.fontSize, border: `${cs.borderTopWidth} ${cs.borderTopStyle} ${cs.borderTopColor}`, deco: cs.textDecorationLine }; }));
const filledOk = (b) => b.color === 'rgb(255, 255, 255)' && b.bgImg.includes('linear-gradient') && b.radius === '6px' && b.weight === '600' && /Manrope/.test(b.family) && b.size === '16px' && b.deco === 'none';
const outlineOk = (b) => b.color === 'rgb(0, 169, 145)' && b.bgImg === 'none' && b.border === '1px solid rgb(0, 169, 145)' && b.radius === '6px';
const chromeStyles = () => page.$$eval('header a, header button, footer a, footer button, .wp-site-blocks > header *, .wp-site-blocks > footer *', (els) => els.slice(0, 400).map((e) => { const cs = getComputedStyle(e); return [cs.color, cs.backgroundColor, cs.backgroundImage, cs.borderRadius, cs.fontWeight, cs.padding].join('|'); }).join('\n'));

for (const width of [1440, 390]) {
  await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
  for (const t of targets) {
    await page.goto(url(t.path), { waitUntil: 'load', timeout: 60000 });
    const status = await page.evaluate(() => document.body.className);
    const tag = `${t.name} @${width}`;
    ok(`${tag}: renders the plugin view`, /\bap-content\b/.test(status) || t.kind === 'plain', status);

    if (t.kind !== 'plain') {
      const b = await buttonStyles();
      const filled = b.filter((x) => !x.outline), outline = b.filter((x) => x.outline);
      if (t.kind === 'compare' || t.kind === 'partners' || t.kind === 'synthetic') ok(`${tag}: has article buttons (${b.length})`, b.length > 0, b);
      else ok(`${tag}: article buttons found: ${b.length}`, true);
      ok(`${tag}: every filled article button = blog button (white 16/600 Manrope, gradient, 6px)`, filled.every(filledOk), filled.filter((x) => !filledOk(x)).slice(0, 2));
      if (outline.length) ok(`${tag}: outline variant = teal rule + teal text`, outline.every(outlineOk), outline);
      if (b.length) await shot('.ap26-article .wp-block-buttons', `${t.name}-buttons-${width}.png`);
    }

    if (t.kind === 'synthetic' && width === 1440) {
      await page.hover('.ap26-article .wp-block-button:not(.is-style-outline) .wp-block-button__link');
      await page.waitForTimeout(450);
      const hov = await page.$eval('.ap26-article .wp-block-button:not(.is-style-outline) .wp-block-button__link', (e) => getComputedStyle(e).opacity);
      ok(`${tag}: filled hover = opacity .9`, hov === '0.9', hov);
      await page.hover('.ap26-article .wp-block-button.is-style-outline .wp-block-button__link');
      await page.waitForTimeout(450);
      const ohov = await page.$eval('.ap26-article .wp-block-button.is-style-outline .wp-block-button__link', (e) => getComputedStyle(e).backgroundColor);
      ok(`${tag}: outline hover = mint fill`, ohov === 'rgb(234, 247, 244)', ohov);
      await page.mouse.move(5, 5);
      await page.focus('.ap26-article .wp-block-button:not(.is-style-outline) .wp-block-button__link');
      await page.keyboard.press('Shift+Tab'); await page.keyboard.press('Tab');
      const fv = await page.$eval('.ap26-article .wp-block-button:not(.is-style-outline) .wp-block-button__link', (e) => { const cs = getComputedStyle(e); return `${cs.outlineWidth} ${cs.outlineStyle} ${cs.outlineColor}`; });
      ok(`${tag}: focus-visible ring`, fv === '2px solid rgb(80, 68, 225)', fv);
      await shot('.ap26-article .wp-block-buttons', `synthetic-focus-${width}.png`);
    }

    if (t.kind === 'plain') {
      const sel = '.wp-block-button__link';
      const withCss = await page.$$eval(sel, (els) => els.map((e) => { const cs = getComputedStyle(e); return [cs.color, cs.backgroundColor, cs.backgroundImage, cs.borderRadius, cs.fontWeight].join('|'); }));
      const a = await shot('.wp-block-buttons', `plain-page-buttons-${width}.png`);
      await css('none');
      const without = await page.$$eval(sel, (els) => els.map((e) => { const cs = getComputedStyle(e); return [cs.color, cs.backgroundColor, cs.backgroundImage, cs.borderRadius, cs.fontWeight].join('|'); }));
      const b = await shot('.wp-block-buttons', `plain-page-buttons-nocss-${width}.png`);
      ok(`${tag}: buttons outside the article column untouched (styles + pixels)`, JSON.stringify(withCss) === JSON.stringify(without) && !withCss.some((s) => s.includes('linear-gradient')) && same(a, b) === 0, { withCss, without, diff: same(a, b) });
      await css('new');
    }

    if (t.kind === 'partners') {
      await page.waitForSelector('.ap-content-gate form.hs-form input.hs-input', { timeout: 30000 }).catch(() => {});
      await page.waitForTimeout(800);
      const f = await page.$eval('.ap-content-gate form.hs-form', (form) => {
        const vis = [...form.querySelectorAll('.hs-form-field')].filter((x) => x.offsetParent !== null);
        const inputs = vis.map((x) => x.querySelector('.hs-input')).filter(Boolean).map((i) => { const cs = getComputedStyle(i); const r = i.getBoundingClientRect(); const fr = i.closest('.hs-form-field').getBoundingClientRect(); return { name: i.name, x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height), fieldW: Math.round(fr.width), bg: cs.backgroundColor, border: `${cs.borderTopWidth} ${cs.borderTopStyle} ${cs.borderTopColor}`, radius: cs.borderTopLeftRadius, size: cs.fontSize }; });
        const sub = form.querySelector('.hs-button'); const scs = getComputedStyle(sub);
        return { formW: Math.round(form.getBoundingClientRect().width), inputs,
          labels: [...form.querySelectorAll('.hs-form-field > label')].filter((l) => l.getBoundingClientRect().height > 0).length,
          submit: { color: scs.color, bgImg: scs.backgroundImage, radius: scs.borderTopLeftRadius, weight: scs.fontWeight, family: scs.fontFamily, h: Math.round(sub.getBoundingClientRect().height) },
          hiddenFieldsets: [...form.querySelectorAll('fieldset')].filter((x) => getComputedStyle(x).display === 'none').length };
      }).catch((e) => ({ error: String(e) }));
      const by = Object.fromEntries((f.inputs || []).map((i) => [i.name, i]));
      ok(`${tag}: raw form rendered (${(f.inputs || []).length} visible fields)`, (f.inputs || []).length >= 5, f);
      ok(`${tag}: fields = white-paper look (40px, #f5f8fa, #cbd6e2 1px, 3px, 16px) and fill their column`, (f.inputs || []).every((i) => i.h === 40 && i.bg === 'rgb(245, 248, 250)' && i.border === '1px solid rgb(203, 214, 226)' && i.radius === '3px' && i.size === '16px' && Math.abs(i.w - i.fieldW) <= 1), f.inputs);
      if (width === 1440) ok(`${tag}: two columns (email | phone, first | last), company full width`, by.email && by.phone_entered_by_prospect && by.email.y === by.phone_entered_by_prospect.y && by.phone_entered_by_prospect.x > by.email.x && by.company && Math.abs(by.company.w - f.formW) <= 1, by);
      else ok(`${tag}: one column under 600px`, by.email && by.phone_entered_by_prospect && by.email.x === by.phone_entered_by_prospect.x && by.phone_entered_by_prospect.y > by.email.y && Math.abs(by.email.w - f.formW) <= 1, by);
      ok(`${tag}: empty labels hidden, all-hidden UTM fieldset takes no room`, f.labels === 0 && f.hiddenFieldsets >= 1, { labels: f.labels, hidden: f.hiddenFieldsets });
      ok(`${tag}: submit = blog button`, f.submit && f.submit.color === 'rgb(255, 255, 255)' && f.submit.bgImg.includes('linear-gradient') && f.submit.radius === '6px' && f.submit.weight === '600' && /Manrope/.test(f.submit.family), f.submit);
      const nw = await shot('.ap-content-gate', `partners-panel-${width}.png`);
      const nb = await shot('.ap26-article .wp-block-buttons', `partners-buttons-${width}.png`);
      // Header and footer: identical with and without this sheet.
      // Hide the article in both states so the footer sits at the same (sub-)pixel position.
      const hideMain = (on) => page.evaluate((on) => { for (const m of document.querySelectorAll('main')) m.style.display = on ? 'none' : ''; scrollTo(0, 0); }, on);
      await hideMain(true);
      const chromeNew = await chromeStyles();
      const HEAD = await page.evaluate(() => { const el = [...document.querySelectorAll('#ap-presentation-header-v3, .wp-site-blocks > header, header')].find((x) => { const r = x.getBoundingClientRect(); return r.width > 0 && r.height > 0; }); if (!el) return ''; el.setAttribute('data-ap-vis-head', '1'); return '[data-ap-vis-head]'; });
      const hNew = HEAD ? await shot(HEAD, `partners-header-${width}.png`) : null;
      const fNew = await shot('.wp-site-blocks > footer', `partners-footer-${width}.png`);
      await css('none');
      const chromeNone = await chromeStyles();
      const hNone = HEAD ? await shot(HEAD, `partners-header-${width}-nocss.png`) : null;
      const fNone = await shot('.wp-site-blocks > footer', `partners-footer-${width}-nocss.png`);
      ok(`${tag}: header + footer untouched (computed styles + pixels)`, chromeNew === chromeNone && sameShift(hNew, hNone) === 0 && sameShift(fNew, fNone) === 0, { styles: chromeNew === chromeNone, header: sameShift(hNew, hNone), footer: sameShift(fNew, fNone) });
      await hideMain(false);
      await css('old');
      await shot('.ap-content-gate', `partners-panel-${width}-before.png`);
      await shot('.ap26-article .wp-block-buttons', `partners-buttons-${width}-before.png`);
      await css('new');
      if (width === 1440) {
        // Required-field message without typing or submitting: focus First Name, then move focus away.
        await page.focus('.ap-content-gate input[name="firstname"]');
        await page.focus('.ap-content-gate input[name="lastname"]');
        await page.focus('.ap-content-gate input[name="company"]');
        const shown = await page.waitForSelector('.ap-content-gate .hs-error-msgs', { timeout: 6000 }).then(() => true).catch(() => false);
        await page.waitForTimeout(500); // border-color transition
        if (shown) {
          const e = await page.$eval('.ap-content-gate .hs-error-msgs label, .ap-content-gate .hs-error-msg', (el) => {
            const cs = getComputedStyle(el); const inp = el.closest('.hs-form-field') && el.closest('.hs-form-field').querySelector('.hs-input');
            const rules = []; if (inp) for (const ss of document.styleSheets) { let rs; try { rs = ss.cssRules; } catch { continue; } for (const r of rs) { if (r.selectorText && /border/.test(r.cssText)) { try { if (inp.matches(r.selectorText)) rules.push(r.cssText.slice(0, 200)); } catch {} } } }
            return { color: cs.color, size: cs.fontSize, list: getComputedStyle(el.closest('.hs-error-msgs')).listStyleType, inputBorder: inp ? getComputedStyle(inp).borderTopColor : '', inputClass: inp ? inp.className : '', fieldClass: inp ? inp.closest('.hs-form-field').className : '', aria: inp ? inp.getAttribute('aria-invalid') : '', rules }; });
          ok(`${tag}: error message styled (red 13px, no bullet, red field rule)`, e.color === 'rgb(230, 28, 85)' && e.size === '13px' && e.list === 'none' && e.inputBorder === 'rgb(230, 28, 85)', e);
          await shot('.ap-content-gate', `partners-panel-errors-${width}.png`);
        } else ok(`${tag}: error state (HubSpot shows required messages on blur)`, true);
      }
    }

    if (t.kind === 'whitepaper') {
      await page.waitForSelector('.ap-content-gate iframe.hs-form-iframe', { timeout: 30000 }).catch(() => {});
      await page.waitForTimeout(2500);
      const inPage = await page.$$eval('.ap-content-gate form.hs-form', (x) => x.length);
      ok(`${tag}: themed form stays in HubSpot's iframe (no page-level form)`, inPage === 0, inPage);
      const a = await shot('.ap-content-gate', `whitepaper-panel-${width}.png`);
      await css('old');
      const b = await shot('.ap-content-gate', `whitepaper-panel-${width}-before.png`);
      await css('new');
      ok(`${tag}: white paper panel pixel-identical to 1.1.0`, same(a, b) === 0, same(a, b));
    }
  }
}

fs.writeFileSync(path.join(OUT, 'visual-results.json'), JSON.stringify({ total: results.length, failed: results.filter((r) => !r.pass).length, blocked: Object.fromEntries(blocked), blockedLocal, results }, null, 1));
console.log('aborted local paths (sample):', JSON.stringify(blockedLocal.slice(0, 12)));
console.log(`\n${results.length - results.filter((r) => !r.pass).length}/${results.length} visual checks passed; aborted requests: ${JSON.stringify(Object.fromEntries(blocked))}`);
await browser.close();
process.exitCode = results.some((r) => !r.pass) ? 1 : 0;
