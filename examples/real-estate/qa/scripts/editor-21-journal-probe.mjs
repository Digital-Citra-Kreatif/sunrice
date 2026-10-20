import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/journal/entries/create', { waitUntil: 'networkidle' });
await shot(p, '40-journal-create', true);
console.log((await dumpForm(p)).filter(x => !/role=checkbox label=$|input\[checkbox\] id= /.test(x)).join('\n'));
console.log((await p.locator('main main').innerText()).slice(0, 1500));
await p.goto(BASE + '/admin/cms/entries/26/edit', { waitUntil: 'networkidle' });
console.log('testimonial header', (await p.locator('main main').innerText()).slice(0, 120).replace(/\n/g, ' | '));
await b.close();
