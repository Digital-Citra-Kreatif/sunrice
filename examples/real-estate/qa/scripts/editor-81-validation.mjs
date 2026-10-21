import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { await d.accept(); });
// empty title
await p.goto(BASE + '/admin/cms/collections/testimonials/entries/create', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Create draft' }).click(); await p.waitForTimeout(1200);
console.log('empty title', await toasts(p, 200), await errors(p)); await shot(p, '143-validation-empty-title');
// rating out of range
await p.fill('#title', 'QA validation test'); await p.fill('#data-rating', '6'); await p.fill('#data-quote', 'x');
await p.getByRole('button', { name: 'Create draft' }).click(); await p.waitForTimeout(1200);
console.log('rating 6', p.url(), await toasts(p, 200), await errors(p)); await shot(p, '144-validation-rating');
const created = p.url().match(/entries\/(\d+)\/edit/);
// summary too long on property
await p.goto(BASE + '/admin/cms/entries/22/edit', { waitUntil: 'networkidle' });
const long = 'An income-producing building with long-term tenants. '.repeat(7);
await p.fill('#data-summary', long);
console.log('summary maxlength attr', await p.getAttribute('#data-summary', 'maxlength'), 'len typed', (await p.inputValue('#data-summary')).length);
await p.getByRole('button', { name: 'Save draft' }).click(); await p.waitForTimeout(1200);
const errs = await errors(p); console.log('long summary', await toasts(p, 200), errs);
await shot(p, '145-validation-summary');
// is the error visible in viewport? where?
const errEl = p.locator('[id$="-error"], [data-slot=form-message], .text-destructive').first();
if (await errEl.count()) console.log('error in viewport?', await errEl.isVisible(), await errEl.evaluate(e => { const r = e.getBoundingClientRect(); return [Math.round(r.top), window.innerHeight]; }));
// discard: reload without saving
await p.goto(BASE + '/admin/cms/entries/22/edit', { waitUntil: 'networkidle' });
console.log('summary after reload', (await p.inputValue('#data-summary')).length);
// negative price, decimals
await p.fill('#data-price', '-5'); await p.fill('#data-bedrooms', '2.5');
await p.getByRole('button', { name: 'Save draft' }).click(); await p.waitForTimeout(1200);
console.log('negative price / 2.5 bedrooms', await toasts(p, 200), await errors(p));
await shot(p, '146-validation-numbers');
await p.goto(BASE + '/admin/cms/entries/22/edit', { waitUntil: 'networkidle' });
console.log('price after', await p.inputValue('#data-price'), 'bedrooms', await p.inputValue('#data-bedrooms'));
if (created) console.log('CREATED TEST ENTRY', created[1]);
await b.close();
