// Every named child ledger is fully met (gate-check --status exits 0 and reports no unmet or abandoned gate).
//   node children-met.mjs leaf-1.1.1 leaf-1.1.2 ...
import { execFileSync } from 'node:child_process';
const scope = 'C:/Sample Code/.unlazy/launch-1028';
const check = 'C:/Users/josep/.claude/skills/unlazy/scripts/gate-check.mjs';
const ids = process.argv.slice(2);
if (!ids.length) { console.log('no children named'); process.exit(2); }
let bad = 0;
for (const id of ids) {
  let out = '', code = 0;
  // --status rejects --cwd (it never executes a check), so anchor with --root only.
  try { out = execFileSync('node', [check, '--root', 'C:/Sample Code', '--status', scope + '/gates/' + id + '.md'], { stdio: 'pipe' }).toString(); } catch (e) { code = e.status || 1; out = String(e.stdout || '') + String(e.stderr || ''); }
  const ok = code === 0 && !/UNMET|HANDOFF|ABANDON|stale/i.test(out);
  if (!ok) bad++;
  console.log((ok ? 'met   ' : 'UNMET ') + id + (ok ? '' : '\n' + out.split('\n').slice(0, 12).join('\n')));
}
if (bad) { console.log('CHILDREN UNMET ' + bad); process.exit(1); }
console.log('CHILDREN ALL MET n=' + ids.length);
