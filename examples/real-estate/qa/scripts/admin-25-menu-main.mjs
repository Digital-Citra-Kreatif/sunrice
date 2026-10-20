import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/cms/menus/main', { waitUntil: 'networkidle' });
const add = async (kind, target, label, url) => {
  await p.getByRole('button', { name: kind, exact: true }).click(); await p.waitForTimeout(400);
  if (kind === 'Link') await p.fill('#menu-url', url);
  else { await p.click('#menu-target'); await p.waitForTimeout(200); const o = p.getByRole('option', { name: target, exact: true }); if (!(await o.count())) console.log('opts', await p.getByRole('option').allInnerTexts()); await o.first().click(); }
  if (label) await p.fill('#menu-label', label);
  await p.locator('[role=dialog]').getByRole('button', { name: 'Add', exact: true }).click(); await p.waitForTimeout(400);
};
await add('Listing', 'Properties');
await add('Link', null, 'For sale', '/properties?listing_type=for-sale');
await add('Link', null, 'For rent', '/properties?listing_type=for-rent');
await add('Term', 'Property types: Villa', 'Villas');
await add('Listing', 'Neighbourhoods');
await add('Listing', 'Agents', 'Team');
await add('Listing', 'Journal');
for (const n of ['For sale','For rent','Villas']) { await p.getByRole('button', { name: 'Move ' + n }).locator('xpath=ancestor::*[.//button[@aria-label="In a level"]][1]').getByRole('button', { name: 'In a level' }).click(); await p.waitForTimeout(200); }
await shot(p, '61-menu-main-items', true);
await p.getByRole('button', { name: 'Save', exact: true }).click(); console.log('save', await toasts(p));
await p.reload({ waitUntil: 'networkidle' }); await shot(p, '62-menu-main-saved', true);
console.log((await p.locator('main main').innerText()));
console.log(await p.$$eval('main main button', e => e.map(x => (x.getAttribute('aria-label')||'') + '|' + x.innerText.trim()).filter(Boolean)));
await b.close();
