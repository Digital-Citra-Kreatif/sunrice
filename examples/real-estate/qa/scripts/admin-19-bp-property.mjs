import { launch, ctx, BASE, logErrors, createDef } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const S = 'No, shared', Y = 'Yes, translated';
const N = (label, handle, extra = {}) => ({ type: 'Number', label, handle, tr: S, ...extra });
await createDef(p, 'blueprints', 'Property', 'property', [
  { type: 'Textarea', label: 'Summary', handle: 'summary', max: 300, tr: Y },
  N('Price', 'price', { step: 1, min: 0, instructions: 'In IDR' }),
  { type: 'Select', label: 'Price suffix', handle: 'price_suffix', tr: S, options: [['none','None'],['per_month','per month'],['per_year','per year']], default: 'none' },
  { type: 'Select', label: 'Status', handle: 'property_status', tr: S, options: [['available','Available'],['under_offer','Under offer'],['sold','Sold'],['rented','Rented']], default: 'available' },
  N('Bedrooms', 'bedrooms', { step: 1, min: 0 }), N('Bathrooms', 'bathrooms', { step: 1, min: 0 }),
  N('Building area (m²)', 'building_area', { min: 0 }), N('Land area (m²)', 'land_area', { min: 0 }),
  N('Year built', 'year_built', { step: 1, min: 1800, max: 2100 }),
  { type: 'Text', label: 'Address', handle: 'address', tr: Y },
  { type: 'Link', label: 'Map', handle: 'map', tr: S },
  { type: 'Media', label: 'Gallery', handle: 'gallery', tr: S, multiple: true, max: 12, types: ['Images'] },
  { type: 'Media', label: 'Floor plan', handle: 'floor_plan', tr: S, types: ['Images', 'Documents'] },
  { type: 'Rich text', label: 'Description', handle: 'description', tr: Y },
  { type: 'Repeater', label: 'Features', handle: 'features', tr: Y, children: [{ type: 'Text', label: 'Label', handle: 'label' }] },
  { type: 'Entries', label: 'Agent', handle: 'agent', tr: S, collections: ['Agents'] },
  { type: 'Entries', label: 'Neighbourhood', handle: 'neighbourhood', tr: S, collections: ['Neighbourhoods'] },
  { type: 'Link', label: 'Video', handle: 'video', tr: S },
  { type: 'Toggle', label: 'Featured', handle: 'featured', tr: S },
  { type: 'Terms', label: 'Property type', handle: 'property_type', tr: S, taxonomy: 'Property types' },
  { type: 'Terms', label: 'Listing type', handle: 'listing_type', tr: S, taxonomy: 'Listing types' },
  { type: 'Terms', label: 'Location', handle: 'location', tr: S, taxonomy: 'Locations' },
  { type: 'Terms', label: 'Amenities', handle: 'amenities', tr: S, taxonomy: 'Amenities', multiple: true },
], '48-bp-property');
await b.close();
