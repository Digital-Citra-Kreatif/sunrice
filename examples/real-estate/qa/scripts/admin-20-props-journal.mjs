import { launch, ctx, BASE, logErrors, createCollection, createDef } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await createCollection(p, { title: 'Properties', handle: 'properties', blueprint: 'Property', route: '/properties/{slug}', listing_route: '/properties', listing_blueprint: 'Properties listing', per_page: 9, schema: 'Web page', seo_description: 'Summary (summary)', seo_image: 'Gallery (gallery)' }, '49-collection-properties');
// try reserved handle first to record behaviour
await createDef(p, 'blueprints', 'Post', 'post', [{ type: 'Entries', label: 'Author', handle: 'author', collections: ['Agents'] }]);
await createDef(p, 'blueprints', 'Post', 'post', [
  { type: 'Media', label: 'Cover', handle: 'cover', types: ['Images'], tr: 'No, shared' },
  { type: 'Textarea', label: 'Excerpt', handle: 'excerpt', max: 240 },
  { type: 'Rich text', label: 'Body', handle: 'body' },
  { type: 'Entries', label: 'Author', handle: 'author_agent', collections: ['Agents'], tr: 'No, shared' },
  { type: 'Terms', label: 'Topics', handle: 'topics', taxonomy: 'Journal topics', multiple: true, tr: 'No, shared' },
  { type: 'Entries', label: 'Related properties', handle: 'related', collections: ['Properties'], multiple: true, max: 3, tr: 'No, shared' },
], '50-bp-post');
await createCollection(p, { title: 'Journal', handle: 'journal', blueprint: 'Post', dated: true, route: '/journal/{slug}', listing_route: '/journal', listing_blueprint: 'Journal listing', per_page: 6, comments: true, schema: 'Blog post', seo_description: 'Excerpt (excerpt)', seo_image: 'Cover (cover)' }, '51-collection-journal');
await b.close();
