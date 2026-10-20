import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
// verify URL after create bug: reload the create url?
await p.goto(BASE + '/admin/cms/collections/properties/entries/create', { waitUntil: 'networkidle' });
await shot(p, '09-property-create', true);
console.log((await dumpForm(p)).join('\n'));
await b.close();
