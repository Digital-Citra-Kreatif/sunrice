import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/terms', { waitUntil: 'networkidle' });
await shot(p, '84-terms-index');
console.log((await p.locator('main main').innerText()).slice(0, 900));
console.log('buttons', (await p.locator('main main button').evaluateAll(bs => bs.map(b => b.getAttribute('aria-label') || b.innerText.trim()))).filter(Boolean).slice(0, 60).join(' ; '));
await b.close();
