#!/usr/bin/env node
/**
 * audit-escaping.mjs — security lints for the Asset Panda PHP trees.
 *
 *   node tools/audit-escaping.mjs <components-dir>        every <dir>/<name>/render.php: a line that prints a
 *                                                         variable must carry an escaping/sanitising helper
 *   node tools/audit-escaping.mjs --self-test             the lint rejects a bad fixture and accepts a good one
 *   node tools/audit-escaping.mjs --sinks <dir>...        no eval/unserialize/exec-style sinks; every
 *                                                         $_GET/$_POST/$_REQUEST/$_COOKIE read is sanitised inline
 *   node tools/audit-escaping.mjs --php-lint <dir>...     php -l on every .php file (AP_PHP overrides the binary)
 *   node tools/audit-escaping.mjs --sync-check            gutenberg/lib and unyson/lib equal ui/ byte for byte
 *
 * Exit 0 and a *_PASSED marker only when every assertion holds. Lines are
 * printed for each failure so the fix is one click away. The lints are
 * lexical: they catch the cheap mistakes (a raw echo, a stray eval) and keep
 * them out for good; they do not replace reading the helper a line calls.
 *
 * tests/ directories are skipped: they run only on a developer machine.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/* ── shared ──────────────────────────────────────────────────────────── */

function phpFiles(dir) {
  const out = [];
  const walk = (d) => {
    for (const ent of fs.readdirSync(d, { withFileTypes: true })) {
      if (ent.name === 'tests' || ent.name === 'node_modules' || ent.name === '.git') continue;
      const p = path.join(d, ent.name);
      if (ent.isDirectory()) walk(p);
      else if (ent.isFile() && ent.name.endsWith('.php')) out.push(p);
    }
  };
  if (fs.existsSync(dir)) walk(dir);
  return out;
}

function rel(p) {
  return path.relative(ROOT, p).split(path.sep).join('/');
}

/* ── 1. output escaping in kit render files ───────────────────────────── */

