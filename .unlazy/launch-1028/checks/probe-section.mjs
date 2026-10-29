// Why is a section blank? Scrolls the selector into view, waits, and reports each visible-size child's opacity,
// visibility, display, text and image load state.   node probe-section.mjs <path> <css selector> [width]
import { DEV, launch, guardPage } from './lib.mjs';
const [p, sel, w = '1440'] = process.argv.slice(2);
const browser = await launch();
const page = await browser.newPage({ viewport: { width: +w, height: 900 } });
await guardPage(page);
const limited = [];
page.on('response', (r) => { if (r.status() === 429) limited.push(r.url().replace(DEV, '').slice(0, 80)); });
await page.goto(DEV + p + '?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.evaluate(async (s) => { for (let y = 0; y < document.documentElement.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); } const el = document.querySelector(s); if (el) el.scrollIntoView({ block: 'start' }); }, sel);
await page.waitForTimeout(2500);
const r = await page.evaluate((s) => {
  const el = document.querySelector(s);
  if (!el) return { missing: true };
  const box = el.getBoundingClientRect();
  const rows = [];
  const walk = (n, d) => {
    if (d > 4) return;
    for (const c of n.children) {
      const b = c.getBoundingClientRect(); const cs = getComputedStyle(c);
      if (b.height < 20) continue;
      rows.push('  '.repeat(d) + (c.className || c.tagName).toString().slice(0, 44) + ' h' + Math.round(b.height) + ' op' + cs.opacity + ' vis:' + cs.visibility + ' disp:' + cs.display + (cs.transform !== 'none' ? ' tf' : '') + (c.tagName === 'IMG' ? ' IMG ' + (c.complete ? (c.naturalWidth ? 'loaded' : 'BROKEN') : 'pending') + ' ' + (c.currentSrc || c.src).replace(location.origin, '').slice(-50) : '') + (c.children.length ? '' : ' "' + c.textContent.trim().slice(0, 40) + '"'));
      walk(c, d + 1);
    }
  };
  walk(el, 0);
  return { cls: el.className, h: Math.round(box.height), op: getComputedStyle(el).opacity, attrs: [...el.attributes].map((a) => a.name + '=' + a.value.slice(0, 40)).join(' '), rows: rows.slice(0, 40) };
}, sel);
await browser.close();
console.log(JSON.stringify({ limited: limited.length, limitedSample: limited.slice(0, 3), ...r }, null, 1));
