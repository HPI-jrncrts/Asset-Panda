#!/usr/bin/env node
/**
 * Design-token conformance audit.
 *
 * This is the check that makes "matches Figma" objective. A pixel diff tells you
 * something is off by a bit; this tells you the heading is 22px when the token
 * says 24px — which is the actual bug.
 *
 * Two layers:
 *   A. Explicit assertions from the spec (authoritative, fail on mismatch)
 *   B. A palette/scale sweep over the whole section (warn on off-system values)
 *
 * Usage: node tools/audit-tokens.mjs --section platform-stack [--strict]
 */

import {
  parseArgs, loadSpec, loadTokens, resolveUrl, preparePage, launchBrowser,
  cssEquals, normalizeColor, makeReport, printReport, info, isMainModule,
} from './lib/shared.mjs';

/** Read one computed property off the first match of a selector. */
function readComputed({ rootSelector, selector, prop }) {
  const root = document.querySelector(rootSelector);
  if (!root) return { error: `root ${rootSelector} not found` };
  const el = selector ? root.querySelector(selector) : root;
  if (!el) return { error: `no match for "${selector}" inside ${rootSelector}` };
  return { value: getComputedStyle(el).getPropertyValue(prop).trim() };
}

/** Sweep every visible element and collect the values actually in use. */
function sweepUsedValues({ rootSelector }) {
  const root = document.querySelector(rootSelector);
  if (!root) return { error: `root ${rootSelector} not found` };

  const seen = { fontSize: {}, color: {}, background: {}, radius: {}, spacing: {} };
  const bump = (bucket, value, el) => {
    if (!value || ['none','0px','normal','auto','rgba(0, 0, 0, 0)'].includes(value)) return;
    (seen[bucket][value] ||= []).push(
      el.tagName.toLowerCase() +
      (typeof el.className === 'string' && el.className
        ? '.' + el.className.trim().split(/\s+/)[0] : '')
    );
  };

  for (const el of root.querySelectorAll('*')) {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) continue;

    const hasOwnText = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
    if (hasOwnText) { bump('fontSize', cs.fontSize, el); bump('color', cs.color, el); }
    bump('background', cs.backgroundColor, el);
    bump('radius', cs.borderRadius, el);
    for (const p of ['gap', 'paddingTop', 'paddingBottom', 'marginTop', 'marginBottom']) {
      bump('spacing', cs[p], el);
    }
  }

  for (const bucket of Object.keys(seen)) {
    for (const v of Object.keys(seen[bucket])) {
      seen[bucket][v] = [...new Set(seen[bucket][v])].slice(0, 3);
    }
  }
  return seen;
}

export async function auditTokens({ spec, url, browser, strict = false }) {
  const report = makeReport(`Token conformance — ${spec.section}`);
  const tokens = loadTokens();
  const own = !browser;
  const b = browser || (await launchBrowser());

  // Allowed sets come straight from tokens.css — the same file the CSS uses.
  const pick = (prefix) => new Set(
    Object.entries(tokens).filter(([k]) => k.startsWith(prefix)).map(([, v]) => v.trim())
  );
  const allowedFontSizes = pick('--ap-fs-');
  const allowedRadii = pick('--ap-radius-');
  const allowedSpacing = pick('--ap-space-');
  const allowedColors = new Set(
    Object.entries(tokens)
      .filter(([, v]) => /^#[0-9a-f]{3,8}$/i.test(v.trim()))
      .map(([, v]) => normalizeColor(v.trim()))
  );

  try {
    for (const width of spec.widths) {
      const page = await b.newPage();
      try {
        // see audit-layout.mjs: 'networkidle' hangs on real AP pages with live
        // tracking beacons. domcontentloaded + preparePage's settle wait instead.
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45_000 });
        await preparePage(page, { width });

        /* ---- A. Explicit assertions -------------------------------- */
        for (const a of spec.assert || []) {
          if (a.widths && !a.widths.includes(width)) continue;

          const expected = a.token ? tokens[a.token] : (a.equals ?? a.value);

          if (a.token && expected === undefined) {
            report.add(false, `@${width}px  unknown token ${a.token}`,
              `Referenced by the assertion on "${a.selector}". Add it to ` +
              `ui/src/tokens.css or correct the name.`);
            continue;
          }

          const res = await page.evaluate(readComputed, {
            rootSelector: spec.selector, selector: a.selector, prop: a.prop,
          });

          if (res.error) {
            report.add(false, `@${width}px  ${a.selector} { ${a.prop} } — ${res.error}`);
            continue;
          }

          report.add(
            cssEquals(res.value, expected),
            `@${width}px  ${a.selector} { ${a.prop} }`,
            `expected ${expected}${a.token ? ` (${a.token})` : ''}, got ${res.value}`
          );
        }

        /* ---- B. Off-system sweep (widest breakpoint only) ----------- */
        if (width === spec.widths[0]) {
          const used = await page.evaluate(sweepUsedValues, { rootSelector: spec.selector });
          if (!used.error) {
            const offSystem = [];

            for (const [v, els] of Object.entries(used.fontSize)) {
              if (!allowedFontSizes.has(v)) offSystem.push(`font-size ${v}  → ${els.join(', ')}`);
            }
            for (const [v, els] of Object.entries(used.radius)) {
              if (!allowedRadii.has(v) && !v.includes(' ')) offSystem.push(`border-radius ${v}  → ${els.join(', ')}`);
            }
            for (const [v, els] of Object.entries(used.spacing)) {
              if (!allowedSpacing.has(v)) offSystem.push(`spacing ${v}  → ${els.join(', ')}`);
            }
            for (const [v, els] of Object.entries({ ...used.color, ...used.background })) {
              const n = normalizeColor(v);
              if (n && !allowedColors.has(n) && !n.startsWith('rgba(')) {
                offSystem.push(`colour ${v}  → ${els.join(', ')}`);
              }
            }

            const detail = offSystem.slice(0, 20).join('\n        ') +
              (offSystem.length > 20 ? `\n        …and ${offSystem.length - 20} more` : '');

            if (strict || spec.strictTokens) {
              report.add(offSystem.length === 0, `${offSystem.length} off-system value(s) in use`, detail);
            } else if (offSystem.length) {
              report.addWarning(
                `${offSystem.length} value(s) not in the token scale`,
                detail + `\n        (run with --strict to fail on these)`
              );
            }
          }
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
    console.error('Usage: node tools/audit-tokens.mjs --section <name> [--strict] [--target preview|dev|live]');
    process.exit(2);
  }
  const spec = loadSpec(args.section);
  const url = resolveUrl(args.target || 'preview', spec, args);
  info(`Auditing ${url}`);
  const report = await auditTokens({ spec, url, strict: !!args.strict });
  process.exit(printReport(report) ? 0 : 1);
}
