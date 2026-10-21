import { launch, ctx, BASE, logErrors, createDef } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const T = (label, handle, extra = {}) => ({ type: 'Text', label, handle, ...extra });
const TA = (label, handle, extra = {}) => ({ type: 'Textarea', label, handle, ...extra });
const NAME = T('Name','name', { required: true }), EMAIL = T('Email','email', { required: true, validation: 'email' }), PHONE = T('Phone','phone');
await createDef(p, 'blueprints', 'Contact form', 'contact_form', [NAME, EMAIL, PHONE,
  { type: 'Select', label: 'Topic', handle: 'topic', options: [['buying','Buying'],['selling','Selling'],['renting','Renting'],['other','Other']] },
  TA('Message','message', { required: true })], '44-bp-contact-form');
await createDef(p, 'blueprints', 'Inquiry form', 'inquiry_form', [NAME, EMAIL, PHONE, T('Property','property'),
  { type: 'Date', label: 'Preferred date', handle: 'preferred_date' }, TA('Message','message')]);
await createDef(p, 'blueprints', 'Valuation form', 'valuation_form', [NAME, EMAIL, PHONE, T('Address','address', { required: true }),
  { type: 'Select', label: 'Property type', handle: 'property_type', options: [['house','House'],['villa','Villa'],['apartment','Apartment'],['land','Land'],['commercial','Commercial']] },
  { type: 'Number', label: 'Bedrooms', handle: 'bedrooms', min: 0, step: 1 },
  { type: 'File (forms)', label: 'Photos', handle: 'photos', ext: 'jpg, jpeg, png, webp', maxKb: 5120 },
  TA('Notes','notes')], '45-bp-valuation-form');
await createDef(p, 'blueprints', 'Newsletter form', 'newsletter_form', [EMAIL]);
await b.close();
