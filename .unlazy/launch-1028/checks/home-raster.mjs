// Homepage paint cost A/B: traces a full-page scroll (plus a hover over the industries panel) and sums raster/paint
// time per variant, to find what makes animations feel laggy (main-thread frame timing was already clean).
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const VARIANTS = {
  baseline: '',
  'no-industries-blur': '.ap-industries .ap-sector-box-bg::before, .ap-industries .ap-sector-box-bg::after { filter: none !important; } .ap-industries .ap-sector-bg svg g { filter: none !important; }',
  'no-header-backdrop': '.ap-site-header *, .ap-site-header { backdrop-filter: none !important; -webkit-backdrop-filter: none !important; }',
  'no-anim': '*, *::before, *::after { animation: none !important; transition: none !important; }',
};
const browser = await launch();
for (const [name, css] of Object.entries(VARIANTS)) {
  const p = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  if (css) await p.addStyleTag({ content: css });
  await p.waitForTimeout(2500);
  const cdp = await p.context().newCDPSession(p);
  const events = [];
  cdp.on('Tracing.dataCollected', (d) => events.push(...d.value));
  const done = new Promise((r) => cdp.once('Tracing.tracingComplete', r));
  await cdp.send('Tracing.start', { categories: 'devtools.timeline,disabled-by-default-devtools.timeline,cc,gpu', transferMode: 'ReportEvents' });
  await p.evaluate(async () => { const H = document.documentElement.scrollHeight - innerHeight; await new Promise((res) => { let y = 0; const step = () => { y += 24; window.scrollTo(0, y); if (y < H) requestAnimationFrame(step); else res(); }; requestAnimationFrame(step); }); });
  const ind = await p.locator('.ap-industries').boundingBox().catch(() => null);
  if (ind) { await p.evaluate((y) => window.scrollTo(0, y), Math.max(0, ind.y + (await p.evaluate(() => scrollY)) - 100)); await p.waitForTimeout(300); await p.mouse.move(700, 400); await p.waitForTimeout(900); await p.mouse.move(5, 5); await p.waitForTimeout(900); }
  await cdp.send('Tracing.end');
  await done;
  const sum = (re) => Math.round(events.filter((e) => re.test(e.name) && e.dur).reduce((a, e) => a + e.dur, 0) / 1000);
  console.log(name.padEnd(20) + JSON.stringify({ rasterMs: sum(/^(RasterTask|RasterizerTaskImpl::RunOnWorkerThread)$/), paintMs: sum(/^Paint$/), compositeMs: sum(/^(CompositeLayers|Commit)$/), frames: events.filter((e) => e.name === 'DrawFrame').length, dropped: events.filter((e) => e.name === 'DroppedFrame' || (e.name === 'PipelineReporter' && e.args && e.args.chrome_frame_reporter && e.args.chrome_frame_reporter.state === 'STATE_DROPPED')).length }));
  await p.close(); await sleep(3500);
}
await browser.close();
