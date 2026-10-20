import { launch, session, shot, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
let tReq = 0, tResp = 0;
p.on('request', r => { if (r.url().includes('entry-picker')) tReq = Date.now(); });
p.on('response', r => { if (r.url().includes('entry-picker')) tResp = Date.now(); });
await p.goto(BASE + '/admin', { waitUntil: 'networkidle' });
await p.keyboard.press('Control+k'); await p.waitForTimeout(400);
await p.keyboard.type('kemang', { delay: 30 }); const t0 = Date.now();
for (let i = 0; i < 40; i++) { const n = await p.getByRole('option').count(); const nf = await p.getByText('Nothing found.').count(); if (n) { console.log('options after', Date.now() - t0, 'ms; req after', tReq - t0, 'resp after', tResp - t0, 'nothing-found shown meanwhile?', nf); break; } await p.waitForTimeout(100); }
await p.waitForTimeout(300);
await shot(p, '109-palette-results');
console.log(await p.getByRole('option').allInnerTexts());
await p.keyboard.press('ArrowDown'); await p.keyboard.press('Enter'); await p.waitForLoadState('networkidle');
console.log('went to', p.url());
// palette on Indonesian title
await p.keyboard.press('Control+k'); await p.waitForTimeout(300); await p.keyboard.type('Rumah Taman'); await p.waitForTimeout(2500);
console.log('id title', await p.getByRole('option').allInnerTexts());
await b.close();
