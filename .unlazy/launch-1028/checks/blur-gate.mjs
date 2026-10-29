// Blurry images (contract C36): re-runs the 2x-screen survey and fails on any RASTER <img> that is still upscaled
// ≥20% beyond its natural size, excluding full-bleed art (rendered ≥1200 CSS px wide), SVG sources, data: URIs and
// the sources the image agent judged already sharp enough (blur-fix/not-replaced.json).
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
const OUT = 'C:/Sample Code/reports/launch-1028/blur-survey-after.json';
execFileSync('node', ['.unlazy/launch-1028/checks/blur-survey.mjs', OUT], { stdio: 'inherit', env: process.env, timeout: 1500000 });
const skipFile = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/blur-fix/not-replaced.json';
const skipped = new Set();
try { for (const s of JSON.parse(fs.readFileSync(skipFile, 'utf8'))) skipped.add((s.rel || s.src || '').replace(/^.*wp-content\/uploads\//, '')); } catch (e) { /* none */ }
const rows = JSON.parse(fs.readFileSync(OUT, 'utf8'));
const bad = rows.filter((r) => !/^data:|\.svg(\?|$)/.test(r.src)).filter((r) => parseInt(r.rendered, 10) < 1200).filter((r) => !skipped.has(r.src.replace(/^.*wp-content\/uploads\//, '').replace(/\?.*$/, '')));
const uniq = [...new Map(bad.map((r) => [r.src, r])).values()];
for (const r of uniq) console.log('STILL SOFT ' + r.short + '% ' + r.src + ' rendered ' + r.rendered + ' natural ' + r.natural + ' on ' + r.page);
console.log('raster rows checked=' + rows.length + ' still soft=' + uniq.length + ' skipped-as-sharp=' + skipped.size);
if (uniq.length) { console.log('BLUR GATE FAILED'); process.exit(1); }
console.log('BLUR GATE PASSED');
