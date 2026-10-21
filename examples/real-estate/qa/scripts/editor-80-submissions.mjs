import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
import fs from 'fs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/submissions', { waitUntil: 'networkidle' });
await shot(p, '141-submissions');
console.log(p.url(), (await p.locator('main main').innerText()).slice(0, 700).replace(/\n/g, ' | '));
const row = p.getByRole('link', { name: /Oct 20, 2026/ }).or(p.getByRole('button', { name: /Oct 20, 2026/ })).first();
if (await row.count()) { await row.click(); await p.waitForTimeout(800); await shot(p, '142-submission-detail'); console.log('detail', p.url(), (await p.locator('body').innerText()).slice(-900).replace(/\n/g, ' | ')); }
console.log('btns', (await p.locator('main main button, main main a').evaluateAll(bs => bs.map(b => b.getAttribute('aria-label') || b.innerText.trim()))).filter(Boolean).slice(0, 30).join(' ; '));
await b.close();
