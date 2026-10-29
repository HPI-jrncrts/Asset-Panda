// Survey for Joseph's late 2026-10-29 notes, at 1440 on every solution / product / platform page:
//   hero: the gap between the hero copy's text and its art (union of the visible art images, minus full-width
//         background layers) — negative = the art runs into the copy;
//   closing: the last kit section before the footer, and whether it is a dark gradient card (the homepage's blue box);
//   button: the closing section's primary button size and type, beside the homepage's closing button.
// Writes reports/launch-1028/hero-cta-survey.json and prints one line per page. Read-only.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep, flag } from './lib.mjs';
const all = JSON.parse(fs.readFileSync('.unlazy/launch-1028/pages.json', 'utf8'));
const extra = ['/', '/barcode-labels/', '/asset-intelligence-platform/', '/ursa-ai/', '/partners/', '/compare/', '/roi-calculator/', '/integrations/'];
const paths = [...new Set(extra.concat(all.filter((p) => /^\/(solutions|products)\//.test(p))))];
const browser = await launch();
const rows = [];
for (const path of paths) {
  const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(p);
  try {
    await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await p.waitForTimeout(1600);
    const r = await p.evaluate(() => {
      const vw = innerWidth;
      const hero = document.querySelector('.ap-vhero, .ap-solution-hero, .ap-orbit-hero, .ap-assist-hero, .ap-readiness-hero, .ap-page-hero, .ap-compare-hero, .ap-roi-hero, .ap-integration-hero');
      let gap = null, heroCls = hero ? hero.className.split(' ')[0] : null, textR = null, artL = null;
      if (hero) {
        const copy = hero.querySelector('[class*="__content"], [class*="__copy"]');
        const art = hero.querySelector('[class*="__visual"], [class*="__stage"], [class*="__media"]');
        if (copy && art) {
          const texts = [...copy.querySelectorAll('h1, h2, p, a, .ap-btn, [class*="__eyebrow"], [class*="__badges"] img')].filter((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; });
          // text extent: a heading/paragraph box can be wider than its text, so measure the text itself via a Range
          let right = -1e9;
          for (const e of texts) { const rg = document.createRange(); rg.selectNodeContents(e); for (const rr of rg.getClientRects()) if (rr.width > 0) right = Math.max(right, rr.right); }
          const imgs = [...art.querySelectorAll('img, picture, svg, [class*="__card"], [class*="__float"], [class*="__chip"]')].filter((e) => { const r = e.getBoundingClientRect(); const cs = getComputedStyle(e); return r.width > 8 && r.height > 8 && r.width < vw * 0.62 && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05 && cs.display !== 'none' && r.right > 0 && r.left < vw; });
          let left = 1e9; for (const e of imgs) left = Math.min(left, e.getBoundingClientRect().left);
          if (right > -1e9 && left < 1e9) { textR = Math.round(right); artL = Math.round(left); gap = Math.round(left - right); }
        }
      }
      const foot = document.querySelector('.ap-site-footer');
      let closing = null, card = null, btn = null;
      if (foot) {
        const secs = [...document.querySelectorAll('main section[class^="ap-"], .wp-site-blocks section[class^="ap-"], section[class^="ap-"]')].filter((s) => !s.closest('.ap-site-footer') && !s.closest('header') && (s.compareDocumentPosition(foot) & Node.DOCUMENT_POSITION_FOLLOWING));
        const last = secs[secs.length - 1];
        if (last) {
          closing = last.className.split(' ').slice(0, 2).join(' ');
          card = [...last.querySelectorAll('*')].concat([last]).some((e) => { const cs = getComputedStyle(e); const r = e.getBoundingClientRect(); return r.width > 600 && r.height > 200 && /linear-gradient/.test(cs.backgroundImage) && /\(\s*(3[0-9]|[0-9]{1,2})\s*,|rgb\((3[0-9]|[0-9]{1,2}|1[0-1][0-9]),/.test(cs.backgroundImage) ; });
          const b = last.querySelector('a.ap-btn, a.epx-btn, a[class*="btn"], a[class*="__cta"], a[class*="button"]');
          if (b) { const cs = getComputedStyle(b); const rr = b.getBoundingClientRect(); btn = { text: b.textContent.trim().slice(0, 20), w: Math.round(rr.width), h: Math.round(rr.height), fs: cs.fontSize, fw: cs.fontWeight, tt: cs.textTransform, pad: cs.padding, cls: b.className.split(' ').slice(0, 2).join(' ') }; }
        }
      }
      return { heroCls, textR, artL, gap, closing, card, btn };
    });
    rows.push({ path, ...r });
    console.log(path.padEnd(58) + ' hero ' + String(r.heroCls).padEnd(20) + ' gap ' + String(r.gap).padStart(5) + ' | close ' + String(r.closing).slice(0, 34).padEnd(34) + ' card ' + (r.card ? 'Y' : 'n') + ' | btn ' + (r.btn ? r.btn.w + 'x' + r.btn.h + ' ' + r.btn.fs + '/' + r.btn.fw + ' ' + r.btn.tt + ' "' + r.btn.text + '"' : '-'));
  } catch (e) { console.log(path + ' ERROR ' + String(e).slice(0, 80)); }
  await p.close(); await sleep(3300);
}
await browser.close();
fs.writeFileSync('reports/launch-1028/hero-cta-survey.json', JSON.stringify(rows, null, 1));
console.log('SURVEY DONE ' + rows.length);
