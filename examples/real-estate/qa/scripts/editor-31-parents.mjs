import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
import { setParent } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
let dlg = null; p.on('dialog', async d => { dlg = d.message(); await d.dismiss(); });
// warning test on unsaved settings
await p.goto(BASE + '/admin/cms/entries/37/edit', { waitUntil: 'networkidle' });
await setParent(p, 'About');
await p.getByRole('link', { name: 'Dashboard' }).click(); await p.waitForTimeout(1000);
console.log('unsaved-settings nav: url', p.url(), 'dialog', dlg);
for (const [id, parent] of [[37, 'About'], [39, 'Services'], [40, 'Services'], [41, 'Services'], [42, 'Services']]) {
  await p.goto(BASE + `/admin/cms/entries/${id}/edit`, { waitUntil: 'networkidle' });
  await setParent(p, parent);
  await p.getByRole('button', { name: 'Save settings' }).click();
  console.log(id, await toasts(p, 1000));
}
await p.goto(BASE + '/admin/cms/collections/pages/entries', { waitUntil: 'networkidle' });
await shot(p, '58-pages-with-children', true);
console.log((await p.locator('tbody').innerText()).replace(/\t/g, ' ').replace(/\n+/g, ' | '));
await b.close();
