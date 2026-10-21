import { launch, ctx, BASE, logErrors, pick, toasts, shot } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/collections/2/edit', { waitUntil: 'networkidle' });
await pick(p, '#field-seo_description_field', 'Intro (intro)');
await pick(p, '#field-seo_image_field', 'Hero (hero)');
await p.click('#field-seo_description_field'); await p.waitForTimeout(300); await shot(p, '47-seo-from-fields-all-blueprints'); await p.keyboard.press('Escape');
await p.locator('button[type=submit]').first().click(); console.log(await toasts(p));
await b.close();