// A line that prints something: echo/print or a short echo tag.
const PRINTS = /(^|[^a-z_$])(echo|print)\s+|<\?=/i;
// Helpers that make a value safe for the context they are used in, plus the
// numeric casts that cannot carry markup.
const SAFE = /\besc_(html|attr|url|textarea|js|html__|attr__|html_e|attr_e)\s*\(|\bap_ui_(lines|svg|markup|brandmarks|asset_url)\s*\(|\bwp_kses(_post|_data)?\s*\(|\bwp_json_encode\s*\(|\babsint\s*\(|\bintval\s*\(|\(\s*(int|float|bool)\s*\)|\bnumber_format\s*\(|\bsanitize_(text_field|key|html_class|title)\s*\(|\brawurlencode\s*\(|\burlencode\s*\(|\bcount\s*\(|\bchecked\s*\(|\bselected\s*\(|\bget_block_wrapper_attributes\s*\(/;
// A trailing phpcs annotation that names why the line is safe. The lint
// accepts it only when the justification names the helper applied upstream
// (escaped / esc_url / JSON_HEX_TAG …) or says the markup is fixed or literal.
const JUSTIFIED = /phpcs:ignore\s+WordPress\.Security\.EscapeOutput[^\n]*--[^\n]*\b(escaped|escaping|esc_(?:url|html|attr|textarea)|fixed|literal|kit escapes|component escapes|sanitised|sanitized|core-escaped|JSON_HEX_TAG)\b/i;
// Booleans and fixed literals chosen by a ternary carry no data.
const FIXED_TERNARY = /\?\s*'[^'$]*'\s*:\s*'[^'$]*'/;

function lintRender(file) {
  const findings = [];
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
  lines.forEach((line, i) => {
    if (!PRINTS.test(line)) return;
    if (!/\$[A-Za-z_]/.test(line)) return;
    if (SAFE.test(line) || JUSTIFIED.test(line) || FIXED_TERNARY.test(line)) return;
    findings.push(`${rel(file)}:${i + 1}: ${line.trim().slice(0, 160)}`);
  });
  return findings;
}

function auditComponents(dir) {
  const abs = path.resolve(ROOT, dir);
  if (!fs.existsSync(abs)) {
    console.error(`no such directory: ${dir}`);
    return 2;
  }
  let files = 0;
  const findings = [];
  for (const name of fs.readdirSync(abs)) {
    const f = path.join(abs, name, 'render.php');
    if (!fs.existsSync(f)) continue;
    files++;
    findings.push(...lintRender(f));
  }
  if (files === 0) {
    console.error(`no render.php files under ${dir}`);
    return 2;
  }
  if (findings.length) {
    console.log(findings.join('\n'));
    console.log(`ESCAPING AUDIT FAILED: ${findings.length} unescaped output line(s) in ${files} files`);
    return 1;
  }
  console.log(`ESCAPING AUDIT PASSED: ${files} render files, 0 unescaped output lines`);
  return 0;
}

function selfTest() {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'ap-escaping-'));
  const bad = path.join(tmp, 'bad');
  const good = path.join(tmp, 'good');
  fs.mkdirSync(path.join(bad, 'x'), { recursive: true });
  fs.mkdirSync(path.join(good, 'x'), { recursive: true });
  fs.writeFileSync(path.join(bad, 'x', 'render.php'), "<?php\n<h2><?php echo $props['title']; ?></h2>\n<p><?= $item['body'] ?></p>\n");
  fs.writeFileSync(path.join(good, 'x', 'render.php'), "<?php\n<h2><?php echo esc_html( $props['title'] ); ?></h2>\n<a href=\"<?php echo esc_url( $url ); ?>\">x</a>\n<?php echo $flag ? ' open' : ''; ?>\n");
  const badFindings = lintRender(path.join(bad, 'x', 'render.php'));
  const goodFindings = lintRender(path.join(good, 'x', 'render.php'));
  fs.rmSync(tmp, { recursive: true, force: true });
  if (badFindings.length !== 2) {
    console.log(`SELF-TEST FAILED: expected 2 findings on the bad fixture, got ${badFindings.length}`);
    return 1;
  }
  if (goodFindings.length !== 0) {
    console.log(`SELF-TEST FAILED: expected 0 findings on the good fixture, got ${goodFindings.length}\n${goodFindings.join('\n')}`);
    return 1;
  }
  console.log('SELF-TEST PASSED: bad fixture rejected (2 lines), good fixture accepted');
  return 0;
}

/* ── 2. dangerous sinks and raw superglobals ──────────────────────────── */

const SINKS = /(^|[^a-z_>$])(eval|unserialize|maybe_unserialize|shell_exec|passthru|proc_open|popen|system|exec|pcntl_exec|create_function|assert|extract|parse_str)\s*\(|\$\$[a-z_]|preg_replace\s*\(\s*['"][^'"]*\/[a-z]*e[a-z]*['"]|(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)/i;
const SUPERGLOBAL = /\$_(GET|POST|REQUEST|COOKIE)\s*\[/;
const SANITISED = /\bsanitize_[a-z_]+\s*\(|\bwp_unslash\s*\(|\babsint\s*\(|\bintval\s*\(|\(\s*int\s*\)|\bisset\s*\(|\bempty\s*\(|\barray_key_exists\s*\(|\bin_array\s*\(|\bcheck_admin_referer\s*\(|\bwp_verify_nonce\s*\(|\$_(GET|POST|REQUEST|COOKIE)\s*\[[^\]]+\]\s*===?\s*'/;

function auditSinks(dirs) {
  const findings = [];
  let files = 0;
  for (const dir of dirs) {
    for (const f of phpFiles(path.resolve(ROOT, dir))) {
      files++;
      const lines = fs.readFileSync(f, 'utf8').split(/\r?\n/);
      lines.forEach((line, i) => {
        const code = line.replace(/\/\/.*$/, '').replace(/#.*$/, '');
        if (/^\s*\*/.test(code) || /^\s*\/\*/.test(code)) return; // doc comments
        if (SINKS.test(code)) findings.push(`${rel(f)}:${i + 1}: sink: ${line.trim().slice(0, 140)}`);
        if (SUPERGLOBAL.test(code) && !SANITISED.test(code)) findings.push(`${rel(f)}:${i + 1}: raw superglobal: ${line.trim().slice(0, 140)}`);
      });
    }
  }
  if (files === 0) {
    console.error('no PHP files found');
    return 2;
  }
  if (findings.length) {
    console.log(findings.join('\n'));
    console.log(`SINK AUDIT FAILED: ${findings.length} finding(s) in ${files} files`);
    return 1;
  }
  console.log(`SINK AUDIT PASSED: ${files} PHP files, 0 sinks, 0 raw superglobal reads`);
  return 0;
}

/* ── 3. php -l ────────────────────────────────────────────────────────── */

function phpLint(dirs) {
  const php = process.env.AP_PHP || 'C:/Users/josep/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe';
  if (!fs.existsSync(php)) {
    console.log(`PHP LINT FAILED: php binary not found at ${php} (set AP_PHP)`);
    return 1;
  }
  let files = 0;
  const bad = [];
  for (const dir of dirs) {
    for (const f of phpFiles(path.resolve(ROOT, dir))) {
      files++;
      const r = spawnSync(php, ['-n', '-d', 'display_errors=1', '-l', f], { encoding: 'utf8' });
      if (r.status !== 0) bad.push(`${rel(f)}: ${(r.stdout + r.stderr).trim().split('\n')[0]}`);
    }
  }
  if (bad.length) {
    console.log(bad.join('\n'));
    console.log(`PHP LINT FAILED: ${bad.length} of ${files} files`);
    return 1;
  }
  const ver = spawnSync(php, ['-n', '-r', 'echo PHP_VERSION;'], { encoding: 'utf8' }).stdout.trim();
  console.log(`PHP LINT PASSED: ${files} files parse under PHP ${ver}`);
  return 0;
}

/* ── 4. synced copies ─────────────────────────────────────────────────── */

function listFiles(dir) {
  const out = [];
  const walk = (d) => {
    for (const ent of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, ent.name);
      if (ent.isDirectory()) walk(p);
      else if (ent.isFile()) out.push(path.relative(dir, p).split(path.sep).join('/'));
    }
  };
  walk(dir);
  return out.sort();
}

function syncCheck() {
  const src = path.join(ROOT, 'ui');
  const copies = ['gutenberg/lib/asset-panda-ui', 'unyson/lib/asset-panda-ui'];
  const srcFiles = listFiles(src).filter((f) => f !== 'CLAUDE.md');
  const problems = [];
  for (const copy of copies) {
    const abs = path.join(ROOT, copy);
    if (!fs.existsSync(abs)) {
      problems.push(`${copy}: missing`);
      continue;
    }
    const have = new Set(listFiles(abs));
    for (const f of srcFiles) {
      if (!have.has(f)) {
        problems.push(`${copy}/${f}: missing`);
        continue;
      }
      if (!fs.readFileSync(path.join(src, f)).equals(fs.readFileSync(path.join(abs, f)))) problems.push(`${copy}/${f}: differs from ui/${f}`);
    }
    for (const f of have) {
      if (!srcFiles.includes(f) && f !== 'CLAUDE.md') problems.push(`${copy}/${f}: stale (not in ui/)`);
    }
  }
  if (problems.length) {
    console.log(problems.join('\n'));
    console.log(`SYNC CHECK FAILED: ${problems.length} difference(s); run pwsh tools/sync-ui.ps1`);
    return 1;
  }
  console.log(`SYNC CHECK PASSED: ${srcFiles.length} files identical in ${copies.length} copies`);
  return 0;
}

/* ── main ─────────────────────────────────────────────────────────────── */

const args = process.argv.slice(2);
let code;
if (args[0] === '--self-test') code = selfTest();
else if (args[0] === '--sinks') code = auditSinks(args.slice(1));
else if (args[0] === '--php-lint') code = phpLint(args.slice(1));
else if (args[0] === '--sync-check') code = syncCheck();
else if (args[0] && !args[0].startsWith('--')) code = auditComponents(args[0]);
else {
  console.error('usage: audit-escaping.mjs <components-dir> | --self-test | --sinks <dir>... | --php-lint <dir>... | --sync-check');
  code = 2;
}
process.exit(code);
