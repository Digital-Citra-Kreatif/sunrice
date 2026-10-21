import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/globals/company?locale=id', { waitUntil: 'networkidle' });
console.log('lang shown', await p.getByRole('combobox', { name: 'Language' }).innerText(), 'tagline', await p.inputValue('#data-tagline'));
await p.fill('#data-primary_contact-name', 'Anindya Wirawan');
await p.fill('#data-primary_contact-phone', '+62 21 7199 4400');
await p.fill('#data-primary_contact-email', 'anindya@lumina-estates.test');
await p.getByRole('button', { name: 'Save' }).click(); console.log(await toasts(p), await errors(p));
await b.close();
