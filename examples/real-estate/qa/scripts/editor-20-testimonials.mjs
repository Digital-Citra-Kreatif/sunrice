import { launch, session, shot, BASE } from './editor-lib.mjs';
import { createEntry } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const T = [
 ['Hendrawan family, Kemang', { quote: 'Sekar found us a house in Kemang within three weeks of landing in Jakarta, and somehow knew which school run would be the shortest. Everything after that was just as calm.', client: 'Arief and Lina Hendrawan', client_role: 'Tenants, Kemang', rating: '5' }],
 ['Claire & Tom Whitfield, Pererenan', { quote: 'Buying a villa from abroad felt daunting. Rafael explained every ownership option in plain English and the notary process was finished before we flew back for the handover.', client: 'Claire and Tom Whitfield', client_role: 'Buyers, Villa in Pererenan', rating: '5' }],
 ['Ibu Ratna Sumarno, Menteng', { quote: 'We entrusted Lumina with the sale of my parents\' heritage home. They treated it with the respect it deserved and found a buyer who loves it as much as we did.', client: 'Ratna Sumarno', client_role: 'Seller, Menteng', rating: '5' }],
 ['Jeroen Bakker, Ubud', { quote: 'Maarten listened to what we really wanted: quiet, green, and close enough to Ubud for dinner. The land he showed us on the second day is now our home.', client: 'Jeroen Bakker', client_role: 'Buyer, Ubud', rating: '4' }],
];
for (const [t, f] of T) await createEntry(p, BASE, 'testimonials', t, f, {});
await shot(p, '39-testimonial', true);
await b.close();
