import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/settings/branding', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Remove' }).first().click(); await p.waitForTimeout(300);
await p.locator('button[type=submit]').first().click(); console.log('branding', await toasts(p));
await p.reload({ waitUntil: 'networkidle' }); await shot(p, '99-branding-logo-removed');
await b.close();
