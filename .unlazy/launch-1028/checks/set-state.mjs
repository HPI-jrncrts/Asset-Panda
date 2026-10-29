// Update leaf State cells in PLAN.md's dispatch table.   node set-state.mjs 1.1.1=IN-FLIGHT 1.2.5=VERIFIED ...
import fs from 'node:fs';
const f = 'C:/Sample Code/.unlazy/launch-1028/PLAN.md';
let t = fs.readFileSync(f, 'utf8');
for (const a of process.argv.slice(2)) {
  const [leaf, state] = a.split('=');
  const re = new RegExp('^(\\| ' + leaf.replace(/\./g, '\\.') + ' \\|.*\\| )([A-Z-]+)( \\|)\\s*$', 'm');
  if (!re.test(t)) { console.log('no row for ' + leaf); continue; }
  t = t.replace(re, '$1' + state + '$3');
}
fs.writeFileSync(f, t);
console.log([...t.matchAll(/^\| (1\.\d\.\d) \|.*\| ([A-Z-]+) \|\s*$/gm)].map((m) => m[1] + '=' + m[2]).join(' '));
