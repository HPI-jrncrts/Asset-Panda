#!/usr/bin/env node
/**
 * Motion audit.
 *
 * Three separate concerns, all of which have bitten this project:
 *
 *   A. SPEC CONFORMANCE — does the transition use the duration and easing the
 *      Figma motion layer specifies? "Animations are not on point" is almost
 *      always a 300ms ease where Figma says 550ms cubic-bezier(0.22,1,0.36,1).
 *
 *   B. DOES IT ACTUALLY FIRE — a transition declared on a property that never
 *      changes is invisible in code review and invisible in a screenshot.
 *
 *   C. JITTER — the hero shake. Caused by a scroll handler that reads
 *      getBoundingClientRect() and writes margin/left from it, so the element
 *      oscillates between two positions every frame. This samples element
 *      geometry across animation frames at a STATIONARY scroll offset: if the
 *      position moves while nothing is scrolling, there is a feedback loop.
 *
 * Usage: node tools/audit-motion.mjs --section platform-stack
 */

import {
  parseArgs, loadSpec, resolveUrl, preparePage, launchBrowser,
  cssEquals, makeReport, printReport, info,
} from './lib/shared.mjs';

/** "550ms" and "0.55s" are the same thing; compare in ms. */
function normaliseTime(v) {
  if (!v) return v;
  const s = String(v).trim();
  const n = parseFloat(s);
  if (Number.isNaN(n)) return s;
  return `${s.endsWith('ms') ? n : n * 1000}ms`;
}

/** Sample an element's position across N animation frames without scrolling. */
function sampleJitter({ selectors, frames }) {
  return new Promise((resolve) => {
    const targets = selectors.flatMap((sel) =>
      Array.from(document.querySelectorAll(sel)).slice(0, 5).map((el) => ({ sel, el }))
    );
    if (!targets.length) return resolve({ error: 'no jitter targets matched' });

    const samples = targets.map(({ sel, el }) => ({ sel, el, xs: [], ys: [], ws: [] }));
    let n = 0;
    const startScrollY = window.scrollY;

    function tick() {
      for (const s of samples) {
        const r = s.el.getBoundingClientRect();
        s.xs.push(r.left); s.ys.push(r.top); s.ws.push(r.width);
      }
      if (++n < frames) requestAnimationFrame(tick);
      else {
        const range = (a) => Math.max(...a) - Math.min(...a);
        resolve({
          scrollDrift: Math.abs(window.scrollY - startScrollY),
          results: samples.map((s) => ({
            sel: s.sel,
            tag: s.el.tagName.toLowerCase() + (s.el.id ? `#${s.el.id}` : ''),
            dx: +range(s.xs).toFixed(2),
            dy: +range(s.ys).toFixed(2),
            dw: +range(s.ws).toFixed(2),
          })),
        });
      }
    }
    requestAnimationFrame(tick);
  });
}

