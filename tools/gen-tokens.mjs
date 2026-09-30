// Generates the responsive block of ui/src/tokens.css from the Figma "Responsive" collection.
// PATHS and VALS are index-aligned: both were produced by iterating collection.variableIds in order.
const PATHS = `Page/Max Width/content-xs
Page/Page Width/Default
Page/Max Width/content-sm
Page/Max Width/content-md
Page/Max Width/content-lg
Page/Max Width/content-xl
Page/Max Width/content-2xl
Page/Max Width/content-3xl
Page/Max Width/content-full
Page/Page Width/Wide
Page/Page Width/Narrow
Section/padding y/pad-y-6xl
Section/padding y/pad-y-5xl
Section/padding y/pad-y-4xl
Section/padding y/pad-y-3xl
Section/padding y/pad-y-2xl
Section/padding y/pad-y-xl
Section/padding y/pad-y-lg
Section/padding y/pad-y-md
Section/padding y/pad-y-sm
Section/padding y/pad-y-xs
Section/padding y/pad-y-2xs
Section/padding y/pad-y-3xs
Section/padding y/pad-y-4xs
Section/padding y/pad-y-5xs
Section/padding y/pad-y-none
Section/padding x/pad-x-6xl
Section/padding x/pad-x-5xl
Section/padding x/pad-x-4xl
Section/padding x/pad-x-3xl
Section/padding x/pad-x-2xl
Section/padding x/pad-x-xl
Section/padding x/pad-x-lg
Section/padding x/pad-x-md
Section/padding x/pad-x-sm
Section/padding x/pad-x-xs
Section/padding x/pad-x-2xs
Section/padding x/pad-x-3xs
Section/padding x/pad-x-4xs
Section/padding x/pad-x-none
Surface/Max Width/content-2xs
Surface/Max Width/content-xs
Surface/Max Width/content-sm
Surface/Max Width/content-md
Surface/Max Width/content-lg
Surface/Max Width/content-xl
Surface/Max Width/content-2xl
Surface/Max Width/content-3xl
Surface/Max Width/content-4xl
Surface/Max Width/content-full
Surface/gap/surf-gap-none
Surface/gap/surf-gap-xs
Surface/gap/surf-gap-sm
Surface/gap/surf-gap-md
Surface/gap/surf-gap-lg
Surface/gap/surf-gap-xl
Surface/gap/surf-gap-2xl
Surface/gap/surf-gap-3xl
Surface/gap/surf-gap-4xl
Surface/gap/surf-gap-5xl
Surface/gap/surf-gap-6xl
Surface/gap/surf-gap-7xl
Surface/gap/surf-gap-8xl
Surface/gap/surf-gap-9xl
Surface/padding/surf-pad-7xl
Surface/padding/surf-pad-6xl
Surface/padding/surf-pad-5xl
Surface/padding/surf-pad-4xl
Surface/padding/surf-pad-3xl
Surface/padding/surf-pad-2xl
Surface/padding/surf-pad-xl
Surface/padding/surf-pad-lg
Surface/padding/surf-pad-md
Surface/padding/surf-pad-sm
Surface/padding/surf-pad-xs
Surface/padding/surf-pad-2xs
Surface/padding/surf-pad-none
Typeface Size/Display & Heading/Display
Typeface Size/Display & Heading/heading 1-lg
Typeface Size/Display & Heading/heading 1
Typeface Size/Display & Heading/heading 1-sm
Typeface Size/Display & Heading/heading 2
Typeface Size/Display & Heading/heading 3
Typeface Size/Display & Heading/heading 4
Typeface Size/Display & Heading/heading 5
Typeface Size/Display & Heading/heading 6
Typeface Size/Body/text-xl
Typeface Size/Quote/quote-2xl
Typeface Size/Body/text-lg
Typeface Size/Body/text-md
Typeface Size/Quote/quote-xl
Typeface Size/Quote/quote-lg
Typeface Size/Body/text-reg
Typeface Size/Body/text-sm
Typeface Size/Quote/quote-md
Typeface Size/Body/text-xs
Typeface Size/Quote/quote-sm
Typeface Size/Quote/quote-xs
Typeface Size/Utility/eyebrow
Typeface Size/Utility/Buttons/button-lg
Typeface Size/Utility/Buttons/button-md
Typeface Size/Utility/Nav/nav-md
Typeface Size/Utility/Buttons/button-sm
Typeface Size/Utility/Nav/nav-sm
Typeface Size/Utility/form label
Typeface Size/Utility/caption
Grid Guides/count y/grid-count
Grid Guides/gap/grid-gap`.trim().split('\n');

