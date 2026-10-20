import { launch, ctx, shot, BASE, logErrors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const toast = async () => { await p.waitForTimeout(1200); const t = await p.locator('[data-sonner-toast]').allInnerTexts(); console.log('TOAST', t); };
// languages
await p.goto(BASE + '/admin/settings/locales', { waitUntil: 'networkidle' });
console.log('lang initial value:', JSON.stringify(await p.inputValue('#field-languages')), 'placeholder', await p.getAttribute('#field-languages', 'placeholder'));
await shot(p, '10-languages-before');
await p.fill('#field-languages', 'en: English\nid: Bahasa Indonesia');
await p.click('button[type=submit]'); await toast();
await shot(p, '11-languages-saved');
// site
await p.goto(BASE + '/admin/settings/site', { waitUntil: 'networkidle' });
await p.fill('#field-name', 'Lumina Estates');
await p.fill('#field-description', 'Premium homes, villas, apartments and land for sale and rent in Jakarta and Bali.');
await p.fill('#field-separator', '·');
const sd = p.locator('#field-structuredData'); if ((await sd.getAttribute('aria-checked')) !== 'true') await sd.click();
await p.waitForTimeout(300);
await shot(p, '12-site-structured', true);
console.log((await p.locator('form').first().innerText()).slice(-1500));
console.log(await p.$$eval('input,textarea,button[id^=field]', e => e.map(x => x.id + '=' + (x.value || x.getAttribute('aria-checked') || x.innerText))));
await b.close();
