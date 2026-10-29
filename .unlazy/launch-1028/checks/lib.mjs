// Shared helpers for the launch-1028 checks: paced dev fetches (dev answers 429 to bursts), the live-guard
// for Playwright (blocks analytics/forms so a check never fires a real event), and the pages list.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

export const DEV = 'https://assetpanda1dev.wpenginepowered.com';
export const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
// Repo root, resolved from this file so the checks run from any clone (Windows or the cloud).
export const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')), '../../..').replace(/\\/g, '/');
export const SCOPE = ROOT + '/.unlazy/launch-1028';
export const GUARD = 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/live-guard.mjs';
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

export function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i < 0 ? def : process.argv[i + 1];
}
export const flag = (name) => process.argv.includes(name);

export function readPaths(file) {
  const j = JSON.parse(fs.readFileSync(path.resolve(ROOT, file), 'utf8'));
  return Array.isArray(j) ? j : j.paths;
}

// GET a dev page with back-off; returns { status, text, url }.
export async function fetchPage(p, { fresh = true } = {}) {
  const url = DEV + p + (fresh ? (p.includes('?') ? '&' : '?') + 'nc=' + Date.now() : '');
  for (let k = 0; k < 6; k++) {
    const r = await fetch(url, { headers: { 'user-agent': UA, accept: 'text/html' }, redirect: 'manual' });
    if (r.status === 429) { await sleep(12000 * (k + 1)); continue; }
    return { status: r.status, text: r.status === 200 ? await r.text() : '', location: r.headers.get('location') || '' };
  }
  return { status: 429, text: '', location: '' };
}

export async function head(url) {
  // Cloudflare rate-limits bursts from one IP: on 429 wait out the window (15 s) and try twice more.
  for (let k = 0; k < 3; k++) {
    const r = await fetch(url, { method: 'HEAD', headers: { 'user-agent': UA }, redirect: 'follow' });
    if (r.status === 429) { await sleep(15000); continue; }
    if (r.status === 405) { const g = await fetch(url, { headers: { 'user-agent': UA, range: 'bytes=0-0' } }); return g.status; }
    return r.status;
  }
  return 429;
}

export async function launch() {
  const require = createRequire(ROOT + '/package.json');
  const { chromium } = require('playwright');
  if (fs.existsSync(GUARD)) await import('file:///' + GUARD);
  return chromium.launch();
}

// Block analytics and form posts inside a page, belt and braces beside the live-guard.
export async function guardPage(page) {
  // Match hosts, not substrings: a bare "hubspot" also aborted the site's own eduhub-int-hubspot.png.
  await page.route(/google-analytics|googletagmanager|hubspot\.(com|net)|hubapi\.com|hs-scripts|hsforms|hsappstatic|hs-analytics|hscollectedforms|clarity\.ms|facebook|linkedin|doubleclick|stape|data\.assetpanda/i, (route) => route.abort());
}
