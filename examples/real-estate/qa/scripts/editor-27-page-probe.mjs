import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/pages/entries/create', { waitUntil: 'networkidle' });
for (const blk of ['Hero', 'Stats', 'Featured properties', 'Image and text', 'Team', 'Testimonials', 'Call to action', 'Rich text', 'FAQ']) {
  await p.getByRole('button', { name: 'Add block' }).click(); await p.waitForTimeout(250);
  await p.getByRole('menuitem', { name: blk, exact: true }).click(); await p.waitForTimeout(300);
}
const t0 = Date.now();
console.log((await dumpForm(p)).filter(x => x.includes('sections') || x.includes('switch')).join('\n'));
console.log('buttons', (await p.locator('main main button').evaluateAll(bs => bs.map(b => b.getAttribute('aria-label') || b.innerText.trim()))).filter(Boolean).join(' ; '));
await shot(p, '52-page-all-blocks', true);
await b.close();
