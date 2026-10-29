// Figma-vs-site QA, step 1: one paced full-page capture at 1440 of every dev page that has a Figma desktop frame
// (reports/launch-1028/figma-qa/frames.json), after a reader-style scroll so every reveal has run, plus each top-level
// section's class and box (for cropping). Writes reports/launch-1028/figma-qa/dev/<slug>.png + <slug>.json and a
// DONE file at the end. Read-only; ≥3.3 s between pages.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const DIR = 'reports/launch-1028/figma-qa/dev';
fs.mkdirSync(DIR, { recursive: true });
const frames = JSON.parse(fs.readFileSync('reports/launch-1028/figma-qa/frames.json', 'utf8')).frames;
const paths = [...new Set(frames.map((f) => f.staging_path).filter((p) => /^\/[a-z0-9/-]*$/.test(p || '')))].concat(['/pricing-v2-clean/', '/roi-calculator/', '/thanks-guided-trial-pro/', '/partners/', '/compare/', '/integrations/', '/tutorials/']);
const slug = (p) => (p === '/' ? 'home' : p.replace(/^\/|\/$/g, '').split('/').join('_'));
const browser = await launch();
for (const path of [...new Set(paths)]) {
  const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(p);
  try {
    await p.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
    await p.waitForTimeout(1200);
    const sections = await p.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
      window.scrollTo(0, 0); await new Promise((r) => setTimeout(r, 600));
      const top = [...document.querySelectorAll('section[class^="ap-"], footer, .ap-site-footer')].filter((s) => !s.parentElement.closest('section[class^="ap-"]'));
      return top.map((s) => { const r = s.getBoundingClientRect(); return { cls: (s.className || '').toString().split(' ').slice(0, 2).join(' '), y: Math.round(r.top + window.scrollY), h: Math.round(r.height) }; });
    });
    await p.screenshot({ path: DIR + '/' + slug(path) + '.png', fullPage: true });
    fs.writeFileSync(DIR + '/' + slug(path) + '.json', JSON.stringify({ path, sections }, null, 1));
    console.log('shot ' + path + ' sections=' + sections.length);
  } catch (e) { console.log('ERROR ' + path + ' ' + String(e).slice(0, 80)); }
  await p.close(); await sleep(3300);
}
await browser.close();
fs.writeFileSync(DIR + '/DONE', new Date().toISOString());
console.log('CAPTURE DONE');