const VALS = `480,480,480,480,390,320
1440,1280,992,768,480,360
640,640,640,600,390,320
800,800,720,640,390,320
960,960,800,720,390,320
1120,1120,960,720,390,320
1280,1200,960,720,390,320
1360,1280,960,720,390,320
1440,1280,992,768,480,360
1920,1440,1024,768,480,360
1280,1200,960,744,390,360
280,280,220,180,140,120
240,240,200,160,120,112
220,220,180,140,112,96
200,200,160,120,96,80
160,160,120,96,80,64
120,120,96,80,64,56
112,112,80,72,56,48
80,80,64,56,40,40
40,40,32,32,24,24
24,24,24,24,24,24
20,20,20,20,14,14
16,16,16,16,12,12
12,12,12,12,8,8
8,8,8,8,4,4
0,0,0,0,0,0
280,240,160,120,96,80
240,200,120,112,80,64
220,180,112,96,64,56
200,160,96,80,56,48
160,120,80,64,48,40
120,80,64,48,40,32
80,64,48,40,32,28
64,56,40,36,28,24
40,40,32,32,24,22
24,24,24,24,20,20
20,20,20,20,18,16
16,16,16,16,16,14
8,8,8,8,8,8
0,0,0,0,0,0
480,480,480,480,390,320
560,560,560,480,390,320
640,640,640,600,390,320
720,720,720,640,390,320
800,800,720,640,390,320
960,960,800,720,390,320
1120,1120,960,720,390,320
1280,1200,960,720,390,320
1360,1280,960,720,390,320
1440,1280,992,768,480,360
0,0,0,0,0,0
2,2,2,2,2,2
4,4,4,4,4,4
8,8,8,8,8,8
16,16,16,16,12,12
24,24,20,20,16,16
28,28,22,22,18,18
32,32,24,24,20,20
40,40,40,32,24,24
64,64,48,40,32,24
80,80,64,48,40,32
96,96,80,64,48,40
112,112,88,80,56,48
120,120,104,88,64,56
112,112,80,56,48,40
80,80,60,48,40,32
64,64,48,40,32,28
48,48,40,32,28,24
40,40,32,28,24,22
32,32,28,24,22,20
24,24,24,22,20,18
16,16,16,16,16,14
12,12,12,12,12,12
8,8,8,8,8,8
4,4,4,4,4,4
2,2,2,2,2,2
0,0,0,0,0,0
120,96,80,64,56,48
80,72,64,56,48,40
56,48,40,40,36,32
48,40,40,32,28,28
40,36,36,28,24,24
28,28,28,24,22,22
24,24,24,20,20,20
20,20,20,18,18,18
16,16,16,14,14,14
24,24,24,20,20,20
40,40,40,32,28,24
20,20,20,18,18,18
18,18,18,16,16,16
32,32,32,24,24,22
24,24,22,20,20,18
16,16,16,14,14,14
14,14,14,14,12,12
18,18,18,16,16,14
12,12,12,12,12,12
16,16,16,14,14,14
14,14,14,14,12,12
14,14,14,12,12,12
18,18,18,16,16,16
16,16,16,14,14,14
16,16,16,14,14,14
14,14,14,14,14,14
14,14,14,14,14,14
14,14,14,14,14,14
14,14,14,12,12,12
12,12,12,12,4,4
28,28,20,16,8,8`.trim().split('\n').map((l) => l.split(',').map(Number));

if (PATHS.length !== VALS.length) {
  throw new Error(`misaligned: ${PATHS.length} paths vs ${VALS.length} value rows`);
}

const slug = (s) =>
  s.toLowerCase().replace(/&/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

function nameFor(path) {
  const p = path.split('/');
  const last = slug(p[p.length - 1]);
  if (p[0] === 'Page' && p[1] === 'Max Width') return `--ap-page-max-${last}`;
  if (p[0] === 'Page' && p[1] === 'Page Width') return `--ap-page-w-${last}`;
  if (p[0] === 'Section') return `--ap-${last}`;
  if (p[0] === 'Surface' && p[1] === 'Max Width') return `--ap-surf-max-${last}`;
  if (p[0] === 'Surface') return `--ap-${last}`;
  if (p[0] === 'Typeface Size') return `--ap-fs-${last.replace(/^heading-/, 'h')}`;
  if (p[0] === 'Grid Guides') return `--ap-${last}`;
  throw new Error('unmapped: ' + path);
}

// Unit policy: font sizes -> rem so they honour user text scaling; lengths -> px; counts unitless.
function unitFor(path) {
  if (path.startsWith('Typeface Size')) return 'rem';
  if (path.endsWith('grid-count')) return '';
  return 'px';
}
const fmt = (n, u) =>
  u === 'rem' ? `${+(n / 16).toFixed(4)}rem` : u === '' ? String(n) : `${n}px`;

const MINW = [1440, 1280, 992, 768, 480, null]; // null = :root (Mobile 360 base)
const LABEL = [
  'Desktop (1440+)',
  'Desktop (1280+)',
  'Tablet (992+)',
  'Small Tablet (768+)',
  'Mobile Landscape (480+)',
  'Mobile (360+)',
];

const rows = PATHS.map((p, i) => ({
  path: p,
  name: nameFor(p),
  unit: unitFor(p),
  vals: VALS[i],
}));

// Page/Max Width and Surface/Max Width both contain "content-xs" — the prefixes must keep them apart.
const seen = new Map();
for (const r of rows) {
  if (seen.has(r.name)) {
    throw new Error(`duplicate token ${r.name}: "${seen.get(r.name)}" vs "${r.path}"`);
  }
  seen.set(r.name, r.path);
}

let out = '';
out += '\t/* ---- Responsive scale (base = Figma "Mobile (360+)" mode) ---- */\n';
let lastGroup = '';
for (const r of rows) {
  const g = r.path.split('/').slice(0, 2).join('/');
  if (g !== lastGroup) {
    out += `\n\t/* ${g} */\n`;
    lastGroup = g;
  }
  out += `\t${r.name}: ${fmt(r.vals[5], r.unit)};\n`;
}
out += '}\n';

// Each larger breakpoint emits only the tokens whose value changed from the next smaller one.
for (let k = 4; k >= 0; k--) {
  const changed = rows.filter((r) => r.vals[k] !== r.vals[k + 1]);
  if (!changed.length) continue;
  out += `\n/* ${LABEL[k]} */\n@media (min-width: ${MINW[k]}px) {\n\t:root {\n`;
  for (const r of changed) out += `\t\t${r.name}: ${fmt(r.vals[k], r.unit)};\n`;
  out += '\t}\n}\n';
}

process.stdout.write(out);
console.error(`ok: ${rows.length} tokens, ${seen.size} unique`);
