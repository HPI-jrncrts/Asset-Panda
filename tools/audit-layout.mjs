#!/usr/bin/env node
/**
 * Layout invariant audit.
 *
 * Targets the failure class reported on this project: "some layouts are
 * overlapping". A human scanning a page misses a 6px collision at 1024px.
 * A geometry check does not.
 *
 * Checks, at every breakpoint:
 *   1. Document does not scroll horizontally
 *   2. No element escapes the section's horizontal bounds
 *   3. No two in-flow siblings in a noOverlap group intersect
 *   4. No text is clipped by an overflow-hidden ancestor
 *   5. No visible element has collapsed to zero height while holding content
 *   6. No interactive target is smaller than 24x24 (WCAG 2.2 SC 2.5.8)
 *
 * Usage: node tools/audit-layout.mjs --section platform-stack [--target preview]
 */

import {
  parseArgs, loadSpec, resolveUrl, preparePage, launchBrowser,
  makeReport, printReport, info, isMainModule,
} from './lib/shared.mjs';

/* Runs inside the page. Pure geometry — no framework assumptions. */
function collectLayoutFacts({ rootSelector, noOverlapSelectors, bleedOkSelectors = [], collapsedOkSelectors = [] }) {
  const root = document.querySelector(rootSelector);
  if (!root) return { error: `Section root not found: ${rootSelector}` };

  const rootRect = root.getBoundingClientRect();
  const out = {
    docScrollWidth: document.documentElement.scrollWidth,
    innerWidth: window.innerWidth,
    overflowers: [], overlaps: [], clipped: [], collapsed: [], smallTargets: [],
  };

  const describe = (el) => {
    const id = el.id ? `#${el.id}` : '';
    const cls = typeof el.className === 'string' && el.className
      ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '';
    const text = (el.textContent || '').trim().slice(0, 40);
    return `${el.tagName.toLowerCase()}${id}${cls}${text ? ` "${text}…"` : ''}`;
  };

  const isVisible = (el) => {
    const s = getComputedStyle(el);
    if (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 || r.height > 0;
  };

  const all = Array.from(root.querySelectorAll('*')).filter(isVisible);

  /* 2. Elements escaping the section horizontally.
        Deliberate full-bleed is a correct pattern, so allow anything that
        spans the full viewport width. An element whose ancestor (up to and
        including the section root) clips horizontally cannot visually escape
        no matter what its geometry says — marquee tracks and decorative
        orb/glow bleed live inside such clips by design. `bleedOk` lets a spec
        exempt intentional bleed that is NOT clipped (e.g. soft glows). */
  const horizontallyClipped = (el) => {
    for (let a = el.parentElement; a; a = a.parentElement) {
      const o = getComputedStyle(a).overflowX;
      if (o === 'hidden' || o === 'clip') return true;
      if (a === root) break;
    }
    return false;
  };
  for (const el of all) {
    const s = getComputedStyle(el);
    if (s.position === 'fixed') continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0) continue;
    if (r.width >= window.innerWidth - 2) continue;
    if (horizontallyClipped(el)) continue;
    if (bleedOkSelectors.some((sel) => { try { return el.closest(sel); } catch (e) { return false; } })) continue;
    if (r.right > rootRect.right + 1 || r.left < rootRect.left - 1) {
      out.overflowers.push({
        el: describe(el),
        rect: { left: Math.round(r.left), right: Math.round(r.right) },
        bounds: { left: Math.round(rootRect.left), right: Math.round(rootRect.right) },
      });
    }
  }

  /* 3. Pairwise overlap within each noOverlap group.
        In-flow elements only (static/relative). Absolutely positioned art is
        supposed to overlap — flagging it would make the check useless. */
  for (const sel of noOverlapSelectors) {
    const els = Array.from(root.querySelectorAll(sel)).filter((el) => {
      if (!isVisible(el)) return false;
      const p = getComputedStyle(el).position;
      return p === 'static' || p === 'relative';
    });
    for (let i = 0; i < els.length; i++) {
      for (let j = i + 1; j < els.length; j++) {
        const a = els[i], b = els[j];
        if (a.contains(b) || b.contains(a)) continue;
        const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
        const overlapX = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left);
        const overlapY = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top);
        // 1px tolerance absorbs sub-pixel rounding on fractional layouts.
        if (overlapX > 1 && overlapY > 1) {
          out.overlaps.push({
            selector: sel, a: describe(a), b: describe(b),
            overlap: { x: Math.round(overlapX), y: Math.round(overlapY) },
          });
        }
      }
    }
  }

  /* 4. Text clipped by an overflow-hidden box. */
  for (const el of all) {
    const s = getComputedStyle(el);
    if (s.overflow !== 'hidden' && s.overflowY !== 'hidden') continue;
    if (s.webkitLineClamp && s.webkitLineClamp !== 'none') continue; // intentional clamp
    if (!el.textContent?.trim()) continue;
    if (el.scrollHeight > el.clientHeight + 2 && el.clientHeight > 0) {
      out.clipped.push({ el: describe(el), scrollHeight: el.scrollHeight, clientHeight: el.clientHeight });
    }
  }

  /* 5. Collapsed containers that still hold content. `collapsedOk` exempts
        containers that collapse interactively by design (accordion answers). */
  for (const el of all) {
    const r = el.getBoundingClientRect();
    if (collapsedOkSelectors.some((sel) => { try { return el.closest(sel); } catch (e) { return false; } })) continue;
    if (r.height === 0 && el.children.length > 0 && el.textContent?.trim()) {
      out.collapsed.push({ el: describe(el) });
    }
  }

  /* 6. Touch/click target size. */
  for (const el of root.querySelectorAll('a, button, [role="button"], input, select')) {
    if (!isVisible(el)) continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    if (r.width < 24 || r.height < 24) {
      out.smallTargets.push({ el: describe(el), size: `${Math.round(r.width)}x${Math.round(r.height)}` });
    }
  }

  return out;
}

