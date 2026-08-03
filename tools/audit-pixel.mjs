#!/usr/bin/env node
/**
 * Pixel diff against the Figma reference export.
 *
 * IMPORTANT — read before trusting a number from this tool.
 *
 * A pixel diff between a Figma PNG and a browser render is the WEAKEST of the
 * four checks, because text rasterises differently in Figma and in Chromium.
 * A 1–2% mismatch on a text-heavy section is normal and means nothing.
 *
 * Its real job is catching STRUCTURAL divergence the other three audits cannot
 * see: a missing element, a section 200px too tall, art in the wrong place, a
 * background that never applied.
 *
 * Never "fix" a diff percentage by nudging values. Read the diff PNG, find the
 * structural cause, fix that. If layout/tokens/motion all pass and the diff is
 * still high, the reference is probably stale — re-export from Figma.
 *
 * Usage: node tools/audit-pixel.mjs --section platform-stack [--update-refs]
 */
import fs from 'node:fs';
import path from 'node:path';

import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import {
  ROOT, parseArgs, loadSpec, resolveUrl, preparePage, launchBrowser,
  makeReport, printReport, info,
} from './lib/shared.mjs';

const REFS = path.join(ROOT, 'refs');
const REPORTS = path.join(ROOT, 'reports');

export async function auditPixel({ spec, url, browser, updateRefs = false }) {
  const report = makeReport(`Pixel diff vs Figma — ${spec.section}`);
  fs.mkdirSync(REFS, { recursive: true });
  fs.mkdirSync(REPORTS, { recursive: true });

  const own = !browser;
  const b = browser || (await launchBrowser());
  const threshold = spec.pixelThreshold ?? 2.0;

  try {
    for (const width of spec.widths) {
      const refPath = path.join(REFS, `${spec.section}@${width}.png`);
      const page = await b.newPage();

      try {
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45_000 });
        // Reduced motion so a mid-flight transition doesn't poison the capture.
        await preparePage(page, { width, reducedMotion: true });
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(400);

        const el = page.locator(spec.selector).first();
        if (!(await el.count())) {
          report.add(false, `@${width}px  ${spec.selector} not found on the page`);
          continue;
        }

        const shotPath = path.join(REPORTS, `actual-${spec.section}@${width}.png`);
        await el.screenshot({ path: shotPath });

        if (!fs.existsSync(refPath) || updateRefs) {
          if (!fs.existsSync(refPath)) {
            report.addWarning(
              `@${width}px  no Figma reference at refs/${spec.section}@${width}.png`,
              `Export it with the Figma MCP:\n` +
              `        get_screenshot on node ${spec.node || '<node-id>'} at width ${width}\n` +
              `        save to refs/${spec.section}@${width}.png\n` +
              `        Until then, only layout/tokens/motion are enforced at this width.`);
          }
          continue;
        }

        const actual = PNG.sync.read(fs.readFileSync(shotPath));
        const expected = PNG.sync.read(fs.readFileSync(refPath));

        /* A size delta IS a finding — report it rather than silently cropping. */
        const dh = Math.abs(actual.height - expected.height);
        const dw = Math.abs(actual.width - expected.width);
        if (dh > 4 || dw > 4) {
          report.add(false, `@${width}px  rendered size differs from Figma`,
            `Figma ${expected.width}x${expected.height}, rendered ${actual.width}x${actual.height} ` +
            `(${dw}px wide / ${dh}px tall off). A height delta usually means wrong section padding, ` +
            `a wrapped grid, or a line-height mismatch compounding down the section.`);
        }

        /* Compare the shared region so a height delta still yields a usable diff. */
        const w = Math.min(actual.width, expected.width);
        const h = Math.min(actual.height, expected.height);
        const crop = (src) => {
          if (src.width === w && src.height === h) return src;
          const out = new PNG({ width: w, height: h });
          PNG.bitblt(src, out, 0, 0, w, h, 0, 0);
          return out;
        };
        const a = crop(actual);
        const e = crop(expected);
        const diff = new PNG({ width: w, height: h });

        const mismatched = pixelmatch(e.data, a.data, diff.data, w, h, {
          threshold: 0.18,   // per-pixel colour tolerance
          includeAA: false,  // ignore antialiasing — text rasterises differently
          alpha: 0.35,
        });

        const pct = (mismatched / (w * h)) * 100;
        fs.writeFileSync(path.join(REPORTS, `diff-${spec.section}@${width}.png`), PNG.sync.write(diff));

        report.add(pct <= threshold, `@${width}px  pixel diff ${pct.toFixed(2)}% (limit ${threshold}%)`,
          `${mismatched.toLocaleString()} of ${(w * h).toLocaleString()} px differ.\n` +
          `        diff:      reports/diff-${spec.section}@${width}.png\n` +
          `        rendered:  reports/actual-${spec.section}@${width}.png\n` +
          `        figma:     refs/${spec.section}@${width}.png\n` +
          `        Open the diff before changing any value — look for a missing or displaced ` +
          `block, not for a number to tune.`);
      } finally {
        await page.close();
      }
    }
  } finally {
    if (own) await b.close();
  }

  return report;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const args = parseArgs();
  if (!args.section) {
    console.error('Usage: node tools/audit-pixel.mjs --section <name> [--update-refs] [--target preview|dev|live]');
    process.exit(2);
  }
  const spec = loadSpec(args.section);
  const url = resolveUrl(args.target || 'preview', spec, args);
  info(`Auditing ${url}`);
  const report = await auditPixel({ spec, url, updateRefs: !!args['update-refs'] });
  process.exit(printReport(report) ? 0 : 1);
}
