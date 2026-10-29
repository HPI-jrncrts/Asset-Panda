// The new site stands alone: no listed dev page links to or loads anything from www.assetpanda.com
// (JSON-LD, canonical/og meta and plain text are ignored), and every same-site <a href> answers 200
// without redirecting off-site.   node links.mjs --paths <json> [--limit N] [--control]
import { DEV, fetchPage, head, readPaths, arg, flag, sleep } from './lib.mjs';

const paths = readPaths(arg('--paths', '.unlazy/launch-1028/pages.json')).slice(0, +arg('--limit', 100000));
const liveRefs = [];
const hrefs = new Map();
for (const p of paths) {
  const r = await fetchPage(p);
  if (r.status !== 200) { liveRefs.push(p + ' HTTP ' + r.status); await sleep(1500); continue; }
  let t = r.text.replace(/<script[^>]*application\/ld\+json[^>]*>[\s\S]*?<\/script>/gi, '').replace(/<link[^>]*rel=["'](?:canonical|alternate|shortlink)["'][^>]*>/gi, '').replace(/<meta[^>]*>/gi, '');
  if (flag('--control') && p === paths[0]) t += '<a href="https://www.assetpanda.com/pricing/">control</a>';
  for (const m of t.matchAll(/<a\b[^>]*\shref=["'](https?:\/\/(?:www\.)?assetpanda\.com[^"']*)["']/gi)) liveRefs.push(p + ' link ' + m[1].slice(0, 90));
  for (const m of t.matchAll(/(?:src|srcset|poster|href|imagesrcset)=["'][^"']*?(?:https?:)?\/\/(?:www\.)?assetpanda\.com(\/wp-content\/[^"'\s,)]*)/gi)) liveRefs.push(p + ' asset ' + m[1].slice(0, 90));
  for (const m of t.matchAll(/url\(\s*["']?(?:https?:)?\/\/(?:www\.)?assetpanda\.com(\/wp-content\/[^"')]*)/gi)) liveRefs.push(p + ' css ' + m[1].slice(0, 90));
  for (const m of t.matchAll(/<a\b[^>]*\shref=["']([^"'#]+)["']/gi)) {
    let u = m[1];
    if (/^(mailto:|tel:|javascript:)/i.test(u)) continue;
    if (u.startsWith('/')) u = DEV + u;
    if (!u.startsWith(DEV)) continue;
    u = u.split('?')[0];
    if (!hrefs.has(u)) hrefs.set(u, p);
  }
  await sleep(1500);
}
const broken = [];
let n = 0;
for (const [u, p] of hrefs) {
  const s = await head(u);
  if (s !== 200) broken.push(s + ' ' + u.replace(DEV, '') + '  from ' + p);
  if (++n % 20 === 0) await sleep(2500); else await sleep(700);
}
for (const x of liveRefs) console.log('LIVE ' + x);
for (const x of broken) console.log('BROKEN ' + x);
console.log('pages=' + paths.length + ' liveRefs=' + liveRefs.length + ' hrefs=' + hrefs.size + ' broken=' + broken.length);
if (liveRefs.length || broken.length) { console.log('LINK CHECK FAILED'); process.exit(1); }
console.log('LINK CHECK PASSED pages=' + paths.length + ' hrefs=' + hrefs.size);
