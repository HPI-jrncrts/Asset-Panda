// Every ledger in the scope lints clean (gate-lint prints LINT OK for each).
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
const scope = 'C:/Sample Code/.unlazy/launch-1028';
const lint = 'C:/Users/josep/.claude/skills/unlazy/scripts/gate-lint.mjs';
const files = [scope + '/GATES.md', ...fs.readdirSync(scope + '/gates').filter((f) => f.endsWith('.md')).map((f) => scope + '/gates/' + f)];
let bad = 0;
for (const f of files) {
  let out = '';
  try { out = execFileSync('node', [lint, f], { stdio: 'pipe' }).toString(); } catch (e) { out = String(e.stdout || '') + String(e.stderr || ''); }
  const ok = /LINT OK/.test(out);
  if (!ok) bad++;
  console.log((ok ? 'ok   ' : 'FAIL ') + f.replace(scope + '/', '') + (ok ? '' : '\n' + out));
}
if (bad) { console.log('LINT ALL FAILED ' + bad); process.exit(1); }
console.log('LINT ALL OK files=' + files.length);
