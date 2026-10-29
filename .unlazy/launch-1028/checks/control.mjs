// Negative control for a sweep: on ONE page the check must PASS as-is and FAIL once the named defect is
// injected (--control). Both halves are required, so a check that fails for an unrelated reason, or one that
// cannot fail at all, never counts as a working oracle.   node control.mjs images|fade|links|forms
import { execFileSync } from 'node:child_process';
const kind = process.argv[2];
const lists = { images: 'pages.json', fade: 'fade-pages.json', links: 'pages.json', forms: 'forms-pages.json' };
const marker = { images: 'IMAGE CHECK', fade: 'FADE CHECK', links: 'LINK CHECK', forms: 'FORM CHECK' };
if (!lists[kind]) { console.log('unknown check ' + kind); process.exit(2); }
function run(extra) {
  let out = '', code = 0;
  try {
    out = execFileSync('node', ['.unlazy/launch-1028/checks/' + kind + '.mjs', '--paths', '.unlazy/launch-1028/' + lists[kind], '--limit', '1', ...extra], { cwd: 'C:/Sample Code', stdio: 'pipe', timeout: 900000 }).toString();
  } catch (e) { code = e.status || 1; out = String(e.stdout || '') + String(e.stderr || ''); }
  return { code, out };
}
const base = run([]);
const basePassed = base.code === 0 && base.out.includes(marker[kind] + ' PASSED');
console.log('baseline (no defect): exit ' + base.code + ' — ' + (base.out.split('\n').find((l) => l.includes(marker[kind])) || '').trim());
if (!basePassed) { console.log('CONTROL INVALID: the page fails without the injected defect, so the control proves nothing'); process.exit(1); }
const ctl = run(['--control']);
const failed = ctl.code !== 0 && ctl.out.includes(marker[kind] + ' FAILED');
console.log('with defect: exit ' + ctl.code + ' — ' + ctl.out.split('\n').filter((l) => /FAIL|MISSING|LIVE|BROKEN/.test(l)).slice(0, 3).join(' | ').trim());
if (!failed) { console.log('CONTROL DID NOT FAIL'); process.exit(1); }
console.log('CONTROL FAILED AS EXPECTED ' + kind);
