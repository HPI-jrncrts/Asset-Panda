// Build the kit plugin from the repo: gen-blocks → copy ui/components, ui/src, gutenberg/blocks into
// dist/asset-panda-ui-kit (additive: dist-only assets survive) → bump Version + AP_UI_VERSION → zip.
//   node build-kit.mjs <version>
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
// Repo root: AP_ROOT if set, else two levels up from this file (works on Windows and in cloud checkouts).
const ROOT = process.env.AP_ROOT || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..').split(path.sep).join('/');
const DIST = ROOT + '/dist/asset-panda-ui-kit';
const ver = process.argv[2];
if (!/^\d+\.\d+\.\d+$/.test(ver)) throw new Error('usage: node build-kit.mjs 1.3.92');
execFileSync('node', ['tools/gen-blocks.mjs'], { cwd: ROOT, stdio: 'inherit' });
const copy = (src, dst) => {
  for (const e of fs.readdirSync(src, { withFileTypes: true })) {
    const s = path.join(src, e.name), d = path.join(dst, e.name);
    if (e.isDirectory()) { fs.mkdirSync(d, { recursive: true }); copy(s, d); } else fs.copyFileSync(s, d);
  }
};
copy(ROOT + '/ui/components', DIST + '/components');
copy(ROOT + '/ui/src', DIST + '/src');
copy(ROOT + '/gutenberg/blocks', DIST + '/blocks');
const boot = DIST + '/asset-panda-ui-kit.php';
let b = fs.readFileSync(boot, 'utf8');
b = b.replace(/(\* Version:\s+)\d+\.\d+\.\d+/, `$1${ver}`).replace(/define\( 'AP_UI_VERSION', '\d+\.\d+\.\d+' \);/, `define( 'AP_UI_VERSION', '${ver}' );`);
fs.writeFileSync(boot, b);
// Lint every PHP file in the build.
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
