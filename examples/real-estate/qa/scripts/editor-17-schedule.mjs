import { launch, session, shot, BASE, toasts, errors, dumpForm } from './editor-lib.mjs';
import { fillFields } from './editor-content.mjs';
import { PROP_TYPES } from './editor-props-data.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/properties/entries/create', { waitUntil: 'networkidle' });
await p.fill('#title', 'Pondok Indah Garden Penthouse');
await fillFields(p, { summary: 'A 410 m² duplex penthouse with a private roof garden and plunge pool, overlooking the Pondok Indah golf course.', price: '21000000000', property_status: 'Available', bedrooms: '4', bathrooms: '4', building_area: '410', year_built: '2020', address: 'Jl. Sultan Iskandar Muda Kav. 5, Tower Golf Lt. 32, Pondok Indah, Jakarta Selatan 12310', map: 'https://maps.google.com/?q=Pondok%20Indah%20Jakarta',
  description: '<p>The top two floors of a boutique tower: wide terraces, a private roof garden and views across the golf course to the Jakarta skyline.</p><h2>The penthouse</h2><ul><li>Four bedrooms, each with a terrace</li><li>Private roof garden with plunge pool</li><li>Private lift and three parking bays</li></ul><h3>Location</h3><p>Steps from Pondok Indah Mall and the hospital.</p>',
  features: [{ label: 'Duplex over two floors' }, { label: 'Private roof garden' }, { label: 'Three parking bays' }], agent: 'Anindya Wirawan', neighbourhood: 'Pondok Indah', video: 'https://www.youtube.com/watch?v=lumina-penthouse', property_type: 'Apartment', listing_type: 'For sale', location: '— — Pondok Indah', amenities: ['Private pool', 'Gym', '24h security', 'Parking', 'Rooftop', 'Smart home'] }, PROP_TYPES);
await p.getByRole('button', { name: 'Create draft' }).click();
await p.waitForSelector('text=Save draft'); await p.waitForLoadState('networkidle');
console.log(p.url(), await toasts(p, 500));
// open dropdown next to Publish
const pub = p.getByRole('button', { name: 'Publish', exact: true });
const box = await pub.boundingBox();
await p.mouse.click(box.x + box.width + 18, box.y + box.height / 2);
await p.waitForTimeout(300);
await p.getByRole('menuitem', { name: 'Publish on a date…' }).click();
await p.waitForTimeout(500);
await shot(p, '32-schedule-dialog');
const dlg = p.locator('[role=dialog]');
console.log('dialog', await dlg.innerText());
console.log((await dumpForm(p)).filter(x => !x.includes('data-')).join('\n'));
await b.close();
