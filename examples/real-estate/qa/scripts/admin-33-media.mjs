import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.locator('input[type=file]').first().setInputFiles('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa-test-upload.png');
await p.waitForTimeout(3000); await shot(p, '90-media-test-upload');
console.log((await p.locator('main main').innerText()).slice(0, 500));
// branding picker sees it?
await p.goto(BASE + '/admin/settings/branding', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Choose' }).first().click(); await p.waitForTimeout(1200);
await shot(p, '91-branding-logo-picker');
console.log((await p.locator('[role=dialog]').innerText().catch(() => 'no dialog')).slice(0, 400));
await b.close();
