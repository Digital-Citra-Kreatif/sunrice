import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const sets = {
  property_types: ['House', 'Villa', 'Apartment', 'Land', 'Commercial'],
  listing_types: ['For sale', 'For rent'],
  amenities: ['Private pool', 'Garden', 'Gym', '24h security', 'Parking', 'Ocean view', 'Rice-field view', 'Smart home', 'Staff quarters', 'Rooftop'],
  topics: ['Market reports', 'Buying guides', 'Living', 'Company news'],
  locations: [['Jakarta'], ['South Jakarta', 'Jakarta'], ['Kemang', 'South Jakarta'], ['Pondok Indah', 'South Jakarta'], ['Central Jakarta', 'Jakarta'], ['Menteng', 'Central Jakarta'], ['Bali'], ['Badung', 'Bali'], ['Canggu', 'Badung'], ['Seminyak', 'Badung'], ['Gianyar', 'Bali'], ['Ubud', 'Gianyar']],
};
for (const [tax, terms] of Object.entries(sets)) {
  await p.goto(BASE + `/admin/cms/taxonomies/${tax}/terms`, { waitUntil: 'networkidle' });
  for (const t of terms) {
    const [title, parent] = Array.isArray(t) ? t : [t];
    await p.fill('#new-term-title', title);
    if (parent) { await p.click('#new-term-parent'); const opts = await p.getByRole('option').allInnerTexts(); await p.getByRole('option', { name: new RegExp(parent + '$') }).first().click().catch(async e => { console.log('opts', opts); throw e; }); }
    if (title === 'Ubud') { console.log('slug auto', await p.inputValue('#new-term-slug')); await shot(p, '33-term-add-nested'); }
    await p.getByRole('button', { name: 'Add term' }).click();
    const t2 = await toasts(p); if (!/added|created/i.test(t2.join())) console.log(tax, title, t2);
  }
  await shot(p, `34-terms-${tax}`, true);
}
console.log(await p.locator('main main').innerText());
await b.close();
