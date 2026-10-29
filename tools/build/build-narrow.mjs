// Narrow kit build: start from the deployed dist and copy ONLY the listed ui/ paths (+ their generated blocks),
// so unfinished edits elsewhere in ui/ don't ship.  node build-narrow.mjs <version> <ui-relative path>...
//   e.g. node build-narrow.mjs 1.3.92 components/resource-cards src/loader.php components/demo-booking/behaviour.js
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
// Repo root: AP_ROOT if set, else two levels up from this file (works on Windows and in cloud checkouts).
const ROOT = process.env.AP_ROOT || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..').split(path.sep).join('/');
const DIST = ROOT + '/dist/asset-panda-ui-kit';
const [ver, ...items] = process.argv.slice(2);
if (!/^\d+\.\d+\.\d+$/.test(ver) || !items.length) throw new Error('usage: node build-narrow.mjs 1.3.92 <ui paths...>');
execFileSync('node', ['tools/gen-blocks.mjs'], { cwd: ROOT, stdio: 'inherit' });
const copy = (src, dst) => {
  const st = fs.statSync(src);
  if (st.isDirectory()) { fs.mkdirSync(dst, { recursive: true }); for (const e of fs.readdirSync(src)) copy(path.join(src, e), path.join(dst, e)); }
  else { fs.mkdirSync(path.dirname(dst), { recursive: true }); fs.copyFileSync(src, dst); }
};
for (const it of items) {
  copy(path.join(ROOT, 'ui', it), path.join(DIST, it));
  const m = it.match(/^components\/([a-z0-9-]+)(\/component\.json)?$/);
  if (m && fs.existsSync(path.join(ROOT, 'gutenberg/blocks', m[1]))) copy(path.join(ROOT, 'gutenberg/blocks', m[1]), path.join(DIST, 'blocks', m[1]));
  console.log('copied', it);
}
const boot = DIST + '/asset-panda-ui-kit.php';
let b = fs.readFileSync(boot, 'utf8');
b = b.replace(/(\* Version:\s+)\d+\.\d+\.\d+/, `$1${ver}`).replace(/define\( 'AP_UI_VERSION', '\d+\.\d+\.\d+' \);/, `define( 'AP_UI_VERSION', '${ver}' );`);
fs.writeFileSync(boot, b);
// PHP for lint: PHP env var, else the Local app's PHP on Joseph's PC, else php on PATH (cloud).
const LOCAL_PHP = 'C:/Users/josep/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe';
const php = process.env.PHP || (fs.existsSync(LOCAL_PHP) ? LOCAL_PHP : 'php');
let bad = 0;
const lint = (d) => { for (const e of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, e.name); if (e.isDirectory()) lint(p); else if (p.endsWith('.php')) { try { execFileSync(php, ['-l', p], { stdio: 'pipe' }); } catch (err) { bad++; console.log('LINT FAIL', p, String(err.stdout)); } } } };
lint(DIST);
if (bad) throw new Error(bad + ' php files fail lint');
const zip = ROOT + `/dist/asset-panda-ui-kit-${ver}.zip`;
execFileSync(process.env.PYTHON || (process.platform === 'win32' ? 'python' : 'python3'), ['-c', `
import zipfile, os
root = r'${DIST}'
z = zipfile.ZipFile(r'${zip}', 'w', zipfile.ZIP_DEFLATED)
for d, _, fs_ in os.walk(root):
    for f in fs_:
        p = os.path.join(d, f)
        z.write(p, os.path.join('asset-panda-ui-kit', os.path.relpath(p, root)))
z.close()
`]);
console.log('built', zip, fs.statSync(zip).size, 'bytes');
