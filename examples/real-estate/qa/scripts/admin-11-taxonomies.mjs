import { launch, ctx, shot, BASE, logErrors, toasts, errors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const taxes = [
  ['Property types', 'property_types', false, '/property-type/{slug}'],
  ['Listing types', 'listing_types', false, ''],
  ['Locations', 'locations', true, '/location/{slug}'],
  ['Amenities', 'amenities', false, ''],
  ['Journal topics', 'topics', false, '/journal/topic/{slug}'],
];
let i = 0;
for (const [title, handle, nested, route] of taxes) {
  await p.goto(BASE + '/admin/taxonomies/create', { waitUntil: 'networkidle' });
  await p.fill('#field-title', title);
  if (i === 0) console.log('auto handle', await p.inputValue('#field-handle'));
  await p.fill('#field-handle', handle);
  if (nested) await p.click('#field-hierarchical');
  if (route) await p.fill('#field-route', route);
  if (i === 2) await shot(p, '29-taxonomy-create-locations', true);
  await p.click('button[type=submit]');
  console.log(handle, await toasts(p), p.url(), await errors(p));
  i++;
}
await p.goto(BASE + '/admin/taxonomies', { waitUntil: 'networkidle' });
await shot(p, '30-taxonomies-list');
console.log(await p.locator('main main').innerText());
await b.close();
