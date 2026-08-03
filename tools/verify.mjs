#!/usr/bin/env node
/**
 * Verification orchestrator — the gate a section must pass to be "done".
 *
 * Runs all four audits against one shared browser instance and prints a single
 * verdict. Exit code 0 only if every audit passed.
 *
 * Triage order is deliberate: layout -> tokens -> motion -> pixel.
 * A pixel failure is nearly always a SYMPTOM of one of the first three. Fix
 * causes in that order and the diff usually resolves itself.
 *
 *   npm run verify -- --section platform-stack
 *   npm run verify -- --all --target live
 */
import fs from 'node:fs';
import path from 'node:path';

import { auditLayout } from './audit-layout.mjs';
import { auditTokens } from './audit-tokens.mjs';
import { auditMotion } from './audit-motion.mjs';
import { auditPixel } from './audit-pixel.mjs';
import {
  ROOT, parseArgs, loadSpec, listSpecs, resolveUrl, launchBrowser,
  printReport, heading, info, ok, fail,
} from './lib/shared.mjs';

async function verifySection(section, target, args, browser) {
  const spec = loadSpec(section);
  const url = resolveUrl(target, spec, args);

  heading(`=== ${section}  [${target}]`);
  info(url);
  if (spec.node) info(`Figma node ${spec.node}`);

  const reports = [];
  reports.push(await auditLayout({ spec, url, browser }));
  reports.push(await auditTokens({ spec, url, browser, strict: !!args.strict }));
  reports.push(await auditMotion({ spec, url, browser }));
  if (!args['skip-pixel']) {
    reports.push(await auditPixel({ spec, url, browser, updateRefs: !!args['update-refs'] }));
  }

  let allPassed = true;
  for (const r of reports) allPassed = printReport(r) && allPassed;

  const summary = {
    section, target, url,
    node: spec.node || null,
    timestamp: new Date().toISOString(),
    passed: allPassed,
    audits: reports.map((r) => ({
      name: r.name,
      passed: r.passed,
      checks: r.checks,
      failures: r.failures,
      warnings: r.warnings,
    })),
  };

  fs.mkdirSync(path.join(ROOT, 'reports'), { recursive: true });
  fs.writeFileSync(
    path.join(ROOT, 'reports', `${section}.${target}.json`),
    JSON.stringify(summary, null, 2)
  );

  return summary;
}

const args = parseArgs();
const target = args.target || 'preview';
const sections = args.all ? listSpecs() : (args.section ? [args.section] : []);

if (!sections.length) {
  console.error(
    'Usage: npm run verify -- --section <name> [--target preview|dev|live]\n' +
    '       npm run verify -- --all\n\n' +
    `Known sections: ${listSpecs().join(', ') || '(none yet — add specs/<name>.spec.json)'}`
  );
  process.exit(2);
}

const browser = await launchBrowser();
const results = [];
try {
  for (const s of sections) {
    try {
      results.push(await verifySection(s, target, args, browser));
    } catch (e) {
      fail(`${s}: ${e.message}`);
      results.push({ section: s, passed: false, error: e.message });
    }
  }
} finally {
  await browser.close();
}

heading('=== VERDICT');
for (const r of results) {
  (r.passed ? ok : fail)(`${r.section} [${r.target || target}]${r.error ? ` — ${r.error}` : ''}`);
}

const failed = results.filter((r) => !r.passed);
if (failed.length) {
  console.log(
    `\n${failed.length} of ${results.length} section(s) failed. ` +
    `Reports in reports/. Fix in order: layout -> tokens -> motion -> pixel.\n`
  );
  process.exit(1);
}
console.log(`\nAll ${results.length} section(s) verified against Figma.\n`);
