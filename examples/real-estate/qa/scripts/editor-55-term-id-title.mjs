import { launch, session, shot, BASE, dumpForm, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
async function idTitle(tax, name, idName) {
  await p.goto(BASE + `/admin/cms/taxonomies/${tax}/terms?locale=id`, { waitUntil: 'networkidle' });
  if (name === 'Villa') { await shot(p, '88-terms-indonesian-list'); console.log((await p.locator('main main').innerText()).slice(0, 400).replace(/\n/g, ' | ')); }
  await p.getByRole('button', { name: 'Rename ' + name, exact: true }).click(); await p.waitForTimeout(300);
  const inp = p.locator('input[id^=term-][id$=-title]').first();
  await inp.fill(idName); await p.getByRole('button', { name: 'Save', exact: true }).click();
  console.log(tax, name, '->', idName, await toasts(p), await errors(p));
}
for (const [n, i] of [['Private pool','Kolam renang pribadi'],['Garden','Taman'],['Gym','Pusat kebugaran'],['24h security','Keamanan 24 jam'],['Parking','Parkir'],['Ocean view','Pemandangan laut'],['Rice-field view','Pemandangan sawah'],['Smart home','Rumah pintar'],['Staff quarters','Kamar staf'],['Rooftop','Atap terbuka']]) await idTitle('amenities', n, i);
await shot(p, '89-terms-amenities-id');
await b.close();
