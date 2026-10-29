// After-evidence for leaf-1.3.1 on dev once kit 1.3.106 is live: the phone marquees' duplicate logos and award badges
// and the homepage sector-tab icons load eagerly (no blank copies, no lazy attribute), and the Education picker's
// glows render as soft ellipses. Prints the counts and saves the shots under reports/launch-1028/shots/after-*.
import { DEV, launch, guardPage } from './lib.mjs';
const OUT = 'reports/launch-1028/shots/';
const browser = await launch();
const report = {};
const m = await browser.newPage({ viewport: { width: 390, height: 844 } });
await guardPage(m);
const stat = (page, sel) => page.evaluate((s) => { const a = [...document.querySelectorAll(s)]; return { total: a.length, loaded: a.filter((i) => i.complete && i.naturalWidth > 0).length, lazy: a.filter((i) => i.getAttribute('loading') === 'lazy').length }; }, sel);
await m.goto(DEV + '/solutions/healthcare/biomedical/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await m.waitForTimeout(1500);
report.trustDupAtLoad = await stat(m, 'img.ap-solution-trust__dup');
report.awardsDupAtLoad = await stat(m, 'img.ap-solution-awards__dup');
for (const [name, sel] of [['trust', '.ap-solution-trust'], ['awards', '.ap-solution-awards']]) {
  const el = m.locator(sel).first();
  if (!(await el.count())) { report[name + 'Strip'] = 'not on page'; continue; }
  await el.scrollIntoViewIfNeeded();
  await m.waitForTimeout(1200);
  const b = await el.boundingBox();
  await m.screenshot({ path: OUT + 'after-' + name + '-marquee-390.png', clip: b ? { x: 0, y: Math.max(0, b.y), width: 390, height: Math.max(40, Math.min(b.height, 844 - Math.max(0, b.y))) } : undefined });
}
report.trustDupAfterScroll = await stat(m, 'img.ap-solution-trust__dup');
report.awardsDupAfterScroll = await stat(m, 'img.ap-solution-awards__dup');
await m.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await m.waitForTimeout(1500);
report.sectorTabIconsAtLoad = await stat(m, '.ap-sector-tab img');
const tabs = m.locator('.ap-sector-tabs').first();
if (await tabs.count()) {
  await tabs.scrollIntoViewIfNeeded();
  await m.waitForTimeout(1000);
  report.sectorTabIconsAfterScroll = await stat(m, '.ap-sector-tab img');
  const b = await tabs.boundingBox();
  await m.screenshot({ path: OUT + 'after-sector-tabs-390.png', clip: b ? { x: 0, y: Math.max(0, b.y - 10), width: 390, height: Math.max(40, Math.min(b.height + 20, 844 - Math.max(0, b.y - 10))) } : undefined });
} else report.sectorTabs = 'not on page';
await m.close();
const d = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(d);
await d.goto(DEV + '/solutions/education/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
const picker = d.locator('.ap-link-cards--eduhub').first();
if (await picker.count()) {
  await picker.scrollIntoViewIfNeeded();
  await d.waitForTimeout(1500);
  report.eduOrbs = await d.evaluate(() => [...document.querySelectorAll('.ap-link-cards--eduhub .apk-orb')].map((o) => { const cs = getComputedStyle(o); return cs.backgroundImage.slice(0, 48) + ' | filter ' + cs.filter + ' | display ' + cs.display; }));
  await picker.screenshot({ path: OUT + 'after-edu-picker-1440.png' });
} else report.eduPicker = 'not found';
// Government hub picker: switched from the default (40% photo under a white fade) to the hub26 full-strength look.
await d.goto(DEV + '/solutions/government/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
const gov = d.locator('.ap-link-cards--hub26').first();
if (await gov.count()) {
  await gov.scrollIntoViewIfNeeded();
  await d.waitForTimeout(1500);
  report.govPicker = await d.evaluate(() => { const a = [...document.querySelectorAll('.ap-link-cards--hub26 .apg-pick-bg img')]; return { cards: a.length, loaded: a.filter((i) => i.complete && i.naturalWidth > 0).length, opacity: a.map((i) => getComputedStyle(i).opacity).join(',') }; });
  await gov.screenshot({ path: OUT + 'after-gov-picker-1440.png' });
} else report.govPicker = 'hub26 picker not found';
await d.close();
await browser.close();
console.log(JSON.stringify(report, null, 1));
const full = (s) => s && s.total > 0 && s.loaded === s.total && s.lazy === 0;
const ok = full(report.trustDupAfterScroll) && full(report.awardsDupAfterScroll) && full(report.sectorTabIconsAfterScroll || report.sectorTabIconsAtLoad) && Array.isArray(report.eduOrbs) && report.eduOrbs.length > 0 && report.eduOrbs.every((o) => /closest-side/.test(o));
console.log(ok ? 'AFTER SHOTS PASSED' : 'AFTER SHOTS FAILED');
