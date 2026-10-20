import { launch, ctx, BASE, logErrors, pick, toasts, errors, shot } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
for (const [id, cols] of [[1, ['Properties']], [2, ['Properties']], [3, ['Properties']], [4, ['Properties']], [5, ['Journal']]]) {
  await p.goto(BASE + `/admin/taxonomies/${id}/edit`, { waitUntil: 'networkidle' });
  for (const col of cols) await p.getByRole('checkbox', { name: col, exact: true }).click();
  if (id === 3) await shot(p, '54-taxonomy-attach-collections', true);
  await p.locator('button[type=submit]').first().click();
  console.log(id, await toasts(p), await errors(p));
}
await p.goto(BASE + '/admin/collections/1/edit', { waitUntil: 'networkidle' });
await pick(p, '#field-schema_type', '—');
await p.locator('button[type=submit]').first().click(); console.log('agents', await toasts(p));
await p.goto(BASE + '/admin/collections', { waitUntil: 'networkidle' }); await shot(p, '55-collections-list');
console.log(await p.locator('main main').innerText());
await b.close();
