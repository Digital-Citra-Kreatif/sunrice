import { launch, ctx, BASE, logErrors, createCollection } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await createCollection(p, { title: 'Agents', handle: 'agents', blueprint: 'Agent', ordered: true, route: '/team/{slug}', listing_route: '/team', schema: 'About page' }, '46-collection-agents');
await createCollection(p, { title: 'Neighbourhoods', handle: 'neighbourhoods', blueprint: 'Neighbourhood', route: '/neighbourhoods/{slug}', listing_route: '/neighbourhoods', seo_description: 'Intro', seo_image: 'Hero' });
await createCollection(p, { title: 'Testimonials', handle: 'testimonials', blueprint: 'Testimonial', ordered: true });
await b.close();
