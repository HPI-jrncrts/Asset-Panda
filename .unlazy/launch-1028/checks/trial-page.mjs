// /start-a-free-trial-email-only/ (2406) after the reskin (kit 1.3.135): at 1440 and 390 the banner hero carries the
// trial form wired to the developer embed (portal 4155061, form 2418c021…, region na2), the seven sections render in
// order, nothing overflows sideways and no image is broken. Saves full-page captures. Never submits the form.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const OUT = 'reports/launch-1028/shots/trial'; fs.mkdirSync(OUT, { recursive: true });
const problems = []; const ok = (c, l, d) => { console.log((c ? 'PASS ' : 'FAIL ') + l + (d ? ' — ' + d : '')); if (!c) problems.push(l); };
const browser = await launch();
for (const width of [1440, 390]) {
  const p = await browser.newPage({ viewport: { width, height: 900 } }); await guardPage(p);
  await p.goto(DEV + '/start-a-free-trial-email-only/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  const r = await p.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((res) => setTimeout(res, 120)); }
    window.scrollTo(0, 0); await new Promise((res) => setTimeout(res, 500));
    const html = document.documentElement.outerHTML;
    const secs = [...document.querySelectorAll('section[class^="ap-"]')].filter((s) => !s.parentElement.closest('section[class^="ap-"]') && !s.closest('.ap-site-footer')).map((s) => s.className.split(' ')[0]);
    return { banner: !!document.querySelector('.ap-form-hero--banner'), dev: /forms\/embed\/developer\/4155061\.js/.test(html), form: html.includes('2418c021-1507-4be8-97fc-81f6b2f49b89'), secs, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth, broken: [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 0).map((i) => i.currentSrc.split('/').pop()).slice(0, 3) };
  });
  ok(r.banner && r.dev && r.form, width + ': banner hero with the trial form on the developer embed', JSON.stringify({ banner: r.banner, dev: r.dev, form: r.form }));
  ok(r.secs.length >= 7, width + ': the seven sections render', r.secs.join(' '));
  ok(!r.overflow && !r.broken.length, width + ': no sideways overflow, no broken image', JSON.stringify(r.broken));
  await p.screenshot({ path: OUT + '/trial-' + width + '.png', fullPage: true });
  await p.close(); await sleep(3300);
}
await browser.close();
if (problems.length) { console.log('TRIAL PAGE FAILED: ' + problems.length); process.exit(1); }
console.log('TRIAL PAGE PASSED');