export async function auditLayout({ spec, url, browser }) {
  const report = makeReport(`Layout invariants — ${spec.section}`);
  const own = !browser;
  const b = browser || (await launchBrowser());

  try {
    for (const width of spec.widths) {
      const page = await b.newPage();
      try {
        // 'networkidle' hangs indefinitely on real pages carrying GTM/HubSpot/
        // analytics beacons that never stop polling — confirmed live 2026-08-31
        // against the Fire page (45s timeout, every time). 'domcontentloaded' +
        // preparePage's fonts-ready/settle wait is the reliable combination.
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45_000 });
        await preparePage(page, { width });

        const facts = await page.evaluate(collectLayoutFacts, {
          rootSelector: spec.selector,
          noOverlapSelectors: spec.noOverlap || [],
          bleedOkSelectors: spec.bleedOk || [],
          collapsedOkSelectors: spec.collapsedOk || [],
        });

        if (facts.error) { report.add(false, `@${width}px  ${facts.error}`); continue; }

        report.add(
          facts.docScrollWidth <= facts.innerWidth + 1,
          `@${width}px  horizontal scroll`,
          `document scrollWidth ${facts.docScrollWidth} > viewport ${facts.innerWidth}. ` +
          `Usually a 100vw element inside a padded container, or a negative margin.`
        );

        report.add(
          facts.overflowers.length === 0,
          `@${width}px  ${facts.overflowers.length} element(s) escape the section bounds`,
          facts.overflowers.slice(0, 5)
            .map((o) => `${o.el} spans ${o.rect.left}→${o.rect.right}, section is ${o.bounds.left}→${o.bounds.right}`)
            .join('\n        ')
        );

        report.add(
          facts.overlaps.length === 0,
          `@${width}px  ${facts.overlaps.length} overlapping element pair(s)`,
          facts.overlaps.slice(0, 5)
            .map((o) => `${o.a}\n          collides with ${o.b}  (${o.overlap.x}x${o.overlap.y}px)`)
            .join('\n        ')
        );

        report.add(
          facts.clipped.length === 0,
          `@${width}px  ${facts.clipped.length} element(s) with clipped text`,
          facts.clipped.slice(0, 5)
            .map((c) => `${c.el} — content ${c.scrollHeight}px in a ${c.clientHeight}px box`)
            .join('\n        ')
        );

        report.add(
          facts.collapsed.length === 0,
          `@${width}px  ${facts.collapsed.length} collapsed container(s)`,
          facts.collapsed.slice(0, 5).map((c) => c.el).join('\n        ')
        );

        if (facts.smallTargets.length) {
          report.addWarning(
            `@${width}px  ${facts.smallTargets.length} tap target(s) under 24x24 (WCAG 2.2 SC 2.5.8)`,
            facts.smallTargets.slice(0, 5).map((t) => `${t.el} — ${t.size}`).join('\n        ')
          );
        }
      } finally {
        await page.close();
      }
    }
  } finally {
    if (own) await b.close();
  }

  return report;
}

if (isMainModule(import.meta.url)) {
  const args = parseArgs();
  if (!args.section) {
    console.error('Usage: node tools/audit-layout.mjs --section <name> [--target preview|dev|live] [--url <url>]');
    process.exit(2);
  }
  const spec = loadSpec(args.section);
  const url = resolveUrl(args.target || 'preview', spec, args);
  info(`Auditing ${url}`);
  const report = await auditLayout({ spec, url });
  process.exit(printReport(report) ? 0 : 1);
}
