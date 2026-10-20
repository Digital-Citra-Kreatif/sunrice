import { launch, session, shot, BASE, dumpForm, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/taxonomies/locations/terms', { waitUntil: 'networkidle' });
const rows = await p.$$eval('main main a[href*="/terms/"]', as => as.map(a => a.getAttribute('href') + ' ' + a.closest('li, tr, div').innerText.split('\n')[0]));
console.log(rows);
const cangguHref = rows.find(r => /Canggu/.test(r))?.split(' ')[0];
const T = [
  ['/admin/cms/terms/2/edit', { en: ['Villas for sale and rent in Bali and Jakarta', 'Private pool villas in Canggu, Seminyak, Ubud and beyond, for sale and for rent, selected by Lumina Estates.'], id: ['Vila dijual dan disewakan di Bali dan Jakarta', 'Vila dengan kolam renang pribadi di Canggu, Seminyak, Ubud dan sekitarnya, dijual dan disewakan, dipilih oleh Lumina Estates.'] }],
  [cangguHref?.replace(/\?.*/, ''), { en: ['Property in Canggu: villas, land and rentals', 'Villas, land and commercial spaces for sale and rent in Canggu, Berawa and Pererenan, on Bali\'s west coast.'], id: ['Properti di Canggu: vila, tanah dan sewa', 'Vila, tanah dan ruang usaha dijual dan disewakan di Canggu, Berawa dan Pererenan, di pesisir barat Bali.'] }],
];
for (const [url, langs] of T) {
  for (const [lang, [t, d]] of Object.entries(langs)) {
    await p.goto(BASE + url + '?locale=' + lang, { waitUntil: 'networkidle' });
    if (lang === 'id') console.log('id form text', (await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g, ' | '), (await dumpForm(p)).filter(x => x.includes('title')).join(';'));
    await p.fill('#seo-title', t); await p.fill('#seo-description', d);
    await p.getByRole('button', { name: 'Save' }).click(); console.log(url, lang, await toasts(p), await errors(p));
  }
}
await shot(p, '87-term-canggu-id', true);
await b.close();
