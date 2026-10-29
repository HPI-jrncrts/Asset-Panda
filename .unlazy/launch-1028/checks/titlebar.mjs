// The sticky title bar is hidden at the top of the page, shown once the H1 has scrolled out under the header,
// and hidden again on scrolling back — at 1440 and 390.   node titlebar.mjs --path /integrations/slack/
import { DEV, launch, guardPage, arg } from './lib.mjs';
const p = arg('--path', '/integrations/slack/');
const browser = await launch();
const problems = [];
for (const w of [1440, 390]) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 } });
  await guardPage(page);
  await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(800);
  const st = () => page.evaluate(() => { const b = document.querySelector('.ap-integration-titlebar'); return b ? b.classList.contains('is-shown') : null; });
  const s0 = await st();
  await page.evaluate(() => window.scrollTo(0, 1200)); await page.waitForTimeout(700);
  const s1 = await st();
  await page.evaluate(() => window.scrollTo(0, 0)); await page.waitForTimeout(700);
  const s2 = await st();
  if (s0 === null) problems.push('no title bar @' + w);
  else if (s0 !== false || s1 !== true || s2 !== false) problems.push('@' + w + ' top=' + s0 + ' scrolled=' + s1 + ' back=' + s2);
  await page.close();
}
await browser.close();
for (const x of problems) console.log('PROBLEM ' + x);
if (problems.length) { console.log('TITLEBAR CHECK FAILED'); process.exit(1); }
console.log('TITLEBAR CHECK PASSED ' + p);
