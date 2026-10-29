// PageSpeed: Lighthouse mobile performance on each listed page is at least --min (default 90), judged by the
// median of --runs (default 3) runs per page. Uses the local Lighthouse runner in the scratchpad.
//   node lighthouse.mjs --paths <json> [--min 90] [--runs 3]
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { readPaths, arg } from './lib.mjs';

const LH = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/lh';
const paths = readPaths(arg('--paths', '.unlazy/launch-1028/perf-pages.json'));
const min = +arg('--min', 90);
const runs = +arg('--runs', 3);
const scores = {};
for (let r = 0; r < runs; r++) {
  const label = 'launch-' + Date.now() + '-' + r;
  execFileSync('node', ['run.mjs', label, ...paths], { cwd: LH, stdio: 'pipe', timeout: 3600000 });
  const out = JSON.parse(fs.readFileSync(LH + '/results-' + label + '.json', 'utf8'));
  for (const [p, row] of Object.entries(out)) (scores[p] = scores[p] || []).push(typeof row.score === 'number' ? row.score : -1);
}
let fail = 0;
for (const p of paths) {
  const s = (scores[p] || []).slice().sort((a, b) => a - b);
  const med = s.length ? s[Math.floor(s.length / 2)] : -1;
  const ok = med >= min;
  if (!ok) fail++;
  console.log((ok ? 'OK   ' : 'LOW  ') + String(med).padStart(3) + '  ' + p + '  runs=' + s.join(','));
}
if (fail) { console.log('LIGHTHOUSE FAILED below ' + min + ': ' + fail); process.exit(1); }
console.log('LIGHTHOUSE PASSED min=' + min + ' pages=' + paths.length);
