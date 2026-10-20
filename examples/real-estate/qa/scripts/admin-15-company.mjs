import { launch, ctx, BASE, logErrors, createDef } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const T = (label, handle, extra = {}) => ({ type: 'Text', label, handle, ...extra });
const TA = (label, handle, extra = {}) => ({ type: 'Textarea', label, handle, ...extra });
await createDef(p, 'blueprints', 'Company', 'company', [
  T('Legal name','legal_name', { tr: 'No, shared' }), T('Tagline','tagline'), T('Phone','phone', { tr: 'No, shared' }), T('WhatsApp','whatsapp', { tr: 'No, shared' }), T('Email','email', { tr: 'No, shared', validation: 'email' }),
  TA('Address','address'), TA('Office hours','office_hours'), { type: 'Link', label: 'Map', handle: 'map', tr: 'No, shared' },
  { type: 'Group', label: 'Primary contact', handle: 'primary_contact', children: [ { type: 'Fieldset', label: 'Contact person', handle: 'contact_person', fieldset: 'Contact person' } ] },
], '43-bp-company-group-workaround');
await b.close();
