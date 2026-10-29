// Homepage scroll smoothness A/B: scrolls the page top to bottom in rAF-paced steps and counts long frames (>34 ms)
// for the page as served, then with candidate costs switched off (CSS blur filters, backdrop-filters, all
// animations), to find what makes the homepage feel laggy. node home-perf.mjs
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const VARIANTS = {
  baseline: '',
  'no-filter-blur': '*, *::before, *::after { filter: none !important; } svg [filter] { filter: none !important; }',
  'no-backdrop': '*, *::before, *::after { backdrop-filter: none !important; -webkit-backdrop-filter: none !important; }',
  'no-anim': '*, *::before, *::after { animation: none !important; transition: none !important; }',
  'no-industries-blur': '.ap-industries .ap-sector-bg { display: none !important; } .ap-industries .ap-sector-box-bg::before, .ap-industries .ap-sector-box-bg::after { filter: none !important; opacity: 0 !important; }',
};
const browser = await launch();
for (const [name, css] of Object.entries(VARIANTS)) {
  const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  if (css) await p.addStyleTag({ content: css });
  await p.waitForTimeout(2500);
  const r = await p.evaluate(async () => {
    const H = document.documentElement.scrollHeight - innerHeight; const frames = []; let last = performance.now();
    await new Promise((res) => { let y = 0; const step = () => { const now = performance.now(); frames.push(now - last); last = now; y += 24; window.scrollTo(0, y); if (y < H) requestAnimationFrame(step); else res(); }; requestAnimationFrame(step); });
    frames.shift(); frames.sort((a, b) => a - b);
    const long = frames.filter((f) => f > 34).length; const p95 = frames[Math.floor(frames.length * 0.95)];
    return { frames: frames.length, long, p95: Math.round(p95), median: Math.round(frames[Math.floor(frames.length / 2)]) };
  });
  console.log(name.padEnd(20) + JSON.stringify(r));
  await p.close(); await sleep(3500);
}
await browser.close();
