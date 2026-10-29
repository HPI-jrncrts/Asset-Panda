// The integrations hub answers 200 with the seven filters and 40 directory cards; every integration page in
// pages/integrations-slugs.json answers 200 with hero, title bar, detail, FAQ, more-integrations, awards and
// closing sections, its hero logo loads, and nothing references the live site.
import fs from 'node:fs';
import { DEV, SCOPE, fetchPage, head, sleep } from './lib.mjs';
const slugs = JSON.parse(fs.readFileSync(SCOPE + '/pages/integrations-slugs.json', 'utf8'));
const CATS = ['Identity & access', 'IT & device management', 'Collaboration', 'Productivity', 'Finance & ERP', 'Automation', 'Custom Connections'];
const problems = [];
const hub = await fetchPage('/integrations/');
if (hub.status !== 200) problems.push('hub HTTP ' + hub.status);
else {
  const h = hub.text.replace(/&amp;/g, '&');
  const cards = (h.match(/class="[^"]*ap-integration-directory__card(?:\s|")/g) || []).length;
  if (cards < 40) problems.push('hub cards ' + cards + ' < 40');
  for (const c of CATS) if (!h.includes(c)) problems.push('hub filter missing ' + c);
  if (/href=["']https?:\/\/(www\.)?assetpanda\.com/.test(h)) problems.push('hub links to the live site');
}
await sleep(1500);
const parts = ['ap-integration-hero', 'ap-integration-titlebar', 'ap-integration-detail', 'ap-faq', 'ap-integration-more', 'ap-awards', 'ap-platform-cta'];
for (const slug of slugs) {
  const p = '/integrations/' + slug + '/';
  const r = await fetchPage(p);
  if (r.status !== 200) { problems.push(p + ' HTTP ' + r.status); await sleep(1500); continue; }
  for (const c of parts) if (!r.text.includes('class="' + c) && !r.text.includes(' ' + c + ' ') && !r.text.includes('"' + c + ' ')) problems.push(p + ' missing ' + c);
  if (/href=["']https?:\/\/(www\.)?assetpanda\.com/.test(r.text)) problems.push(p + ' links to the live site');
  const logo = (r.text.match(/class="ap-integration-hero__logo"[^>]*src="([^"]+)"/) || r.text.match(/src="([^"]+)"[^>]*class="ap-integration-hero__logo"/) || [])[1];
  if (!logo) problems.push(p + ' no hero logo');
  else { const s = await head(logo.startsWith('/') ? DEV + logo : logo); if (s !== 200) problems.push(p + ' logo ' + s + ' ' + logo.slice(0, 80)); }
  await sleep(1400);
}
for (const x of problems) console.log('PROBLEM ' + x);
console.log('pages=' + (slugs.length + 1) + ' problems=' + problems.length);
if (problems.length) { console.log('INTEGRATIONS CHECK FAILED'); process.exit(1); }
console.log('INTEGRATIONS CHECK PASSED pages=' + (slugs.length + 1));
