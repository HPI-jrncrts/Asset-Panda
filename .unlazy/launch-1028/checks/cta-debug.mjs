import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 760, height: 260 }, deviceScaleFactor: 3 });
await p.goto('file:///C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/index.html');
await p.waitForTimeout(400);
console.log(await p.evaluate(() => { const e = document.getElementById('o1'); const s = e.querySelector('.ap-cta-fx'); const r = s.querySelector('.ap-cta-fx__ray'); const cs = getComputedStyle(r); const sb = s.getBoundingClientRect(); return JSON.stringify({ svgBox: [sb.width, sb.height], vb: s.getAttribute('viewBox'), rect: ['x', 'y', 'width', 'height', 'rx'].map((k) => r.getAttribute(k)), len: r.getTotalLength(), stroke: cs.stroke, sw: cs.strokeWidth, da: cs.strokeDasharray, off: cs.strokeDashoffset, vis: cs.visibility, disp: cs.display, op: cs.opacity, z: getComputedStyle(s).zIndex, defs: !!document.getElementById('ap-cta-ray') }); }));
await p.evaluate(() => { const r = document.querySelector('#o1 .ap-cta-fx__ray'); r.style.transition = 'none'; r.style.strokeDashoffset = 'calc(var(--ap-cta-p) * -0.3)'; });
const box = await p.locator('#o1').boundingBox();
await p.screenshot({ path: 'C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/debug-ray.png', clip: { x: box.x - 6, y: box.y - 6, width: box.width + 12, height: box.height + 12 } });
await b.close();
