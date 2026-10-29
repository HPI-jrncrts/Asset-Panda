// Second batch of live JSON-LD on dev (parity F3 follow-up, 2026-10-29 late): every page in jsonld-proposals-2.json
// prints its _ap_jsonld blocks as valid JSON carrying the proposal's types, with at most one FAQPage on the page
// (the kit's own) and no FAQPage inside the copied blocks.
import fs from 'node:fs';
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const FILE = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/tracking-parity/jsonld-proposals-2.json';
const props = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const bad = [];
const browser = await launch();
const p = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await guardPage(p);
for (const pr of props) {
  const res = await p.goto(DEV + pr.live_path + '?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
  const r = await p.evaluate(() => {
    const types = new Set(); let broken = 0;
    const walk = (o) => { if (Array.isArray(o)) o.forEach(walk); else if (o && typeof o === 'object') { [].concat(o['@type'] || []).forEach((t) => types.add(t)); Object.values(o).forEach(walk); } };
    const own = [...document.querySelectorAll('script.ap-content-jsonld')];
    for (const s of own) { try { walk(JSON.parse(s.textContent)); } catch (e) { broken++; } }
    return { blocks: own.length, broken, types: [...types], faqAll: [...document.querySelectorAll('script[type="application/ld+json"]')].filter((s) => /"FAQPage"/.test(s.textContent)).length };
  });
  const missing = pr.types.filter((t) => !r.types.includes(t));
  const pass = res.status() === 200 && r.blocks > 0 && !r.broken && !missing.length && !r.types.includes('FAQPage') && r.faqAll <= 1;
  console.log((pass ? 'PASS ' : 'FAIL ') + pr.live_path + ' — blocks=' + r.blocks + ' missing=' + JSON.stringify(missing) + ' faqOnPage=' + r.faqAll);
  if (!pass) bad.push(pr.live_path);
  await sleep(3300);
}
await browser.close();
console.log(bad.length ? 'JSONLD2 FAILED: ' + bad.length : 'JSONLD2 PASSED (' + props.length + ' pages)');
if (bad.length) process.exit(1);
