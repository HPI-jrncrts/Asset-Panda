// N1 check: outlined closing-card button is white on the purple card at rest; hover keeps the component's fill.
//   node reports/qa-1029/fixes/check-n1.mjs [--inject]
// --inject loads the pending N1 rule (the tail of ui/src/tokens.css from the N1 comment) into the dev page first.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from '../../../.unlazy/launch-1028/checks/lib.mjs';
const INJECT = process.argv.includes('--inject');
const tokens = fs.readFileSync(new URL('../../../ui/src/tokens.css', import.meta.url), 'utf8');
const N1_CSS = tokens.slice(tokens.lastIndexOf('/*', tokens.indexOf('QA 2026-10-29 (N1)')));
const b = await launch(); let pass = 0, total = 0;
const ok = (name, cond, info) => { total++; if (cond) pass++; console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}  ${info || ''}`); };
for (const [path, w] of [['/integrations/', 1440], ['/integrations/', 390], ['/ursa/', 1440], ['/compare/', 1440], ['/integrations/slack/', 1440], ['/industries/education/', 1440]]) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } }); await guardPage(p);
  await p.goto(DEV + path, { waitUntil: 'networkidle' });
  if (INJECT) { await p.addStyleTag({ content: N1_CSS }); await p.waitForTimeout(800); } // let the buttons' colour transition settle
  const rest = await p.evaluate(() => [...document.querySelectorAll('.ap-final-cta-card .ap-btn-outline')].filter((a) => a.offsetParent).map((a) => { const cs = getComputedStyle(a); return { t: a.textContent.trim(), c: cs.color, bc: cs.borderTopColor }; }));
  let hov = null;
  for (const h of await p.$$('.ap-final-cta-card .ap-btn-outline')) { if (await h.isVisible()) { await h.scrollIntoViewIfNeeded(); await h.hover(); await p.waitForTimeout(600); hov = await h.evaluate((a) => getComputedStyle(a).backgroundColor); break; } }
  const white = rest.length > 0 && rest.every((r) => r.c === 'rgb(255, 255, 255)' && /rgba\(255, 255, 255, 0\.6\)/.test(r.bc));
  ok(`N1 outline white at rest ${path} @${w}`, white, JSON.stringify(rest));
  ok(`N1 hover keeps component fill ${path} @${w}`, !!hov && !/rgba\(0, 0, 0, 0\)|transparent/.test(hov), hov || '(no visible button)');
  // The label is markup (block default), so only a deployed build can show it.
  if (!INJECT && path === '/integrations/' && w === 1440) ok('Integrations hub hero says Free Demo', await p.evaluate(() => document.querySelector('.ap-integrations-hero__btn')?.textContent.trim() === 'Free Demo'));
  await p.close(); await sleep(3400);
}
await b.close();
console.log(`N1 CHECK ${pass}/${total} ${pass === total ? 'PASSED' : 'FAILED'}`);
