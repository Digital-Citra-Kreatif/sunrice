import { launch, ctx, BASE, logErrors, createCollection, createDef } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const H = { type: 'Text', label: 'Heading', handle: 'heading' };
await createDef(p, 'fieldsets', 'Featured properties', 'featured_properties', [H, { type: 'Entries', label: 'Properties', handle: 'properties', collections: ['Properties'], multiple: true, max: 6 }]);
await createDef(p, 'fieldsets', 'Team', 'team', [H, { type: 'Entries', label: 'Agents', handle: 'agents', collections: ['Agents'], multiple: true }]);
await createDef(p, 'fieldsets', 'Testimonials', 'testimonials', [H, { type: 'Entries', label: 'Testimonials', handle: 'testimonials', collections: ['Testimonials'], multiple: true }]);
await createDef(p, 'blueprints', 'Page', 'page', [
  { type: 'Textarea', label: 'Intro', handle: 'intro' },
  { type: 'Flexible content', label: 'Sections', handle: 'sections', blocks: ['Hero', 'Rich text', 'Image and text', 'Stats', 'Featured properties', 'Team', 'Testimonials', 'Call to action', 'FAQ'] },
], '52-bp-page');
await createCollection(p, { title: 'Pages', handle: 'pages', blueprint: 'Page', hierarchical: true, ordered: true, route: '/{slug}', seo_description: 'Intro (intro)' }, '53-collection-pages');
await b.close();
