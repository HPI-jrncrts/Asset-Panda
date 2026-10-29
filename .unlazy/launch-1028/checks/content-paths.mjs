// Published content survives the cutover: no published page, resource, press release or post renders a
// webp-express path (live-only copies) or an absolute staging-host /wp-content/ URL — uploads are root-relative.
// Reads the public REST view (what do_blocks renders from the stored content).
// Excluded: the kit plugin's own asset URLs (/wp-content/plugins/asset-panda-ui-kit/...). ap_ui_asset_url() builds
// them at render time from content_url(), i.e. from the site URL of the moment, so they follow the cutover and are
// never stored in content.
import { DEV, UA, sleep } from './lib.mjs';
const bad = [];
let scanned = 0;
for (const type of ['pages', 'resource', 'newsroom', 'posts']) {
  for (let page = 1; page < 40; page++) {
    let r;
    for (let k = 0; k < 6; k++) { r = await fetch(DEV + '/wp-json/wp/v2/' + type + '?per_page=50&page=' + page + '&_fields=id,link,content', { headers: { 'user-agent': UA } }); if (r.status !== 429) break; await sleep(12000 * (k + 1)); }
    if (!r.ok) break;
    const list = await r.json();
    if (!Array.isArray(list) || !list.length) break;
    for (const p of list) {
      scanned++;
      const h = (p.content && p.content.rendered) || '';
      for (const m of h.matchAll(/(?:assetpanda1dev\.wpenginepowered\.com)?\/wp-content\/webp-express\/[^"'\s)]+/g)) bad.push(type + ':' + p.id + ' webp-express ' + m[0].slice(0, 90));
      for (const m of h.matchAll(/https?:\/\/assetpanda1dev\.wpenginepowered\.com\/wp-content\/(?!plugins\/asset-panda-ui-kit\/)[^"'\s)]+/g)) bad.push(type + ':' + p.id + ' staging-host ' + m[0].slice(0, 110));
    }
    if (list.length < 50) break;
    await sleep(2500);
  }
}
const uniq = [...new Set(bad)];
for (const b of uniq.slice(0, 40)) console.log('BAD ' + b);
const kinds = {};
for (const b of uniq) { const k = b.split(' ')[1] + ' ' + (b.split(' ')[2] || '').replace(/^https?:\/\/[^/]+/, '').split('/').slice(0, 4).join('/'); kinds[k] = (kinds[k] || 0) + 1; }
for (const [k, n] of Object.entries(kinds).sort((a, b) => b[1] - a[1]).slice(0, 12)) console.log('KIND ' + n + ' ' + k);
console.log('scanned=' + scanned + ' bad=' + bad.length);
if (!scanned || bad.length) { console.log('CONTENT PATHS FAILED'); process.exit(1); }
console.log('CONTENT PATHS PASSED scanned=' + scanned);
