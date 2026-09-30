/**
 * Shared helpers for the Asset Panda verification toolchain.
 *
 * Deliberately dependency-light so the audits can run in CI, in a git hook, or
 * from a Claude Code subagent without extra setup.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

/**
 * Cross-platform "was this file run directly, not imported" check.
 *
 * The naive `import.meta.url === 'file://' + process.argv[1]` string concat
 * silently evaluates false on Windows: `process.argv[1]` can come back as a
 * relative path (`tools/audit-layout.mjs`), and even when absolute, a raw
 * `'file://' + path` doesn't add the extra slash a Windows drive letter needs
 * or percent-encode spaces the way a real file:// URL does — so every audit's
 * CLI entrypoint no-op'd (exit 0, zero output) on every Windows invocation,
 * including via `npm run audit:*`. `pathToFileURL` builds the URL correctly
 * for the current platform, so the comparison is exact everywhere.
 */
export function isMainModule(moduleUrl) {
  if (!process.argv[1]) return false;
  return moduleUrl === pathToFileURL(process.argv[1]).href;
}

/* ------------------------------------------------------------------ *
 * Targets
 * ------------------------------------------------------------------ */

/**
 * Where a section can be rendered.
 *  - preview : local static server (fastest, pure design-layer truth)
 *  - dev     : the Gutenberg rebuild
 *  - live    : the Unyson production site, hit at ORIGIN to bypass Cloudflare's
 *              ~10 minute edge cache. Verifying against the cached URL is how
 *              you get a false "the fix didn't work".
 */
export const TARGETS = {
  preview: { base: process.env.AP_PREVIEW_URL || 'http://localhost:4321' },
  dev:     { base: process.env.AP_DEV_URL     || '' },
  live:    { base: process.env.AP_LIVE_URL    || 'https://assetpanda1.wpenginepowered.com' },
};

export const DEFAULT_WIDTHS = [1440, 1280, 1024, 768, 390];

/* ------------------------------------------------------------------ *
 * CLI
 * ------------------------------------------------------------------ */

export function parseArgs(argv = process.argv.slice(2)) {
  const out = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const key = a.slice(2);
      const next = argv[i + 1];
      if (next === undefined || next.startsWith('--')) out[key] = true;
      else { out[key] = next; i++; }
    } else out._.push(a);
  }
  return out;
}

/* ------------------------------------------------------------------ *
 * Specs
 * ------------------------------------------------------------------ */

export function loadSpec(section) {
  const file = path.join(ROOT, 'specs', `${section}.spec.json`);
  if (!fs.existsSync(file)) {
    throw new Error(
      `No spec at specs/${section}.spec.json.\n` +
      `Every section needs a spec before it can be verified — that file IS the ` +
      `definition of "matches Figma". Copy specs/_template.spec.json and fill in ` +
      `the Figma node id.`
    );
  }
  const spec = JSON.parse(fs.readFileSync(file, 'utf8'));
  spec.widths = spec.widths?.length ? spec.widths : DEFAULT_WIDTHS;
  spec.section = spec.section || section;
  return spec;
}

export function listSpecs() {
  const dir = path.join(ROOT, 'specs');
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir)
    .filter((f) => f.endsWith('.spec.json') && !f.startsWith('_'))
    .map((f) => f.replace('.spec.json', ''));
}

/* ------------------------------------------------------------------ *
 * Design tokens
 * ------------------------------------------------------------------ */

/** Parse `--name: value;` pairs out of the token stylesheet. */
export function loadTokens() {
  // The kit in ui/ is the single source of markup AND tokens. Verification reads
  // the same file the components do, so a token change can never pass the audit
  // while the rendered page still uses the old value.
  const candidates = [
    path.join(ROOT, 'ui', 'src', 'tokens.css'),
    path.join(ROOT, 'design-system', 'src', 'tokens.css'),
  ];
  const file = candidates.find((f) => fs.existsSync(f));
  if (!file) return {};
  const css = fs.readFileSync(file, 'utf8');
  const tokens = {};
  for (const m of css.matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/gi)) {
    tokens[m[1].trim()] = m[2].trim();
  }
  return tokens;
}

/* ------------------------------------------------------------------ *
 * Colour normalisation
 *
 * getComputedStyle always returns rgb()/rgba(); tokens are authored as hex.
 * Without normalisation every colour assertion is a false failure.
 * ------------------------------------------------------------------ */

