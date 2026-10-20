import { launch, session, shot, BASE, toasts, errors, pick } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const L = {
  properties: { en: ['Villas, family homes, apartments and land for sale and rent in Jakarta and Bali, each one visited and checked by our team.', 'Properties for sale and rent in Jakarta and Bali', 'Luxury villas, houses, apartments and land for sale and rent in Kemang, Menteng, Pondok Indah, Canggu and Ubud.'],
                id: ['Vila, rumah keluarga, apartemen dan tanah untuk dijual dan disewakan di Jakarta dan Bali, semuanya telah dikunjungi dan diperiksa oleh tim kami.', 'Properti dijual dan disewakan di Jakarta dan Bali', 'Vila mewah, rumah, apartemen dan tanah untuk dijual dan disewakan di Kemang, Menteng, Pondok Indah, Canggu dan Ubud.'] },
  journal: { en: ['Market reports, buying guides and stories from life in Jakarta and Bali, written by our advisers.', 'Journal: property insights for Jakarta and Bali', 'Market reports, buying guides and neighbourhood stories from the advisers at Lumina Estates.'],
             id: ['Laporan pasar, panduan membeli dan cerita kehidupan di Jakarta dan Bali, ditulis oleh para penasihat kami.', 'Jurnal: wawasan properti Jakarta dan Bali', 'Laporan pasar, panduan membeli dan cerita kawasan dari para penasihat Lumina Estates.'] },
};
for (const [coll, langs] of Object.entries(L)) {
  for (const [lang, [intro, st, sd]] of Object.entries(langs)) {
    await p.goto(BASE + `/admin/cms/collections/${coll}/listing`, { waitUntil: 'networkidle' });
    if (lang === 'id') { await p.getByRole('combobox', { name: 'Language' }).click(); await p.getByRole('option', { name: 'Indonesian' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500); }
    console.log(coll, lang, 'prefilled intro:', JSON.stringify((await p.inputValue('#data-intro')).slice(0, 40)));
    await p.fill('#data-intro', intro); await p.fill('#seo-title', st); await p.fill('#seo-description', sd);
    await p.getByRole('button', { name: 'Save' }).click();
    console.log('  ', await toasts(p, 1200), await errors(p), p.url());
  }
}
await shot(p, '71-listing-journal-id', true);
await b.close();
