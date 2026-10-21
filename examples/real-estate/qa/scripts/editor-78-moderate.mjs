import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin', { waitUntil: 'networkidle' });
console.log('dash comments', (await p.locator('main main').innerText()).match(/Comments waiting[\s\S]{0,300}/)?.[0]?.replace(/\n/g, ' | '));
await p.goto(BASE + '/admin/cms/comments', { waitUntil: 'networkidle' });
await shot(p, '138-comments-list', true);
console.log((await p.locator('main main').innerText()).slice(0, 1200).replace(/\n/g, ' | '));
console.log('buttons', (await p.locator('main main button').evaluateAll(bs => bs.map(b => b.getAttribute('aria-label') || b.innerText.trim()))).filter(Boolean).join(' ; '));
await b.close();
