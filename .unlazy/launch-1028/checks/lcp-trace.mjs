// Why is the LCP late? Loads one page as a throttled phone (Lighthouse's mobile profile: 150 ms RTT, 1.6 Mbps down,
// 4x CPU slowdown) and lists every LCP candidate Chrome reported — element, size, loadTime and renderTime — plus the
// hero images' load/decode moments. --css "<rules>" injects a style override at the top of <head> so a hypothesis
// (e.g. "the entrance animation delays the paint") can be tested in one run.
//   node lcp-trace.mjs <path> [--css "<rules>"] [--runs 2]
import { DEV, launch, guardPage, arg } from './lib.mjs';
const path = process.argv[2] || '/products/asset-panda-for-fire/';
const css = arg('--css', '');
const runs = +arg('--runs', 2);
const browser = await launch();
for (let r = 0; r < runs; r++) {
  const ctx = await browser.newContext({ viewport: { width: 412, height: 915 }, deviceScaleFactor: 2.625, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36' });
  const page = await ctx.newPage();
  await guardPage(page);
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
  await page.addInitScript(() => {
    window.__lcp = []; window.__imgs = [];
    try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__lcp.push({ t: Math.round(e.startTime), render: Math.round(e.renderTime), load: Math.round(e.loadTime), size: e.size, url: (e.url || '').replace(/.*\//, ''), el: e.element ? e.element.tagName + '.' + String(e.element.className).slice(0, 40) : '' }); }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
    try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (/\.(webp|png|jpg|svg)/.test(e.name)) window.__imgs.push({ name: e.name.replace(/.*\//, '').slice(0, 40), start: Math.round(e.startTime), end: Math.round(e.responseEnd), kb: Math.round((e.transferSize || 0) / 1024), pri: e.renderBlockingStatus || '' }); }).observe({ type: 'resource', buffered: true }); } catch (e) {}
    try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__fcp = Math.round(e.startTime); }).observe({ type: 'paint', buffered: true }); } catch (e) {}
  });
  if (css) {
    await page.route((u) => u.pathname === path || u.pathname === path.replace(/\/$/, ''), async (route) => {
      const res = await route.fetch();
      let body = await res.text();
      body = body.replace(/<head([^>]*)>/i, '<head$1><style id="ap-hypothesis">' + css + '</style>');
      await route.fulfill({ response: res, body, headers: { ...res.headers(), 'content-length': String(Buffer.byteLength(body)) } });
    });
  }
  const t0 = Date.now();
  await page.goto(DEV + path + '?nc=' + Date.now() + r, { waitUntil: 'load', timeout: 120000 });
  await page.waitForTimeout(6000);
  const out = await page.evaluate(() => ({ fcp: window.__fcp, lcp: window.__lcp, imgs: window.__imgs.filter((i) => /hero|Fireman|Police|dash|person/i.test(i.name)).slice(0, 8), nav: (() => { const n = performance.getEntriesByType('navigation')[0]; return n ? { ttfb: Math.round(n.responseStart), html: Math.round(n.responseEnd), dcl: Math.round(n.domContentLoadedEventEnd), load: Math.round(n.loadEventEnd) } : null; })() }));
  console.log('run ' + (r + 1) + (css ? ' [override]' : ' [baseline]') + ' wall=' + (Date.now() - t0) + 'ms nav=' + JSON.stringify(out.nav) + ' fcp=' + out.fcp);
  for (const c of out.lcp) console.log('  LCP candidate t=' + c.t + ' render=' + c.render + ' load=' + c.load + ' size=' + c.size + ' ' + c.el + ' ' + c.url);
  for (const i of out.imgs) console.log('  img ' + i.name + ' start=' + i.start + ' end=' + i.end + ' ' + i.kb + 'KB');
  await ctx.close();
}
await browser.close();
