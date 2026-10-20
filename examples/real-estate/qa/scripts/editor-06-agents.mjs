import { launch, session, shot, BASE } from './editor-lib.mjs';
import { createEntry } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const types = { languages: 'checks', bio: 'rich', social: 'repeater:Add social' };
const agents = [
 ['Rafael Hartono', { role: 'Senior Partner, Villas & Land (Bali)', phone: '+62 361 473 2210', email: 'rafael@lumina-estates.test', whatsapp: '+62 812 3800 2210', languages: ['English','Indonesian','Mandarin'],
   bio: '<p>Rafael has sold more than 120 villas and land parcels across Canggu, Seminyak and Ubud since moving to Bali in 2011. Before real estate he trained as an architect in Surabaya, which shows in the way he reads a plot, a view corridor or a leasehold contract.</p><p>He advises overseas buyers on the right ownership structure (PT PMA, leasehold or Hak Pakai) and works with a trusted circle of notaries in Badung and Gianyar.</p>',
   social: [{ network: 'Instagram', url: 'https://www.instagram.com/rafael.lumina' }, { network: 'LinkedIn', url: 'https://www.linkedin.com/in/rafael-hartono' }] }],
 ['Sekar Ayuningtyas', { role: 'Leasing Director, Jakarta', phone: '+62 21 7199 4412', email: 'sekar@lumina-estates.test', whatsapp: '+62 811 1900 4412', languages: ['English','Indonesian'],
   bio: '<p>Sekar leads our rental desk, matching diplomatic and corporate tenants with family homes in Kemang, Pondok Indah and Menteng. She knows which streets flood, which schools have waiting lists and which landlords answer the phone.</p><p>Her team manages more than 60 leases a year, from the first viewing to the handover inventory.</p>',
   social: [{ network: 'LinkedIn', url: 'https://www.linkedin.com/in/sekar-ayuningtyas' }] }],
 ['Maarten de Vries', { role: 'Client Advisor, International Buyers', phone: '+62 361 473 2235', email: 'maarten@lumina-estates.test', whatsapp: '+62 812 3800 2235', languages: ['English','Dutch','Indonesian'],
   bio: '<p>Maarten moved from Amsterdam to Ubud in 2016 and has helped European families relocate to Bali ever since. He guides buyers through due diligence, building permits (PBG) and the practicalities of life on the island.</p><p>He is often the first call for clients looking for a quiet home with a rice-field view.</p>',
   social: [{ network: 'Facebook', url: 'https://www.facebook.com/maarten.lumina' }, { network: 'Instagram', url: 'https://www.instagram.com/maarten.in.ubud' }] }],
];
for (const [t, f] of agents) await createEntry(p, BASE, 'agents', t, f, types);
await p.goto(BASE + '/admin/cms/collections/agents/entries', { waitUntil: 'networkidle' });
await shot(p, '10-agents-list-4');
await b.close();
