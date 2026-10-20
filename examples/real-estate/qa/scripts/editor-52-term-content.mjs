import { launch, session, shot, BASE, dumpForm, pick } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/terms', { waitUntil: 'networkidle' });
await p.getByRole('combobox', { name: 'Taxonomy' }).click(); await p.waitForTimeout(200);
console.log('taxonomies', await p.getByRole('option').allInnerTexts());
await p.getByRole('option', { name: 'Property types' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
console.log(p.url());
console.log(await p.$$eval('main main a', as => as.map(a => a.innerText.trim() + ' ' + a.getAttribute('href'))));
const villa = p.getByRole('link', { name: /^Villa/ });
if (await villa.count()) { await villa.first().click(); await p.waitForLoadState('networkidle'); }
else { await p.getByText('Villa', { exact: true }).first().click(); await p.waitForTimeout(800); }
console.log(p.url());
console.log((await p.locator('main main').innerText()).slice(0, 800));
console.log((await dumpForm(p)).join('\n'));
await shot(p, '85-term-villa', true);
await b.close();
