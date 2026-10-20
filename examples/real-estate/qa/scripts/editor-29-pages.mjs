import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { fillRich, addBlock, setParent } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const PAGES = [
 ['About', null, null, null], // exists (id 36): add body
 ['Our story', 'About', 'From a two-person office in Kemang in 2014 to a team of twelve in Jakarta and Bali.', '<h2>How we started</h2><p>Anindya Wirawan opened Lumina Estates in a small office on Jalan Kemang Raya in 2014 with one conviction: that people buying or renting an exceptional home deserve an adviser, not a salesperson.</p><h2>Growing with our clients</h2><p>Our first Bali clients were Jakarta families looking for a second home. In 2017 Rafael Hartono joined to lead our villa and land work, and in 2026 we opened our office in Berawa.</p><h2>What has not changed</h2><ul><li>We take on fewer listings so we can do each one properly</li><li>We tell clients what we would do in their place</li><li>We stay in touch long after the keys are handed over</li></ul>'],
 ['Services', null, 'Buying, selling, renting and managing exceptional homes in Jakarta and Bali, with one team from first viewing to handover.', '<p>Whether you are buying your first villa in Bali, selling a family home in Menteng or relocating to Jakarta, we guide you through every step with clear advice and trusted partners.</p>'],
 ['Buy', 'Services', 'Find the right home, at the right price, with the right paperwork.', '<h2>Buying with Lumina</h2><p>We start by listening: how you live, what matters, what you can compromise on. Then we show you fewer, better homes, including properties that never reach the open market.</p><h3>What we handle</h3><ul><li>Shortlists and accompanied viewings</li><li>Price negotiation and offers</li><li>Legal due diligence with independent notaries</li><li>Ownership structures for foreign buyers</li></ul>'],
 ['Sell', 'Services', 'A considered sale: accurate pricing, beautiful presentation and qualified buyers.', '<h2>Selling with Lumina</h2><p>We value your home honestly, present it with professional photography and film, and introduce it first to the buyers already on our books.</p><ul><li>Free valuation within 48 hours</li><li>Discreet, off-market sales on request</li><li>Weekly progress reports</li></ul>'],
 ['Rent', 'Services', 'Homes for families, diplomats and professionals, with leases handled from start to finish.', '<h2>Renting with Lumina</h2><p>Our leasing team knows Kemang, Pondok Indah and Menteng street by street, and works with landlords who maintain their homes properly.</p><ul><li>Tailored shortlists before you arrive</li><li>Lease negotiation and inventory</li><li>Support throughout the tenancy</li></ul>'],
 ['Property management', 'Services', 'Care for your home or villa while you are away: maintenance, tenants and rental income.', '<h2>Property management</h2><p>For owners abroad or simply busy, we look after your property as if it were our own.</p><ul><li>Tenant finding and rent collection</li><li>Scheduled maintenance and repairs</li><li>Villa rental management in Bali</li><li>Monthly statements in English or Indonesian</li></ul>'],
 ['Sell with us', null, 'Request a free, no-obligation valuation of your home or villa.', '<h2>Request a valuation</h2><p>Tell us about your property and one of our partners will be in touch within one working day to arrange a visit. Our valuations are free, discreet and based on recent comparable sales.</p>'],
 ['Contact', null, 'Visit us in Kemang or Canggu, call, or send us a message. We reply within one working day.', '<h2>Our offices</h2><p><strong>Jakarta</strong>: Jl. Kemang Raya No. 8, Bangka, Mampang Prapatan, Jakarta Selatan 12730</p><p><strong>Bali</strong>: Jl. Pantai Berawa No. 12, Tibubeneng, Kuta Utara, Badung 80361</p><p>Monday to Friday 9:00–18:00, Saturday 10:00–15:00.</p>'],
 ['Privacy policy', null, 'How Lumina Estates collects, uses and protects your personal data.', '<h2>Who we are</h2><p>PT Lumina Estates Indonesia ("Lumina Estates", "we") is responsible for the personal data you share with us through this website.</p><h2>What we collect</h2><ul><li>Contact details you provide in forms (name, email, phone)</li><li>Details of the property you ask about or want valued</li><li>Basic, anonymous usage statistics</li></ul><h2>How we use it</h2><p>Only to answer your enquiry, arrange viewings and, if you agree, send our newsletter. We never sell your data.</p><h2>Your rights</h2><p>Under Indonesia\'s Personal Data Protection Law (UU PDP No. 27/2022) you may ask to see, correct or delete your data at any time by writing to privacy@lumina-estates.test.</p>'],
 ['Terms', null, 'The terms that apply when you use the Lumina Estates website.', '<h2>Use of this website</h2><p>The information on this website is provided in good faith but does not form part of any contract. Prices, availability and specifications may change without notice.</p><h2>Listings</h2><ul><li>Floor areas and land sizes are approximate</li><li>Photographs may show furniture that is not included</li><li>Ownership structures for foreign buyers are subject to Indonesian law</li></ul><h2>Liability</h2><p>Lumina Estates is not liable for decisions made solely on the basis of information on this site. Please seek independent legal advice before buying.</p>'],
];
for (const [title, parent, intro, body] of PAGES) {
  if (title === 'About') {
    await p.goto(BASE + '/admin/cms/entries/36/edit', { waitUntil: 'networkidle' });
    await addBlock(p, 'Rich text');
    await fillRich(p, '#data-sections-0-body', '<h2>An agency built on trust</h2><p>We are a team of twelve advisers in Jakarta and Bali. Between us we speak five languages and have handled more than 900 sales and leases.</p><p>We work with a small number of clients at a time, and most of them come to us through recommendation.</p>');
    await p.getByRole('button', { name: 'Publish changes' }).click(); console.log('About', await toasts(p));
    continue;
  }
  await p.goto(BASE + '/admin/cms/collections/pages/entries/create', { waitUntil: 'networkidle' });
  await p.fill('#title', title);
  await p.fill('#data-intro', intro);
  await addBlock(p, 'Rich text');
  await fillRich(p, '#data-sections-0-body', body);
  await p.getByRole('button', { name: 'Create draft' }).click();
  await p.waitForSelector('text=Save draft'); await p.waitForLoadState('networkidle');
  if (parent) { await setParent(p, parent); await p.getByRole('button', { name: 'Save draft' }).click(); await toasts(p, 800); }
  await p.getByRole('button', { name: 'Publish', exact: true }).click();
  console.log(title, p.url(), await toasts(p), await errors(p));
}
await p.goto(BASE + '/admin/cms/collections/pages/entries', { waitUntil: 'networkidle' });
await shot(p, '55-pages-tree', true);
console.log((await p.locator('tbody').innerText()).replace(/\t/g, ' ').replace(/\n+/g, ' | '));
await b.close();
