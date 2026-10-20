import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const S = [
 [10, 'Villa Arunika, Pererenan: 4-bed pool villa for sale', 'Tropical modern four-bedroom villa with a 15 m pool facing the rice fields in Pererenan, Canggu. Freehold via PT PMA. IDR 17.95 billion.'],
 [11, 'Kemang Garden House: 5-bedroom home for sale in South Jakarta', 'A five-bedroom family home with pool and mature garden on 1,100 m² in Kemang, renovated in 2021. IDR 42 billion.'],
 [36, 'About Lumina Estates: luxury real estate in Jakarta and Bali', 'An independent agency for exceptional homes in Jakarta and Bali, built on discretion, local knowledge and long relationships.'],
 [44, 'Contact Lumina Estates: offices in Kemang and Canggu', 'Call, message or visit our offices in Kemang, Jakarta and Berawa, Canggu. We reply within one working day.'],
 [31, 'Buying property in Bali as a foreigner (2026 guide)', 'Leasehold, Hak Pakai or PT PMA: how foreigners can own a home in Bali, explained by Lumina Estates.'],
];
for (const [id, t, d] of S) {
  await p.goto(BASE + `/admin/cms/entries/${id}/edit`, { waitUntil: 'networkidle' });
  await p.fill('#seo-title', t); await p.fill('#seo-description', d);
  await p.getByRole('button', { name: 'Publish changes' }).click();
  console.log(id, await toasts(p, 1200), await errors(p));
}
// hide Terms page from search engines
await p.goto(BASE + `/admin/cms/entries/46/edit`, { waitUntil: 'networkidle' });
await p.click('#seo-noindex');
await p.getByRole('button', { name: 'Publish changes' }).click(); console.log('terms noindex', await toasts(p, 1200));
await shot(p, '91-seo-noindex', true);
await b.close();