export async function auditMotion({ spec, url, browser }) {
  const report = makeReport(`Motion — ${spec.section}`);
  const own = !browser;
  const b = browser || (await launchBrowser());
  const width = spec.widths[0]; // motion is specified at the design width

  try {
    const page = await b.newPage();
    try {
      await page.goto(url, { waitUntil: 'networkidle', timeout: 45_000 });
      await preparePage(page, { width });

      /* ---- A + B. Declared motion ---------------------------------- */
      for (const m of spec.motion || []) {
        const locator = page.locator(`${spec.selector} ${m.selector}`).first();
        if (!(await locator.count())) {
          report.add(false, `${m.selector} — not found`,
            `The spec declares motion here but nothing matches inside ${spec.selector}.`);
          continue;
        }

        await locator.scrollIntoViewIfNeeded().catch(() => {});
        await page.waitForTimeout(150);

        const before = await locator.evaluate((el, prop) => {
          const cs = getComputedStyle(el);
          return {
            value: cs.getPropertyValue(prop),
            duration: cs.transitionDuration,
            easing: cs.transitionTimingFunction,
            animDuration: cs.animationDuration,
            animEasing: cs.animationTimingFunction,
          };
        }, m.prop);

        if (m.duration) {
          const candidates = [before.duration, before.animDuration]
            .flatMap((d) => (d || '').split(',').map((s) => s.trim()));
          const match = candidates.some((c) => cssEquals(normaliseTime(c), normaliseTime(m.duration)));
          report.add(match, `${m.selector} { ${m.prop} } duration`,
            `Figma specifies ${m.duration}; element reports transition ${before.duration || 'none'}` +
            `${before.animDuration && before.animDuration !== '0s' ? `, animation ${before.animDuration}` : ''}`);
        }

        if (m.easing) {
          const candidates = [before.easing, before.animEasing]
            .flatMap((e) => (e || '').split(/,(?![^(]*\))/).map((s) => s.trim()));
          const match = candidates.some((c) => cssEquals(c, m.easing));
          report.add(match, `${m.selector} { ${m.prop} } easing`,
            `Figma specifies ${m.easing}; element reports ${before.easing || before.animEasing || 'none'}`);
        }

        /* B. Fire the trigger and confirm the property genuinely changes. */
        if (m.trigger && m.trigger !== 'none') {
          try {
            if (m.trigger === 'hover') await locator.hover({ timeout: 5000 });
            else if (m.trigger === 'click') await locator.click({ timeout: 5000 });
            else if (m.trigger === 'focus') await locator.focus({ timeout: 5000 });
            else if (m.trigger === 'scroll') await page.evaluate(() => window.scrollBy(0, window.innerHeight * 0.6));

            const ms = parseFloat(normaliseTime(m.duration || '400ms')) || 400;
            await page.waitForTimeout(Math.max(ms + 200, 400));

            const after = await locator.evaluate((el, prop) => getComputedStyle(el).getPropertyValue(prop), m.prop);

            report.add(after !== before.value, `${m.selector} { ${m.prop} } responds to ${m.trigger}`,
              `Value is "${after}" both before and after the ${m.trigger}. The transition is ` +
              `declared but the property never changes — the animation is dead code.`);
          } catch (e) {
            report.add(false, `${m.selector} — could not apply trigger "${m.trigger}"`,
              String(e.message).split('\n')[0]);
          }
        }
      }

      /* ---- C. Jitter / layout feedback loop ------------------------ */
      const watch = spec.jitterWatch?.length ? spec.jitterWatch : [spec.selector];
      const positions = spec.jitterScrollPositions || [0, 0.25, 0.5];

      for (const frac of positions) {
        await page.evaluate((f) => window.scrollTo(0, document.documentElement.scrollHeight * f), frac);
        await page.waitForTimeout(500); // let legitimate transitions settle

        const j = await page.evaluate(sampleJitter, { selectors: watch, frames: 30 });
        if (j.error) { report.addWarning(`jitter probe @${frac}: ${j.error}`); continue; }
        if (j.scrollDrift > 2) continue; // page still settling; not a fair sample

        for (const r of j.results) {
          const moved = Math.max(r.dx, r.dy, r.dw);
          report.add(moved <= 0.5,
            `no jitter @scroll ${Math.round(frac * 100)}% — ${r.tag}`,
            `Element moved ${r.dx}px x / ${r.dy}px y / ${r.dw}px w across 30 frames with the page ` +
            `stationary. This is a read-then-write layout feedback loop: a scroll or resize handler ` +
            `is reading getBoundingClientRect() and setting margin/left/width from it. Move the ` +
            `positioning into CSS (calc(50% - 50vw), position: sticky).`);
        }
      }

      /* ---- D. prefers-reduced-motion ------------------------------- */
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.reload({ waitUntil: 'networkidle' });
      const stillAnimating = await page.evaluate((rootSel) => {
        const root = document.querySelector(rootSel);
        if (!root) return 0;
        return Array.from(root.querySelectorAll('*')).filter((el) => {
          const cs = getComputedStyle(el);
          return cs.animationName !== 'none'
            && (parseFloat(cs.animationDuration) || 0) > 0.15
            && cs.animationIterationCount === 'infinite';
        }).length;
      }, spec.selector);

      if (stillAnimating > 0) {
        report.addWarning(
          `${stillAnimating} element(s) still run infinite animations under prefers-reduced-motion`,
          `Add a @media (prefers-reduced-motion: reduce) block that sets animation: none.`);
      }
    } finally {
      await page.close();
    }
  } finally {
    if (own) await b.close();
  }

  return report;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = parseArgs();
  if (!args.section) {
    console.error('Usage: node tools/audit-motion.mjs --section <name> [--target preview|dev|live]');
    process.exit(2);
  }
  const spec = loadSpec(args.section);
  const url = resolveUrl(args.target || 'preview', spec, args);
  info(`Auditing ${url}`);
  const report = await auditMotion({ spec, url });
  process.exit(printReport(report) ? 0 : 1);
}
