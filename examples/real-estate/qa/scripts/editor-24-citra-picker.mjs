import { launch, session, shot, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'citra');
p.on('response', async r => { if (r.url().includes('search') || r.url().includes('lookup')) console.log('RESP', r.status(), r.url(), (await r.text()).slice(0, 200)); });
await p.goto(BASE + '/admin/cms/entries/35/edit', { waitUntil: 'networkidle' });
await p.fill('#data-author_agent', 'Rafael'); await p.waitForTimeout(1200);
await p.locator('#data-author_agent').scrollIntoViewIfNeeded();
await shot(p, '45-citra-author-picker-empty');
console.log('listbox text', await p.locator('[role=listbox], [cmdk-list], [data-slot=popover-content]').allInnerTexts());
await b.close();
