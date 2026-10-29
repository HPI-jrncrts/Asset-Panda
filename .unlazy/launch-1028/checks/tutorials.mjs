// /tutorials/ deck gallery (contract C35, second pass 2026-10-29): a large player with the other videos as a deck
// beside it (below on phones); choosing a card makes it current and loads that video from youtube-nocookie; the YouTube
// API is used for muted autoplay + advance; no horizontal overflow.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const problems = [];
const ok = (c, label, detail) => { console.log((c ? 'PASS ' : 'FAIL ') + label + (detail ? ' — ' + detail : '')); if (!c) problems.push(label); };
const browser = await launch();
for (const width of [1440, 390]) {
  const p = await browser.newPage({ viewport: { width, height: 900 } });
  await guardPage(p);
  await p.goto(DEV + '/tutorials/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1500);
  const lay = await p.evaluate(() => {
    const s = document.querySelector('[data-ap-pv-screen]'); const d = document.querySelector('[data-ap-pv-cards]');
    const sr = s && s.getBoundingClientRect(), dr = d && d.getBoundingClientRect();
    return { screenW: sr ? Math.round(sr.width) : 0, cards: document.querySelectorAll('[data-ap-pv-card]').length, beside: sr && dr ? dr.left >= sr.right - 2 : null, below: sr && dr ? dr.top >= sr.bottom - 2 : null, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth };
  });
  ok(lay.cards >= 2, width + ': deck has the videos', JSON.stringify(lay));
  ok(!lay.overflow, width + ': no horizontal overflow');
  if (width === 1440) ok(lay.beside === true && lay.screenW >= 900, '1440: large player with the deck beside it', 'player ' + lay.screenW + 'px');
  else ok(lay.below === true, '390: deck under the player');
  const target = await p.evaluate(() => { const c = [...document.querySelectorAll('[data-ap-pv-card]')].find((e) => e.getAttribute('aria-current') !== 'true' && e.getBoundingClientRect().width > 0 && e.getBoundingClientRect().height > 0); if (!c) return null; c.setAttribute('data-qa-pick', '1'); return { idx: c.getAttribute('data-ap-pv-card'), id: c.getAttribute('data-ap-pv-id') }; });
  if (target) {
    await p.locator('[data-qa-pick="1"]').scrollIntoViewIfNeeded();
    await p.locator('[data-qa-pick="1"]').click();
    await p.waitForTimeout(2500);
    const after = await p.evaluate((t) => { const cur = document.querySelector('[data-ap-pv-card="' + t.idx + '"]'); const f = document.querySelector('[data-ap-pv-screen] iframe'); const cap = document.querySelector('[data-ap-pv-caption="' + t.idx + '"]'); return { current: cur && cur.getAttribute('aria-current'), src: f ? f.getAttribute('src') : null, captionShown: !!cap && !cap.hidden && cap.getBoundingClientRect().height > 0 }; }, target);
    ok(after.current === 'true', width + ': chosen card becomes the current video', JSON.stringify(after.current));
    // The YouTube API switches videos with loadVideoById, so the frame keeps its first address once autoplay made it;
    // the chosen video shows as the visible caption.
    ok(!!after.src && /youtube-nocookie\.com/.test(after.src) && after.captionShown, width + ': privacy-mode player shows the chosen video', JSON.stringify({ src: String(after.src).slice(0, 60), captionShown: after.captionShown }));
  } else ok(false, width + ': a visible card to choose');
  await p.close();
  await sleep(3500);
}
await browser.close();
if (problems.length) { console.log('TUTORIALS FAILED: ' + problems.length); process.exit(1); }
console.log('TUTORIALS PASSED');