export function normalizeColor(value) {
  if (!value) return null;
  const v = String(value).trim().toLowerCase();

  const hex = v.match(/^#([0-9a-f]{3,8})$/);
  if (hex) {
    let h = hex[1];
    if (h.length === 3 || h.length === 4) h = h.split('').map((c) => c + c).join('');
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    const a = h.length === 8 ? Math.round((parseInt(h.slice(6, 8), 16) / 255) * 100) / 100 : 1;
    return a === 1 ? `rgb(${r}, ${g}, ${b})` : `rgba(${r}, ${g}, ${b}, ${a})`;
  }

  const rgb = v.match(/^rgba?\(([^)]+)\)$/);
  if (rgb) {
    const parts = rgb[1].split(/[,\s/]+/).filter(Boolean).map((p) => p.trim());
    const [r, g, b] = parts.slice(0, 3).map((n) => Math.round(parseFloat(n)));
    const a = parts[3] !== undefined ? Math.round(parseFloat(parts[3]) * 100) / 100 : 1;
    return a === 1 ? `rgb(${r}, ${g}, ${b})` : `rgba(${r}, ${g}, ${b}, ${a})`;
  }

  return v;
}

/** Compare two CSS values tolerantly: colours normalised, lengths within 0.5px. */
export function cssEquals(actual, expected) {
  if (actual == null || expected == null) return false;
  const a = String(actual).trim();
  const e = String(expected).trim();
  if (a === e) return true;

  const na = normalizeColor(a);
  const ne = normalizeColor(e);
  if (na && ne && na === ne) return true;

  const pa = parseFloat(a);
  const pe = parseFloat(e);
  if (!Number.isNaN(pa) && !Number.isNaN(pe) && /px$/.test(a) && /px$/.test(e)) {
    return Math.abs(pa - pe) <= 0.5;
  }

  // Easing: collapse whitespace so cubic-bezier(0.22,1,0.36,1) matches the spaced form.
  const strip = (s) => s.replace(/\s+/g, '');
  return strip(a) === strip(e);
}

/* ------------------------------------------------------------------ *
 * Reporting
 * ------------------------------------------------------------------ */

const C = {
  reset: '\x1b[0m', red: '\x1b[31m', green: '\x1b[32m',
  yellow: '\x1b[33m', dim: '\x1b[2m', bold: '\x1b[1m',
};

export const ok      = (m) => console.log(`${C.green}  PASS${C.reset}  ${m}`);
export const fail    = (m) => console.log(`${C.red}  FAIL${C.reset}  ${m}`);
export const warn    = (m) => console.log(`${C.yellow}  WARN${C.reset}  ${m}`);
export const info    = (m) => console.log(`${C.dim}        ${m}${C.reset}`);
export const heading = (m) => console.log(`\n${C.bold}${m}${C.reset}`);

/** Every audit returns this shape so verify.mjs can aggregate uniformly. */
export function makeReport(name) {
  return {
    name,
    failures: [],
    warnings: [],
    checks: 0,
    add(passed, message, detail) {
      this.checks++;
      if (passed) return true;
      this.failures.push({ message, detail });
      return false;
    },
    addWarning(message, detail) { this.warnings.push({ message, detail }); },
    get passed() { return this.failures.length === 0; },
  };
}

export function printReport(report) {
  heading(`${report.name}  (${report.checks} checks)`);
  if (report.passed && !report.warnings.length) ok('all checks passed');
  for (const f of report.failures) { fail(f.message); if (f.detail) info(f.detail); }
  for (const w of report.warnings) { warn(w.message); if (w.detail) info(w.detail); }
  return report.passed;
}

/* ------------------------------------------------------------------ *
 * Page setup
 * ------------------------------------------------------------------ */

/**
 * Deterministic page state. Without the font wait, screenshots and font-size
 * assertions race the webfont and fail intermittently — the classic
 * "but it passed last time" flake.
 */
export async function preparePage(page, { width, height = 1200, reducedMotion = false } = {}) {
  await page.setViewportSize({ width, height });
  if (reducedMotion) await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.evaluate(() => document.fonts?.ready);
  await page.waitForTimeout(250);
}

/**
 * Launch Chromium.
 *
 * Set AP_CHROMIUM_PATH when the Playwright package version and the installed
 * browser build don't line up (common in CI images and sandboxes that ship a
 * pre-installed Chromium). Locally, `npx playwright install chromium` means you
 * never need it.
 */
export async function launchBrowser(opts = {}) {
  const { chromium } = await import('playwright');
  const executablePath = process.env.AP_CHROMIUM_PATH || undefined;
  return chromium.launch({ ...opts, ...(executablePath ? { executablePath } : {}) });
}

export function resolveUrl(target, spec, args = {}) {
  if (args.url) return args.url;
  const t = TARGETS[target];
  if (!t || !t.base) {
    throw new Error(
      `Target "${target}" has no URL configured.\n` +
      `Set AP_DEV_URL / AP_LIVE_URL in .env, or pass --url explicitly.`
    );
  }
  const pagePath = spec.path || `/${spec.section}/`;
  return t.base.replace(/\/$/, '') + pagePath;
}
