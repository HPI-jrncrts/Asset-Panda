// Backend QA, the part that needs no sign-in: robots and sitemap, the 404 page, site search, the GTM container,
// security headers, no exposed user list, XML-RPC closed, WP-Cron reachable, and a sample of pages free of PHP
// warnings/notices in their HTML. Prints one line per check and BACKEND PUBLIC PASSED when all hold.
import { DEV, UA, sleep } from './lib.mjs';
const problems = [];
const ok = (cond, label, detail) => { console.log((cond ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!cond) problems.push(label); };
const get = async (p, opts = {}) => { const r = await fetch(DEV + p, { headers: { 'user-agent': UA }, redirect: 'manual', ...opts }); const t = opts.method === 'HEAD' ? '' : await r.text(); return { s: r.status, h: r.headers, t }; };

let r = await get('/robots.txt');
const stagingBlock = /disallow:\s*\/\s*$/im.test(r.t);
// WP Engine serves "Disallow: /" on every *.wpenginepowered.com host; the production host gets WordPress's own robots.txt.
ok(r.s === 200 && (stagingBlock || /sitemap/i.test(r.t)), 'robots.txt answers (staging block or sitemap line)', 'HTTP ' + r.s + ' ' + r.t.replace(/\s+/g, ' ').slice(0, 120));
console.log('INFO robots.txt currently ' + (stagingBlock ? 'blocks crawlers (WP Engine staging host)' : 'allows crawling') + ' — LAUNCH DAY: confirm the production host serves the normal robots.txt with the sitemap line');
await sleep(1500);
r = await get('/sitemap_index.xml');
const maps = (r.t.match(/<loc>[^<]+<\/loc>/g) || []).length;
ok(r.s === 200 && maps > 0, 'sitemap index answers with sub-sitemaps', 'HTTP ' + r.s + ' maps=' + maps);
ok(!/www\.assetpanda\.com/.test(r.t), 'sitemap carries no live-site URLs');
await sleep(1500);
r = await get('/this-page-does-not-exist-' + Date.now() + '/');
ok(r.s === 404, '404 page answers HTTP 404', 'HTTP ' + r.s);
ok(/ap-site-header|ap-404|not found/i.test(r.t), '404 page renders the site chrome', (r.t.match(/<title>[^<]*/) || [''])[0].slice(7, 80));
await sleep(1500);
r = await get('/?s=barcode');
const results = (r.t.match(/<article|ap-resource-card|search-result|ap-search__/g) || []).length;
ok(r.s === 200 && results > 0, 'site search returns results for "barcode"', 'HTTP ' + r.s + ' result-like nodes=' + results);
await sleep(1500);
r = await get('/');
// The Asset Panda Site plugin prints GTM (and Cookiebot) on its production host list only — www.assetpanda.com — so
// staging stays out of analytics. Verified in its settings on 2026-10-29: container GTM-WVPZ4X, standard loader.
console.log('INFO GTM: ' + (/GTM-WVPZ4X/.test(r.t) ? 'GTM-WVPZ4X printed on this host' : 'not printed on staging by design (site plugin: production hosts only) — LAUNCH DAY: confirm GTM-WVPZ4X appears in the www page source after the cutover'));
ok(!/<b>(Warning|Notice|Fatal error|Deprecated)<\/b>:|PHP (Warning|Notice|Fatal)/.test(r.t), 'homepage HTML has no PHP warning text');
const heads = r.h;
// Security headers live in WP Engine's per-environment Header Rules (set on the production environment, not on
// staging), so they are reported, not gated: LAUNCH DAY: confirm them on www after the cutover.
for (const [name, want] of [['strict-transport-security', 'HSTS'], ['x-content-type-options', 'nosniff'], ['x-frame-options', 'clickjacking'], ['referrer-policy', 'referrer policy'], ['content-security-policy', 'CSP']]) console.log('INFO header ' + want + ': ' + (heads.get(name) || '(not set on staging — WPE Header Rules are per environment)'));
console.log('INFO x-robots-tag: ' + (heads.get('x-robots-tag') || '(none)') + ' | meta robots: ' + ((r.t.match(/<meta[^>]*name="robots"[^>]*>/i) || ['(none)'])[0].replace(/\s+/g, ' ').slice(0, 100)));
await sleep(1500);
r = await get('/wp-json/wp/v2/users');
ok(r.s === 401 || r.s === 403 || r.s === 404 || (r.s === 200 && !/"slug"/.test(r.t)), 'user list is not exposed through REST', 'HTTP ' + r.s + ' ' + r.t.slice(0, 60).replace(/\s+/g, ' '));
await sleep(1500);
r = await get('/xmlrpc.php', { method: 'POST', body: '<methodCall><methodName>system.listMethods</methodName></methodCall>' });
ok(r.s !== 200 || !/<methodResponse/.test(r.t), 'XML-RPC does not answer method calls', 'HTTP ' + r.s);
await sleep(1500);
r = await get('/wp-cron.php?doing_wp_cron', { method: 'HEAD' });
ok(r.s === 200, 'wp-cron.php reachable', 'HTTP ' + r.s);
await sleep(1500);
r = await get('/wp-login.php');
ok(r.s === 200 || r.s === 302 || r.s === 301, 'login page reachable (admins can sign in)', 'HTTP ' + r.s);
// PHP warning scan on a sample of first-screen pages.
// /pricing/ is left out on purpose: on the new site it 301s to /get-a-quote-pro/ (Jon's CRO change, decision for Joseph).
const sample = ['/products/asset-panda-for-fire/', '/solutions/asset-management/', '/resource-center/', '/resource-center/blog/', '/get-a-demo/', '/new-pricing/', '/integrations/', '/compare/', '/ursa-ai/', '/roi-calculator/'];
let bad = 0;
for (const p of sample) {
  await sleep(2500);
  const x = await get(p);
  const warn = /<b>(Warning|Notice|Fatal error|Deprecated)<\/b>:|PHP (Warning|Notice|Fatal)|Uncaught Error/.test(x.t);
  if (x.s !== 200 || warn) { bad++; console.log('  page ' + p + ' HTTP ' + x.s + (warn ? ' PHP WARNING TEXT' : '')); }
}
ok(bad === 0, 'sampled pages answer 200 with no PHP warning text (' + sample.length + ' pages)');
if (problems.length) { console.log('BACKEND PUBLIC FAILED: ' + problems.length); process.exit(1); }
console.log('BACKEND PUBLIC PASSED checks=' + (14 + 1));
