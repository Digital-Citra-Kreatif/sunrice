import { launch, session, shot, BASE, dumpForm, toasts, errors } from './editor-lib.mjs';
import { fillRich } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
console.log('icon link html', await (async () => { await p.goto(BASE + '/admin/cms/taxonomies/property_types/terms', { waitUntil: 'networkidle' }); return p.locator('a[href*="/terms/2/edit"]').evaluate(e => e.outerHTML.slice(0, 300)); })());
await p.goto(BASE + '/admin/cms/terms/2/edit?locale=en', { waitUntil: 'networkidle' });
console.log((await p.locator('main main').innerText()).slice(0, 800));
console.log((await dumpForm(p)).join('\n'));
await shot(p, '86-term-villa-edit', true);
await b.close();
