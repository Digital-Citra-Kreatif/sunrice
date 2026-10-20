import { launch, session, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/taxonomies/property_types/terms', { waitUntil: 'networkidle' });
console.log(await p.locator('a[href*="/terms/2/edit"]').evaluate(e => ({ aria: e.getAttribute('aria-label'), title: e.getAttribute('title'), text: e.innerText, sr: e.querySelector('.sr-only')?.innerText })));
await p.goto(BASE + '/admin/cms/collections/agents/entries/create', { waitUntil: 'networkidle' });
console.log(await p.getByText('English', { exact: true }).first().evaluate(e => e.tagName + ' for=' + e.getAttribute('for')));
console.log(await p.locator('button[role=checkbox]').first().evaluate(e => ({ id: e.id, al: e.getAttribute('aria-label'), lb: e.getAttribute('aria-labelledby') })));
await b.close();
