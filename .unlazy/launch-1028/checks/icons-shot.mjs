// G5 evidence (contract C14): capture the Solutions panel rows that received the new icons — at rest and with each
// row hovered — and the same rows in the phone drawer, reporting whether the icon files loaded.
import { DEV, launch, guardPage } from './lib.mjs';
const OUT = 'reports/launch-1028/shots/';
const ICON = /Help-Desk-Ticketing-icn|Asset-Inspections-icn/;
const browser = await launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await guardPage(page);
await page.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(800);
const nav = page.locator('.ap-nav-item', { hasText: 'Solutions' }).first();
await nav.hover();
await page.waitForTimeout(900);
const tab = nav.locator('.ap-help-tab', { hasText: 'Maintenance' }).first();
if (await tab.count()) { await tab.hover(); await page.waitForTimeout(700); } else console.log('no Maintenance tab found in the Solutions panel');
await page.waitForFunction((re) => [...document.querySelectorAll('img.ap-icon-img')].filter((i) => new RegExp(re).test(i.src) && i.getBoundingClientRect().width > 0).every((i) => i.complete && i.naturalWidth > 0), ICON.source, { timeout: 10000 }).catch(() => {});
const info = await page.evaluate((re) => [...document.querySelectorAll('img.ap-icon-img')].filter((i) => new RegExp(re).test(i.src)).map((i) => { const b = i.closest('.ap-icon-box'); const cs = b && getComputedStyle(b); return { src: i.src.replace(/.*\//, ''), shown: i.getBoundingClientRect().width, loaded: i.complete && i.naturalWidth > 0, box: cs ? cs.borderTopWidth + ' ' + cs.borderTopColor + ' / ' + cs.backgroundColor : null }; }), ICON.source);
console.log('desktop icons ' + JSON.stringify(info));
const panel = nav.locator('.ap-mega-panel').first();
const box = await panel.boundingBox();
const x = Math.max(0, (box ? box.x : 0) - 8), y = Math.max(0, (box ? box.y : 0) - 8);
const clip = box ? { x, y, width: Math.min(1440 - x, box.width + 16), height: Math.min(900 - y, box.height + 16) } : undefined;
await page.screenshot({ path: OUT + 'icons-solutions-rest-1440.png', clip });
const rowFor = (t) => nav.locator('.ap-mega-link', { hasText: t }).first();
await rowFor('Help Desk Ticketing').hover(); await page.waitForTimeout(600);
await page.screenshot({ path: OUT + 'icons-solutions-hover-helpdesk-1440.png', clip });
await rowFor('Asset Inspections').hover(); await page.waitForTimeout(600);
await page.screenshot({ path: OUT + 'icons-solutions-hover-inspections-1440.png', clip });
await page.close();
// Phone drawer: open the burger, expand Solutions (and its Maintenance subgroup if collapsed), scroll the rows in.
const m = await browser.newPage({ viewport: { width: 390, height: 844 } });
await guardPage(m);
await m.goto(DEV + '/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await m.locator('.ap-mobile-burger').first().click();
await m.waitForTimeout(600);
await m.locator('.ap-mobile-menu details', { hasText: 'Solutions' }).first().locator('summary').first().click();
await m.waitForTimeout(500);
const item = m.locator('.ap-mobile-item', { hasText: 'Help Desk Ticketing' }).first();
if (!(await item.isVisible().catch(() => false))) { await m.locator('.ap-mobile-subhead', { hasText: 'Maintenance' }).first().click().catch(() => {}); await m.waitForTimeout(400); }
await item.scrollIntoViewIfNeeded().catch(() => {});
await m.waitForFunction((re) => [...document.querySelectorAll('img.ap-mobile-icon-img')].filter((i) => new RegExp(re).test(i.src)).every((i) => i.complete && i.naturalWidth > 0), ICON.source, { timeout: 10000 }).catch(() => {});
const minfo = await m.evaluate((re) => [...document.querySelectorAll('img.ap-mobile-icon-img')].filter((i) => new RegExp(re).test(i.src)).map((i) => ({ src: i.src.replace(/.*\//, ''), shown: i.getBoundingClientRect().width, loaded: i.complete && i.naturalWidth > 0 })), ICON.source);
console.log('drawer icons ' + JSON.stringify(minfo));
await m.screenshot({ path: OUT + 'icons-drawer-390.png' });
await m.close();
await browser.close();
const all = [...info, ...minfo];
const ok = all.length === 4 && all.every((i) => i.loaded);
console.log(ok ? 'ICON SHOTS DONE: 4 icons loaded' : 'ICON SHOTS DONE with problems: ' + JSON.stringify(all));
