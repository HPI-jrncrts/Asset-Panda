// AIP hero panda (contract C37): the served raster's aspect matches the box it is drawn in (no vertical stretch) and
// it is at least 2x its rendered width for a sharp 2x screen.
import { DEV, launch, guardPage } from './lib.mjs';
const browser = await launch();
const p = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
await guardPage(p);
await p.goto(DEV + '/asset-intelligence-platform/?nc=' + Date.now(), { waitUntil: 'load', timeout: 90000 });
await p.waitForTimeout(1500);
const r = await p.evaluate(() => {
  const img = [...document.images].find((i) => /image-83/.test(i.currentSrc || i.src));
  if (!img) return null;
  const fit = getComputedStyle(img).objectFit;
  return { src: (img.currentSrc || img.src).replace(/^https?:\/\/[^/]+/, ''), nat: img.naturalWidth + 'x' + img.naturalHeight, box: img.clientWidth + 'x' + img.clientHeight, natRatio: img.naturalWidth / img.naturalHeight, boxRatio: img.clientWidth / img.clientHeight, sharp: img.naturalWidth >= img.clientWidth * 2 - 2, fit };
});
await browser.close();
if (!r) { console.log('FAIL panda image not found'); process.exit(1); }
const stretch = Math.abs(r.natRatio - r.boxRatio) / r.boxRatio;
const okAspect = stretch < 0.03 || /contain|cover/.test(r.fit);
console.log(JSON.stringify(r) + ' stretch=' + (stretch * 100).toFixed(1) + '%');
console.log((okAspect ? 'PASS' : 'FAIL') + ' panda drawn at its own aspect');
console.log((r.sharp ? 'PASS' : 'FAIL') + ' panda raster >= 2x its rendered width');
if (!okAspect || !r.sharp) { console.log('AIP PANDA FAILED'); process.exit(1); }
console.log('AIP PANDA PASSED');
