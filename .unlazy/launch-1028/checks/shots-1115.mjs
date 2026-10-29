// Dev captures for the kit 1.3.115 review (leaf-1.6.8 G2). Paced; writes shots/d1115-*.png.
import { DEV, launch, guardPage, sleep } from './lib.mjs';
const OUT = 'C:/Sample Code/reports/launch-1028/shots/d1115-';
const browser = await launch();
const shot = async (name, path, width, sel, opts = {}) => {
  const p = await browser.newPage({ viewport: { width, height: opts.h || 900 } });
  await guardPage(p);
  await p.goto(DEV + path + (path.includes('?') ? '&' : '?') + 'nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
  await p.waitForTimeout(1800);
  if (opts.before) await opts.before(p);
  try {
    if (sel) { const l = p.locator(sel).first(); await l.scrollIntoViewIfNeeded(); await p.waitForTimeout(600); await l.screenshot({ path: OUT + name + '.png' }); }
    else await p.screenshot({ path: OUT + name + '.png' });
    console.log('shot ' + name);
  } catch (e) { console.log('ERR ' + name + ' ' + e.message.slice(0, 100)); }
  await p.close();
  await sleep(3500);
};
await shot('inventory-hero-1440', '/solutions/inventory-management/', 1440, null);
await shot('calibration-hero-1440', '/solutions/calibration-management/', 1440, null);
await shot('helpdesk-hero-1440', '/solutions/help-desk-ticketing/', 1440, null);
await shot('helpdesk-hero-390', '/solutions/help-desk-ticketing/', 390, null, { h: 1200 });
await shot('whycards-1440', '/solutions/inventory-management/', 1440, '.ap-why-cards');
await shot('eduhub-problem-1440', '/solutions/education/', 1440, '.ap-problem-scene');
await shot('founder-1440', '/asset-intelligence-platform/', 1440, '.ap-founder-quote');
await shot('barcode-cta-1440', '/barcode-labels/', 1440, '.ap-solution-cta');
await shot('bloghub-hero-1440', '/resource-center/blog/', 1440, '.ap-resource-hero');
await shot('megamenu-wwh-1440', '/', 1440, null, { before: async (p) => { const t = p.locator('.ap-site-header__desk .ap-nav-item', { hasText: 'Who We Help' }).first(); await t.hover(); await p.waitForTimeout(1500); } });
await shot('roi-1440', '/roi-calculator/', 1440, null, { h: 1100 });
await shot('roi-calc-1440', '/roi-calculator/', 1440, '.ap-pricing-calculator');
await shot('blogpost-1440', '/resource-center/blog/furniture-inventory-asset-management-makes-a-difference-to-your-bottom-line/', 1440, null, { h: 1000 });
await shot('blogpost-390', '/resource-center/blog/furniture-inventory-asset-management-makes-a-difference-to-your-bottom-line/', 390, null, { h: 1000 });
await browser.close();
console.log('SHOTS 1115 DONE');
