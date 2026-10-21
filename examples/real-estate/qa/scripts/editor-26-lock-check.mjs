import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/entries/35/edit', { waitUntil: 'networkidle' });
await shot(p, '49-citra-post-budi-view');
console.log((await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g,' | '));
console.log('author', (await p.locator('main main').innerText()).match(/Author\n.*\n.*/)?.[0]);
await b.close();
