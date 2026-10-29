// Liquid CTA hover on dev (contract C24): rest / mid / end clips of an outline button (/ursa-ai/ "See Ursa AI in
// Action") and a gradient button (header "Try for Free"), corner samples at the end of the sweep, the classified
// counts, and no blob inside any button at rest. Writes shots/liquid-after-*.png; prints PASS/FAIL lines and the
// token LIQUID DEV PASSED when every line passed (exit 1 otherwise).
import { DEV, launch, guardPage } from './lib.mjs';
const OUT = 'C:/Sample Code/reports/launch-1028/shots/';
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
await guardPage(page);
await page.goto(DEV + '/ursa-ai/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1500);
const info = await page.evaluate(() => {
  const all = [...document.querySelectorAll('[data-ap-liquid]')];
  const kinds = all.reduce((m, e) => { const k = e.getAttribute('data-ap-liquid'); m[k] = (m[k] || 0) + 1; return m; }, {});
  const link = [...document.querySelectorAll('link[rel=stylesheet], script[src]')].map((l) => l.href || l.src || '').find((h) => /asset-panda-ui-kit\/src\/motion\.(css|js)/.test(h)) || '';
  const ver = link.match(/ver=([\d.]+)/);
  const outline = all.find((e) => e.getAttribute('data-ap-liquid') === 'outline' && /See Ursa/i.test(e.textContent)) || all.find((e) => e.getAttribute('data-ap-liquid') === 'outline' && e.getBoundingClientRect().width > 80);
  const grad = all.find((e) => e.getAttribute('data-ap-liquid') === 'grad' && /Try for Free/i.test(e.textContent)) || all.find((e) => e.getAttribute('data-ap-liquid') === 'grad' && e.getBoundingClientRect().width > 80);
  const mark = (e, id) => { if (e) e.setAttribute('data-liq-id', id); return !!e; };
  const restVisible = all.filter((e) => e.getAttribute('data-ap-liquid') !== 'none').filter((e) => [...e.querySelectorAll('.ap-liquid > span')].some((b) => { const r = b.getBoundingClientRect(), p = e.getBoundingClientRect(); return r.right > p.left + 1; })).map((e) => e.textContent.trim().slice(0, 30));
  return { kinds, ver: ver ? ver[1] : null, link, outline: mark(outline, 'o'), grad: mark(grad, 'g'), outlineText: outline ? outline.textContent.trim().slice(0, 40) : '', gradText: grad ? grad.textContent.trim().slice(0, 40) : '', lbl: !!(outline && outline.querySelector('.ap-liquid-lbl')), restVisible };
});
console.log('kit motion ver=' + info.ver + ' (' + info.link + ') kinds=' + JSON.stringify(info.kinds) + ' lbl=' + info.lbl + ' outline="' + info.outlineText + '" grad="' + info.gradText + '"');
ok(info.ver && Number(info.ver.split('.')[2]) >= 111, 'kit >= 1.3.111 served', 'ver=' + info.ver);
ok(info.restVisible.length === 0, 'no blob inside any button at rest', JSON.stringify(info.restVisible));
ok(info.outline && info.grad, 'sample buttons found', 'outline=' + info.outline + ' grad=' + info.grad);
ok(info.lbl, 'outline button label wrapped for the text sweep');
const sample = async (id) => {
  const el = page.locator('[data-liq-id="' + id + '"]');
  await el.scrollIntoViewIfNeeded();
  await page.mouse.move(2, 2);
  await page.waitForTimeout(1200);
  const box = await el.boundingBox();
  const clip = { x: box.x - 8, y: box.y - 8, width: box.width + 16, height: box.height + 16 };
  await page.screenshot({ path: OUT + 'liquid-after-' + id + '-rest.png', clip });
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.waitForTimeout(230);
  await page.screenshot({ path: OUT + 'liquid-after-' + id + '-mid.png', clip });
  await page.waitForTimeout(1200);
  await page.screenshot({ path: OUT + 'liquid-after-' + id + '-end.png', clip });
  const buf = await page.screenshot({ type: 'png' });
  return page.evaluate(async ({ b64, box, id }) => {
    const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode();
    const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
    const s = (x, y) => { const d = ctx.getImageData(Math.round(x * 2), Math.round(y * 2), 1, 1).data; return [d[0], d[1], d[2]]; };
    const el = document.querySelector('[data-liq-id="' + id + '"]');
    const r = Math.min(parseFloat(getComputedStyle(el).borderTopLeftRadius) || 0, box.height / 2); const d = r - r / Math.SQRT2 + 2;
    return { tl: s(box.x + d, box.y + d), tr: s(box.x + box.width - d, box.y + d), bl: s(box.x + d, box.y + box.height - d), br: s(box.x + box.width - d, box.y + box.height - d) };
  }, { b64: buf.toString('base64'), box, id });
};
if (info.outline) {
  const px = await sample('o');
  const teal = (p) => p[0] < 60 && p[1] > 130 && p[2] > 110;
  ok(['tl', 'tr', 'bl', 'br'].every((k) => teal(px[k])), 'outline corners filled green at the end of the sweep', JSON.stringify(px));
}
if (info.grad) {
  const px = await sample('g');
  const notWhite = (p) => !(p[0] > 240 && p[1] > 240 && p[2] > 240);
  ok(['tl', 'tr', 'bl', 'br'].every((k) => notWhite(px[k])), 'gradient button keeps its colour at the end (sheen, not a white cover)', JSON.stringify(px));
}
await browser.close();
if (problems.length) { console.log('LIQUID DEV FAILED: ' + problems.length); process.exit(1); }
console.log('LIQUID DEV PASSED');
