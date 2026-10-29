// Every image referenced by every listed dev page resolves (2xx): <img src/srcset>, <source srcset>, <video poster>,
// image preloads and url() in inline styles. Also fails on a page that is not 200.
//   node images.mjs --paths <json> [--limit N] [--control]
// --control adds one URL known to be missing; the check must then FAIL (negative control).
import { DEV, fetchPage, head, readPaths, arg, flag, sleep } from './lib.mjs';

const paths = readPaths(arg('--paths', '.unlazy/launch-1028/pages.json')).slice(0, +arg('--limit', 100000));
const urls = new Map();
const pageErrors = [];
const add = (u, p) => {
  u = u.trim().replace(/&amp;/g, '&');
  if (!u || /^(data:|blob:|#)/.test(u)) return;
  if (u.startsWith('//')) u = 'https:' + u;
  if (u.startsWith('/')) u = DEV + u;
  if (!/^https?:/.test(u)) return;
  if (!urls.has(u)) urls.set(u, []);
  if (urls.get(u).length < 3) urls.get(u).push(p);
};
for (const p of paths) {
  const r = await fetchPage(p);
  if (r.status !== 200) { pageErrors.push(p + ' HTTP ' + r.status); await sleep(1500); continue; }
  const t = r.text.replace(/<script[^>]*application\/ld\+json[^>]*>[\s\S]*?<\/script>/gi, '');
  for (const m of t.matchAll(/<(?:img|source|video|link)\b[^>]*>/gi)) {
    const tag = m[0];
    if (/^<link/i.test(tag) && !/rel=["']preload["'][^>]*as=["']image["']|as=["']image["'][^>]*rel=["']preload["']/i.test(tag)) continue;
    for (const a of tag.matchAll(/\s(?:src|href|poster|data-src)=["']([^"']+)["']/gi)) add(a[1], p);
    for (const s of tag.matchAll(/\s(?:srcset|imagesrcset|data-srcset)=["']([^"']+)["']/gi)) for (const c of s[1].split(',')) add(c.trim().split(/\s+/)[0], p);
  }
  for (const m of t.matchAll(/url\(\s*["']?([^"')]+?)["']?\s*\)/gi)) if (/\/wp-content\//.test(m[1])) add(m[1], p);
  await sleep(1500);
}
if (flag('--control')) add('/wp-content/uploads/2026/10/this-file-does-not-exist-control.png', '(control)');
const bad = [];
let n = 0, local = 0;
import fs from 'node:fs';
const KIT = DEV + '/wp-content/plugins/asset-panda-ui-kit/';
for (const [u, pages] of urls) {
  // Kit assets ship in the deployed zip (built from dist/): verify them on disk instead of over HTTP,
  // which keeps the HEAD rate under Cloudflare's per-IP limit.
  if (u.startsWith(KIT)) {
    const rel = decodeURIComponent(u.slice(KIT.length).split('?')[0]);
    if (!fs.existsSync('C:/Sample Code/dist/asset-panda-ui-kit/' + rel)) bad.push('MISSING-IN-KIT ' + rel + '  on ' + pages.join(' '));
    local++;
    continue;
  }
  const s = await head(u);
  if (s < 200 || s >= 400) bad.push(s + ' ' + u.replace(DEV, '') + '  on ' + pages.join(' '));
  if (++n % 20 === 0) await sleep(2500); else await sleep(650);
}
console.log('kit assets checked on disk=' + local + ' http=' + n);
for (const e of pageErrors) console.log('PAGE ' + e);
for (const b of bad) console.log('MISSING ' + b);
console.log('pages=' + paths.length + ' pageErrors=' + pageErrors.length + ' urls=' + urls.size + ' missing=' + bad.length);
if (pageErrors.length || bad.length) { console.log('IMAGE CHECK FAILED'); process.exit(1); }
console.log('IMAGE CHECK PASSED pages=' + paths.length + ' urls=' + urls.size);
