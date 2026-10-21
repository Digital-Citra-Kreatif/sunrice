import { launch, ctx, shot, BASE, logErrors, addField, toasts, errors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/cms/fieldsets/create', { waitUntil: 'networkidle' });
await p.fill('#title', 'Contact person');
await p.fill('#handle', 'contact-person');
for (const [label, handle] of [['Name','name'],['Role','role'],['Phone','phone'],['Email','email']]) {
  const pre = await addField(p, p.getByRole('button', { name: 'Add field' }).last(), 'Text', { label });
  console.log(pre, 'auto handle', await p.inputValue('#' + pre + '-handle'));
}
await shot(p, '25-contact-person-fields', true);
await p.click('button[type=submit]');
console.log('TOAST', await toasts(p), 'url', p.url());
console.log('ERRS', await errors(p));
await shot(p, '26-contact-person-submit');
await b.close();
