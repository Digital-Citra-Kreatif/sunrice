import { launch, ctx, shot, BASE, logErrors, toasts, errors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const perm = async (name) => p.click(`[id="field-permissions-${name}"]`);
await p.goto(BASE + '/admin/roles/create', { waitUntil: 'networkidle' });
await p.fill('#field-name', 'Listings manager');
for (const n of ['sunrice.access', 'sunrice.guide.view', 'sunrice.media.view', 'sunrice.media.upload', 'sunrice.media.update', 'sunrice.media.delete',
  'cms.entries.properties.view', 'cms.entries.properties.create', 'cms.entries.properties.update', 'cms.entries.properties.publish', 'cms.entries.properties.delete', 'cms.entries.properties.export',
  'cms.terms.locations.manage', 'cms.terms.amenities.manage']) await perm(n);
await shot(p, '69-role-listings-manager', true);
await p.locator('button[type=submit]').first().click(); console.log('role', await toasts(p), p.url(), await errors(p));
// author: scope to journal
await p.goto(BASE + '/admin/roles', { waitUntil: 'networkidle' });
await p.getByRole('link', { name: 'author', exact: true }).first().click().catch(async () => { await p.getByText('author', { exact: true }).first().click(); });
await p.waitForLoadState('networkidle'); console.log('author edit url', p.url());
for (const n of ['cms.entries.view', 'cms.entries.create', 'cms.entries.journal.view', 'cms.entries.journal.create']) await perm(n);
await shot(p, '70-role-author-scoped', true);
await p.locator('button[type=submit]').first().click(); console.log('author', await toasts(p), await errors(p));
const users = [['Budi Santoso', 'budi', 'editor', 'Budi'], ['Citra Lestari', 'citra', 'author', 'Citra'], ['Dewi Kartika', 'dewi', 'translator', 'Dewi'], ['Eko Wibowo', 'eko', 'Listings manager', 'Eko']];
for (const [name, mail, role, first] of users) {
  await p.goto(BASE + '/admin/users/create', { waitUntil: 'networkidle' });
  await p.fill('#field-name', name); await p.fill('#field-email', `${mail}@lumina-estates.test`); await p.fill('#field-password', `Lumina!${first}2026`);
  await p.click(`[id="field-roles-${role}"]`);
  await p.locator('button[type=submit]').first().click(); console.log('user', mail, await toasts(p), p.url(), await errors(p));
}
await p.goto(BASE + '/admin/users', { waitUntil: 'networkidle' }); await shot(p, '71-users-list');
console.log(await p.locator('main main').innerText());
await b.close();
