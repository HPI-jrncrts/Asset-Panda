// Local: a button that changes from gradient to outline after load (html.ap-intl) is re-classified.
import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage();
await p.goto('file:///C:/Users/josep/AppData/Local/Temp/claude/C--Sample-Code/4ce00505-a288-461f-8893-36ba978d4b50/scratchpad/liquid-test/geo.html');
const before = await p.evaluate(() => document.getElementById('d').getAttribute('data-ap-liquid'));
await p.waitForTimeout(800);
const after = await p.evaluate(() => { const e = document.getElementById('d'); return { kind: e.getAttribute('data-ap-liquid'), lbl: !!e.querySelector('.ap-liquid-lbl'), wraps: e.querySelectorAll('.ap-liquid').length, text: e.textContent.trim() }; });
console.log('before=' + before + ' after=' + JSON.stringify(after));
console.log(before === 'grad' && after.kind === 'outline' && after.lbl && after.wraps === 1 && after.text === 'Free Demo' ? 'GEO RECLASSIFY PASSED' : 'GEO RECLASSIFY FAILED');
await b.close();
