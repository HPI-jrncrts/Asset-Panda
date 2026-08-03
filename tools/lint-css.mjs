#!/usr/bin/env node
/**
 * Static guard against the three habits that caused this project's drift.
 *
 *   1. !important                — fighting the cascade instead of fixing it
 *   2. <style>/<script> in markup — per-block styling that cannot be diffed,
 *                                   reviewed, or kept in sync across two builders
 *   3. read-then-write geometry   — getBoundingClientRect() feeding a style
 *                                   assignment in the same handler = the hero shake
 *
 * Run: npm run lint:css
 */
import fs from 'node:fs';
import path from 'node:path';
import { ROOT, ok, fail, info, heading } from './lib/shared.mjs';

const SCAN = ['ui'];
const violations = [];

function walk(dir) {
  const abs = path.join(ROOT, dir);
  if (!fs.existsSync(abs)) return [];
  const out = [];
  for (const e of fs.readdirSync(abs, { withFileTypes: true })) {
    const rel = path.join(dir, e.name);
    if (e.isDirectory()) out.push(...walk(rel));
    else out.push(rel);
  }
  return out;
}

const files = SCAN.flatMap(walk);

for (const rel of files) {
  const ext = path.extname(rel);
  if (!['.css', '.html', '.js', '.mjs'].includes(ext)) continue;
  const text = fs.readFileSync(path.join(ROOT, rel), 'utf8');
  const lines = text.split('\n');

  lines.forEach((line, i) => {
    const n = i + 1;

    if (ext === '.css' && /!\s*important/.test(line)) {
      violations.push({
        rel, n, rule: '!important',
        msg: 'Raise specificity via the .ap-scope root or fix the selector instead.',
        src: line.trim(),
      });
    }

    if (ext === '.html') {
      if (/<style[\s>]/i.test(line)) {
        violations.push({
          rel, n, rule: 'inline <style>',
          msg: 'Styling belongs in the component\'s style.css inside ui/components/<name>/.',
          src: line.trim().slice(0, 90),
        });
      }
      if (/<script[\s>]/i.test(line) && !/type=["']application\/(ld\+)?json/i.test(line)) {
        violations.push({
          rel, n, rule: 'inline <script>',
          msg: 'Move to the component\'s ui/components/<name>/ folder and trigger off a data-ap-* attribute.',
          src: line.trim().slice(0, 90),
        });
      }
      if (/\sstyle=["']/.test(line)) {
        violations.push({
          rel, n, rule: 'style attribute',
          msg: 'Use a token-backed class. Inline style attributes bypass the token audit.',
          src: line.trim().slice(0, 90),
        });
      }
    }
  });

  /* Read-then-write geometry, scoped to handler bodies. */
  if (['.js', '.mjs', '.html'].includes(ext)) {
    const handlerRe = /addEventListener\(\s*['"](scroll|resize)['"][\s\S]{0,1200}?\}\s*\)/g;
    for (const m of text.matchAll(handlerRe)) {
      const body = m[0];
      const reads = /getBoundingClientRect|offsetWidth|offsetHeight|offsetLeft|clientWidth/.test(body);
      const writes = /\.style\.(marginLeft|marginRight|left|right|top|width|height|transform)\s*=|setProperty\(\s*['"](margin-left|left|width|top)/.test(body);
      if (reads && writes) {
        const n = text.slice(0, m.index).split('\n').length;
        violations.push({
          rel, n, rule: 'layout feedback loop',
          msg: 'This handler reads layout geometry and writes position from it — the hero-shake pattern. ' +
               'Use CSS (calc(50% - 50vw), position: sticky) instead.',
          src: body.split('\n')[0].trim().slice(0, 90),
        });
      }
    }
  }
}

heading(`CSS / markup lint  (${files.length} files)`);
if (!violations.length) {
  ok('no violations');
  process.exit(0);
}
for (const v of violations) {
  fail(`${v.rel}:${v.n}  [${v.rule}]`);
  info(v.src);
  info(v.msg);
}
console.log(`\n${violations.length} violation(s).\n`);
process.exit(1);
