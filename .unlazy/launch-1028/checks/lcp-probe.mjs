// Mobile load of one page: which element is the LCP, which hero sources the browser chose, and the heaviest responses.
import { DEV, launch, guardPage } from './lib.mjs';
const path = process.argv[2] || '/products/asset-panda-for-fire/';
const browser = await launch();
const ctx = await browser.newContext({ viewport: { width: 412, height: 915 }, deviceScaleFactor: 2.625, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36' });
const page = await ctx.newPage();
await guardPage(page);
const sizes = new Map();
page.on('response', async (r) => { try { const h = r.headers(); const u = r.url(); const len = +h['content-length'] || 0; sizes.set(u, { len, type: h['content-type'] || '', status: r.status() }); } catch (e) {} });
await page.goto(DEV + path + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(3000);
const info = await page.evaluate(() => {
  const lcp = performance.getEntriesByType('largest-contentful-paint').map((e) => ({ t: Math.round(e.startTime), size: e.size, url: (e.url || '').replace(/.*\//, ''), el: e.element ? e.element.tagName + '.' + String(e.element.className).slice(0, 60) : null }));
  const layers = [...document.querySelectorAll('.ap-vhero__layer, .ap-vhero img, .ap-hero img')].slice(0, 6).map((i) => ({ cls: String(i.className).replace('ap-vhero__layer', '').trim().slice(0, 30), cur: (i.currentSrc || '').replace(/.*\//, ''), nat: i.naturalWidth + 'x' + i.naturalHeight, box: Math.round(i.getBoundingClientRect().width) + 'x' + Math.round(i.getBoundingClientRect().height), fp: i.getAttribute('fetchpriority'), complete: i.complete }));
  const nav = performance.getEntriesByType('navigation')[0];
  return { lcp, layers, ttfb: nav ? Math.round(nav.responseStart) : null, domLoaded: nav ? Math.round(nav.domContentLoadedEventEnd) : null, htmlKB: nav ? Math.round(nav.transferSize / 1024) : null };
});
console.log(JSON.stringify(info, null, 1));
const top = [...sizes.entries()].sort((a, b) => b[1].len - a[1].len).slice(0, 14).map(([u, s]) => Math.round(s.len / 1024) + 'KB ' + s.status + ' ' + s.type.split(';')[0] + ' ' + u.replace(DEV, '').replace(/\?.*$/, '').slice(-80));
console.log('TOP RESPONSES\n' + top.join('\n'));
await browser.close();
