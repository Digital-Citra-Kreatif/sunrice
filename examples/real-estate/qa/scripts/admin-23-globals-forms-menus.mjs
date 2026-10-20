import { launch, ctx, BASE, logErrors, pick, toasts, errors, shot } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const submit = async (label) => { await p.locator('button[type=submit]').first().click(); console.log(label, await toasts(p), p.url(), await errors(p)); };
for (const [t, h, bp] of [['Company', 'company', 'Company'], ['Social', 'social', 'Social'], ['Footer', 'footer', 'Footer']]) {
  await p.goto(BASE + '/admin/global-sets/create', { waitUntil: 'networkidle' });
  await p.fill('#field-title', t); await p.fill('#field-handle', h); await pick(p, '#field-blueprint', bp);
  await submit('global ' + h);
}
const forms = [
  { title: 'Contact', handle: 'contact', bp: 'Contact form', notify: 'ayu@lumina-estates.test', message: 'Thank you for getting in touch. One of our advisors will reply within one working day.' },
  { title: 'Property inquiry', handle: 'inquiry', bp: 'Inquiry form', notify: 'ayu@lumina-estates.test', message: 'Thank you for your interest. The listing agent will contact you shortly to arrange a viewing.' },
  { title: 'Valuation', handle: 'valuation', bp: 'Valuation form', notify: 'ayu@lumina-estates.test', message: 'Thank you. We will be in touch within two working days with a valuation appointment.', per: 3 },
  { title: 'Newsletter', handle: 'newsletter', bp: 'Newsletter form', notify: '', message: 'Thank you for subscribing to the Lumina Estates journal.' },
];
for (const f of forms) {
  await p.goto(BASE + '/admin/forms/create', { waitUntil: 'networkidle' });
  await p.fill('#field-title', f.title); await p.fill('#field-handle', f.handle); await pick(p, '#field-blueprint', f.bp);
  if (f.notify) await p.fill('#field-notify', f.notify);
  await p.fill('#field-message', f.message);
  if (f.per) await p.fill('#field-per_minute', String(f.per));
  if (f.handle === 'valuation') await shot(p, '56-form-valuation', true);
  await submit('form ' + f.handle);
}
for (const [t, h] of [['Main', 'main'], ['Footer', 'footer'], ['Legal', 'legal']]) {
  await p.goto(BASE + '/admin/menus/create', { waitUntil: 'networkidle' });
  await p.fill('#field-title', t); await p.fill('#field-handle', h); await submit('menu ' + h);
}
for (const [from, to] of [['/listings', '/properties'], ['/blog', '/journal']]) {
  await p.goto(BASE + '/admin/redirects/create', { waitUntil: 'networkidle' });
  await p.fill('#field-from_path', from); await p.fill('#field-to_url', to); await submit('redirect ' + from);
}
await p.goto(BASE + '/admin/redirects', { waitUntil: 'networkidle' }); await shot(p, '57-redirects-list');
await b.close();
