import { launch, ctx, BASE, logErrors, createDef, shot } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const T = (label, handle, extra = {}) => ({ type: 'Text', label, handle, ...extra });
const TA = (label, handle, extra = {}) => ({ type: 'Textarea', label, handle, ...extra });
const RT = (label, handle, extra = {}) => ({ type: 'Rich text', label, handle, ...extra });
const M = (label, handle, extra = {}) => ({ type: 'Media', label, handle, types: ['Images'], ...extra });
const L = (label, handle, extra = {}) => ({ type: 'Link', label, handle, ...extra });
// ordering probe: entries field with no collection to choose from
await createDef(p, 'blueprints', 'Probe', 'probe_order', [{ type: 'Entries', label: 'Author', handle: 'author' }], '40-entries-field-no-collections');
await createDef(p, 'blueprints', 'Agent', 'agent', [
  T('Role','role'), M('Photo','photo'), T('Phone','phone', { tr: 'No, shared' }), T('Email','email', { tr: 'No, shared', validation: 'email' }), T('WhatsApp','whatsapp', { tr: 'No, shared' }),
  { type: 'Select', label: 'Languages', handle: 'languages', multiple: true, tr: 'No, shared', options: [['english','English'],['indonesian','Indonesian'],['mandarin','Mandarin'],['dutch','Dutch']] },
  RT('Bio','bio'),
  { type: 'Repeater', label: 'Social', handle: 'social', tr: 'No, shared', children: [ { type: 'Select', label: 'Network', handle: 'network', options: [['instagram','Instagram'],['linkedin','LinkedIn'],['facebook','Facebook']] }, L('URL','url') ] },
], '41-bp-agent');
await createDef(p, 'blueprints', 'Neighbourhood', 'neighbourhood', [
  M('Hero','hero'), TA('Intro','intro'), RT('Body','body'),
  { type: 'Repeater', label: 'Highlights', handle: 'highlights', children: [T('Label','label'), TA('Text','text')] },
  { type: 'Number', label: 'Average price', handle: 'average_price', step: 1, instructions: 'In IDR' },
]);
await createDef(p, 'blueprints', 'Testimonial', 'testimonial', [
  TA('Quote','quote', { required: true }), T('Client','client', { tr: 'No, shared' }), T('Client role','client_role'), M('Photo','photo'),
  { type: 'Number', label: 'Rating', handle: 'rating', min: 1, max: 5, step: 1 },
]);
await createDef(p, 'blueprints', 'Properties listing', 'properties_listing', [TA('Intro','intro'), M('Hero','hero')]);
await createDef(p, 'blueprints', 'Journal listing', 'journal_listing', [TA('Intro','intro')]);
await createDef(p, 'blueprints', 'Company', 'company', [
  T('Legal name','legal_name', { tr: 'No, shared' }), T('Tagline','tagline'), T('Phone','phone', { tr: 'No, shared' }), T('WhatsApp','whatsapp', { tr: 'No, shared' }), T('Email','email', { tr: 'No, shared', validation: 'email' }),
  TA('Address','address'), TA('Office hours','office_hours'), L('Map','map', { tr: 'No, shared' }),
  { type: 'Fieldset', label: 'Primary contact', handle: 'primary_contact', fieldset: 'Contact person' },
], '42-bp-company');
await createDef(p, 'blueprints', 'Social', 'social', [
  { type: 'Repeater', label: 'Links', handle: 'links', tr: 'No, shared', children: [ { type: 'Select', label: 'Network', handle: 'network', options: [['instagram','Instagram'],['facebook','Facebook'],['linkedin','LinkedIn'],['youtube','YouTube'],['tiktok','TikTok'],['whatsapp','WhatsApp']] }, L('URL','url') ] },
]);
await createDef(p, 'blueprints', 'Footer', 'footer', [TA('About','about'), RT('Legal','legal'), T('Newsletter heading','newsletter_heading')]);
await b.close();
