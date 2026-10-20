import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { fillFields, publishOnDate } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const types = { body: 'rich', author_agent: 'entry', topics: 'checks', related: 'entries' };
const POSTS = [
 ['Jakarta prime residential market: Q3 2026 review', 'Wednesday, October 7th, 2026', '09:00', { excerpt: 'Prices for landed homes in Kemang, Menteng and Pondok Indah held firm in the third quarter while transaction volumes rose 8%. Here is what we saw and what it means for buyers.',
   body: '<p>The third quarter brought steady demand for landed homes in South and Central Jakarta. Buyers remained selective, but well-priced properties in good streets sold within 60 days.</p><h2>Key figures</h2><ul><li>Average asking price in Menteng: IDR 65 billion, flat on Q2</li><li>Kemang: transaction volume up 8% quarter on quarter</li><li>Pondok Indah: rental demand from corporate tenants up 12%</li></ul><h2>What it means for buyers</h2><p>Negotiation room has narrowed for the best homes. If a property ticks every box, act quickly; for everything else, there is time to be patient.</p><blockquote><p>"Quality homes in established streets continue to outperform the wider market." — Anindya Wirawan</p></blockquote>',
   author_agent: 'Anindya Wirawan', topics: ['Market reports'], related: ['Kemang Garden House', 'Menteng Heritage Residence'] }],
 ['Buying property in Bali as a foreigner: a practical guide', 'Monday, September 14th, 2026', '10:00', { excerpt: 'Leasehold, Hak Pakai or a PT PMA company? A clear overview of how foreigners can own a home in Bali, and the questions to ask before you sign.',
   body: '<p>Indonesian law does not allow foreigners to hold freehold (Hak Milik) title directly, but there are three well-established ways to own a home in Bali.</p><h2>Your options</h2><ol><li><strong>Leasehold (Hak Sewa)</strong>: a long lease, usually 25–30 years with an extension option.</li><li><strong>Hak Pakai</strong>: a right of use available to foreign residents with a KITAS or KITAP.</li><li><strong>PT PMA</strong>: a foreign-owned company that can hold Hak Guna Bangunan title.</li></ol><h2>Before you sign</h2><ul><li>Check the zoning and the building permit (PBG)</li><li>Use an independent notary (PPAT)</li><li>Confirm the extension terms of any lease in writing</li></ul>',
   author_agent: 'Rafael Hartono', topics: ['Buying guides'], related: ['Villa Arunika, Pererenan', 'Sayan Ridge Villa, Ubud', 'Rice-field Land in Pererenan, 1,200 m²'] }],
 ['Living in Ubud: a year in the hills', 'Thursday, August 20th, 2026', '08:30', { excerpt: 'Maarten de Vries on what daily life in Ubud is really like, from the morning market to the rainy-season afternoons.',
   body: '<p>Ten years ago I came to Ubud for a fortnight. I am still here, and I still find new paths through the rice fields.</p><h2>Mornings</h2><p>The day starts early: offerings on every doorstep, the market at Pasar Ubud, coffee before the heat.</p><h2>The seasons</h2><p>From November to March the afternoons bring heavy rain and the valleys turn an impossible green. A good roof and a covered terrace matter more than a big pool.</p><h3>Three things I wish I had known</h3><ul><li>Choose a home within ten minutes of a main road</li><li>Visit a plot in the rainy season before you buy</li><li>Learn some Bahasa Indonesia: it opens every door</li></ul>',
   author_agent: 'Maarten de Vries', topics: ['Living'], related: ['Sayan Ridge Villa, Ubud', 'Riverside Land near Ubud, 2,500 m²'] }],
 ['Renting a family home in South Jakarta: what to expect', 'Wednesday, July 15th, 2026', '09:30', { excerpt: 'From lease terms and advance payments to who fixes the pump: a guide for families relocating to Kemang and Pondok Indah.',
   body: '<p>Most landlords in Jakarta expect rent to be paid one or two years in advance. That surprises many newcomers, so it is worth planning for early.</p><h2>The usual terms</h2><ul><li>Minimum lease of one or two years</li><li>Rent paid in advance, plus a security deposit</li><li>Landlord responsible for structural repairs; tenant for day-to-day maintenance</li></ul><h2>Choosing the area</h2><p>Kemang suits families who like cafés and walking; Pondok Indah offers larger gardens, the golf course and the hospital.</p>',
   author_agent: 'Sekar Ayuningtyas', topics: ['Buying guides', 'Living'], related: ['Pondok Indah Golf-view Family Home', 'Kemang Townhouse for Lease'] }],
 ['Lumina Estates opens a new office in Canggu', 'Monday, June 1st, 2026', '11:00', { excerpt: 'Our second Bali office, on Jalan Pantai Berawa, brings our villa and land team closer to the clients and properties of the west coast.',
   body: '<p>We are delighted to announce the opening of our new office in Berawa, Canggu, led by Rafael Hartono.</p><h2>Why Canggu</h2><p>More than half of our Bali enquiries now concern Canggu, Pererenan and Seminyak. Being on the spot means faster viewings and better local knowledge.</p><p>Drop by for a coffee: Jalan Pantai Berawa No. 12, open Monday to Saturday, 9:00 to 18:00.</p>',
   author_agent: 'Rafael Hartono', topics: ['Company news'], related: ['Berawa Boutique Retail Space'] }],
];
for (const [title, day, time, f] of POSTS) {
  await p.goto(BASE + '/admin/cms/collections/journal/entries/create', { waitUntil: 'networkidle' });
  await p.fill('#title', title);
  await fillFields(p, f, types);
  await p.getByRole('button', { name: 'Create draft' }).click();
  await p.waitForSelector('text=Save draft'); await p.waitForLoadState('networkidle');
  console.log('created', title, p.url(), await errors(p));
  console.log('  ', await publishOnDate(p, day, time));
}
await shot(p, '41-journal-post', true);
await p.goto(BASE + '/admin/cms/collections/journal/entries', { waitUntil: 'networkidle' });
await shot(p, '42-journal-list');
console.log((await p.locator('tbody').innerText()).replace(/\t/g, ' '));
await b.close();
