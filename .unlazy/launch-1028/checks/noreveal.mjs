// Section reveals off on the solution/hub/product/Ursa page families (contract C25). On each listed page:
// html.ap-noreveal present, zero [data-ap-reveal] left, no element animating on a scroll timeline, and every
// section element visible (opacity 1). Positive control: the homepage keeps html.ap-motion without ap-noreveal and
// still has reveals (so an absent attribute on the other pages means stripped, not never-rendered).
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const PAGES = ['/solutions/education/', '/solutions/calibration-management/', '/products/asset-panda-for-local-government/', '/ursa-ai/', '/solutions/help-desk-ticketing/'];
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const probe = async (path) => {
  const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1500);
  const r = await p.evaluate(() => {
    const all = [...document.querySelectorAll('main *, .entry-content *')];
    const scrollLinked = all.filter((e) => { const t = getComputedStyle(e).animationTimeline || ''; return t && t !== 'auto' && t !== 'none'; });
    const hidden = all.filter((e) => /__rev|epx-drev|sp-prev/.test(e.className || '') && parseFloat(getComputedStyle(e).opacity) < 0.3);
    return { noreveal: document.documentElement.classList.contains('ap-noreveal'), motion: document.documentElement.classList.contains('ap-motion'), reveals: document.querySelectorAll('[data-ap-reveal]').length, scrollLinked: scrollLinked.length, scrollSample: scrollLinked.slice(0, 3).map((e) => e.className.toString().slice(0, 60)), hidden: hidden.length, hiddenSample: hidden.slice(0, 3).map((e) => e.className.toString().slice(0, 60) + ' op=' + getComputedStyle(e).opacity) };
  });
  await p.close();
  return r;
};
for (const path of PAGES) {
  const r = await probe(path);
  ok(r.noreveal, path + ' html.ap-noreveal set');
  ok(r.reveals === 0, path + ' no data-ap-reveal left', 'count=' + r.reveals);
  ok(r.scrollLinked === 0, path + ' no scroll-linked animation', 'count=' + r.scrollLinked + ' ' + JSON.stringify(r.scrollSample));
  ok(r.hidden === 0, path + ' no reveal element left in its hidden pre-state (opacity < .3; some rest dimmed by design, e.g. the dashboard table at .4)', 'count=' + r.hidden + ' ' + JSON.stringify(r.hiddenSample));
  await sleep(3500);
}
const home = await probe('/');
ok(!home.noreveal && home.motion && home.reveals > 0, 'homepage control keeps its reveals', 'noreveal=' + home.noreveal + ' motion=' + home.motion + ' reveals=' + home.reveals);
await browser.close();
if (problems.length) { console.log('NOREVEAL FAILED: ' + problems.length); process.exit(1); }
console.log('NOREVEAL PASSED pages=' + PAGES.length);
