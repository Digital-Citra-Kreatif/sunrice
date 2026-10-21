import { launch, ctx, shot, BASE, logErrors, addField } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/cms/blueprints/create', { waitUntil: 'networkidle' });
const types = ['Textarea','Rich text','Number','Toggle','Select','Date','Link','Media','Group','Repeater','Flexible content','Fieldset','Terms','Entries','File (forms)'];
for (const t of types) {
  const pre = await addField(p, p.getByRole('button', { name: 'Add field' }).last(), t);
  const info = await p.$$eval(`[id^="${pre}-"]`, els => els.map(e => `${e.tagName.toLowerCase()}[${e.getAttribute('type')||e.getAttribute('role')||''}] ${e.id} text=${(e.innerText||e.value||'').trim().slice(0,60).replace(/\n/g,'|')}`));
  const card = await p.locator(`#${pre}-label`).locator('xpath=ancestor::*[contains(@class,"rounded")][1]').innerText().catch(()=>'');
  console.log('=== ' + t + ' ' + pre + '\n' + info.join('\n') + '\n--- text:\n' + card.replace(/\n+/g,' / ').slice(0,900));
}
await shot(p, '27-all-field-types', true);
await b.close();
