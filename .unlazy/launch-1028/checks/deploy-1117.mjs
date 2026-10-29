// Kit 1.3.116/117 + content 1.3.5 on dev (contracts C29, C30, C38): Ursa title gradient, Ursa sections without scroll
// motion and at body weight, the demo frame wired on the Ursa page, the AIP refraction band / orb float / phone art,
// hub link cards without dead space, and no running text at weight 300 in kit sections or blog bodies.
// Token DEPLOY 1117 PASSED.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
const open = async (path, width = 1440) => {
  const p = await browser.newPage({ viewport: { width, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + path + (path.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1800);
  return p;
};
const thin = (p) => p.evaluate(() => [...document.querySelectorAll('main p, main li')].filter((e) => (e.textContent || '').trim().length >= 40 && e.closest('[class^="ap-"], [class*=" ap-"], .ap26-article') && getComputedStyle(e).display !== 'none').filter((e) => parseInt(getComputedStyle(e).fontWeight, 10) < 400).map((e) => (e.className || e.parentElement.className || '').toString().slice(0, 40) + ':' + getComputedStyle(e).fontWeight).slice(0, 6));
{
  const p = await open('/ursa-ai/');
  const r = await p.evaluate(() => {
    const u = document.querySelector('.ap-orbit-hero__title .ap-ursa');
    const cs = u ? getComputedStyle(u) : null;
    const steps = document.querySelector('.ap-ursa-steps');
    const stepsAnim = steps ? [...steps.querySelectorAll('*')].filter((e) => { const s = getComputedStyle(e); return (s.animationTimeline && s.animationTimeline !== 'auto' && s.animationTimeline !== 'none') || e.hasAttribute('data-ap-reveal'); }).length : -1;
    const frame = document.querySelector('.ap-ursa-inspect iframe');
    return { grad: cs ? /linear-gradient/.test(cs.backgroundImage) && /text/.test(cs.backgroundClip || cs.webkitBackgroundClip) : false, stepsAnim, frame: frame ? frame.getAttribute('src') || frame.getAttribute('data-src') : null, beam: !!document.querySelector('.ap-continuity-columns__beam') };
  });
  ok(r.grad, 'Ursa hero: "Ursa AI" in the title is gradient text');
  ok(r.stepsAnim === 0, 'Ursa steps: no scroll-linked or reveal motion', 'count=' + r.stepsAnim);
  ok(!!r.frame && /cloudfront\.net/.test(r.frame), 'Ursa inspect: the live demo frame is wired (poster stays where the host refuses staging)', 'src=' + r.frame);
  ok(r.beam, 'Ursa "See what needs attention": refraction band present');
  const t = await thin(p);
  ok(t.length === 0, '/ursa-ai/: no running text under weight 400', JSON.stringify(t));
  await p.close(); await sleep(3500);
}
{
  const p = await open('/asset-intelligence-platform/');
  const r = await p.evaluate(() => {
    const beam = document.querySelector('.ap-continuity-columns__beam');
    const orb = document.querySelector('.ap-capability-scene__orb');
    const rule = document.querySelector('.ap-continuity-columns__rule');
    const foot = document.querySelector('.ap-continuity-columns__foot');
    return { beamAnim: beam ? (getComputedStyle(beam, '::before').animationName || getComputedStyle(beam).animationName) : null, orbAnim: orb ? getComputedStyle(orb).animationName : null, footGap: rule && foot ? Math.round(foot.getBoundingClientRect().top - rule.getBoundingClientRect().bottom) : null };
  });
  ok(r.beamAnim && r.beamAnim !== 'none', 'AIP continuity: the band on the arrow animates with scroll', 'anim=' + r.beamAnim);
  ok(r.orbAnim && r.orbAnim !== 'none', 'AIP capability scene: Ursa orb floats', 'anim=' + r.orbAnim);
  ok(r.footGap !== null && r.footGap <= 12, 'AIP continuity: closing line sits just under the arrow', 'box gap=' + r.footGap + 'px');
  const t = await thin(p);
  ok(t.length === 0, '/asset-intelligence-platform/: no running text under weight 400', JSON.stringify(t));
  await p.close(); await sleep(3500);
  const m = await open('/asset-intelligence-platform/', 390);
  const a = await m.evaluate(() => { const art = document.querySelector('.ap-asset-scene__art'); const r = art ? art.getBoundingClientRect() : null; return art ? { vis: getComputedStyle(art).display !== 'none' && r.height > 0, src: (art.currentSrc || art.src || '').split('/').pop() } : null; });
  ok(a && a.vis, 'AIP asset scene at 390: art with connector lines shown', JSON.stringify(a));
  await m.close(); await sleep(3500);
}
{
  const p = await open('/solutions/education/');
  const r = await p.evaluate(() => [...document.querySelectorAll('.ap-link-cards a[href]')].filter((a) => /Explore/i.test(a.textContent)).map((a) => { let card = a.parentElement; while (card && card.getBoundingClientRect().height < 200) card = card.parentElement; return card ? Math.round(card.getBoundingClientRect().bottom - a.getBoundingClientRect().bottom) : null; }));
  ok(r.length > 0 && r.every((g) => g !== null && g <= 48), 'education hub link cards: link sits ≤48px above the card edge', JSON.stringify(r));
  await p.close(); await sleep(3500);
}
{
  const p = await open('/resource-center/blog/furniture-inventory-asset-management-makes-a-difference-to-your-bottom-line/');
  const t = await thin(p);
  ok(t.length === 0, 'blog post body: no running text under weight 400', JSON.stringify(t));
  await p.close(); await sleep(3500);
  const h = await open('/');
  const th = await thin(h);
  ok(th.length === 0, 'homepage: no running text under weight 400 (comparison grey column included)', JSON.stringify(th));
  await h.close();
}
await browser.close();
if (problems.length) { console.log('DEPLOY 1117 FAILED: ' + problems.length); process.exit(1); }
console.log('DEPLOY 1117 PASSED');
