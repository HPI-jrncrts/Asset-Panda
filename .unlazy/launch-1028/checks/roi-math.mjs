// The ROI calculator on dev reproduces Figma's figures for Figma's inputs (10 people, $50/hr, $500K assets,
// 10 hrs/week → 364 hrs, $18,200, $10,000, $28,800 total) and recomputes when a slider moves.
import { DEV, launch, guardPage } from './lib.mjs';
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
await page.goto(DEV + '/roi-calculator/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1200);
const r = await page.evaluate(() => {
  const sliders = [...document.querySelectorAll('input[type="range"]')];
  const set = (el, v) => { el.value = String(v); el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); };
  if (sliders.length < 4) return { sliders: sliders.length };
  const vals = [10, 50, 500000, 10];
  sliders.slice(0, 4).forEach((s, i) => set(s, vals[i]));
  const text1 = document.body.innerText.replace(/\s+/g, ' ');
  set(sliders[0], 40);
  const text2 = document.body.innerText.replace(/\s+/g, ' ');
  return { sliders: sliders.length, text1, changed: text1 !== text2 };
});
await browser.close();
const problems = [];
if (r.sliders < 4) problems.push('found ' + r.sliders + ' sliders, expected 4');
else {
  for (const t of ['364 hrs', '$18,200', '$10,000', '$28,200']) if (!r.text1.includes(t)) problems.push('missing ' + t);
  if (!r.changed) problems.push('total did not change when the first slider moved');
}
for (const x of problems) console.log('PROBLEM ' + x);
if (problems.length) { console.log('ROI MATH FAILED'); process.exit(1); }
console.log('ROI MATH PASSED');
