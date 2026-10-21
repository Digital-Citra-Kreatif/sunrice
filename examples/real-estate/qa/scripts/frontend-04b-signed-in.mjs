// Comment as signed-in Ayu; check she never gets a kept page.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const POST = '/journal/five-things-to-check-before-renewing-a-villa-lease-in-bali';
const b = await launch(); const c = await b.newContext(DESKTOP); await login(c); const a = await c.newPage();
for (const u of ['/', '/properties', POST]) { const r = await a.goto(BASE + u, { waitUntil: 'networkidle' }); console.log('ayu', u, r.headers()['x-cache-status'] ?? 'no x-cache header'); }
await a.locator('#comment-form [name=body]').fill('QA signed-in comment from Ayu (frontend test).');
await a.waitForTimeout(2200);
const r = a.waitForResponse((x) => x.url().includes('/comments/'));
await Promise.all([a.waitForLoadState('networkidle'), a.locator('#comment-form button[type=submit]').click()]);
console.log('post', (await r).status(), a.url());
console.log(await a.evaluate(() => document.querySelector('#comments')?.innerText.slice(0, 600)));
await a.locator('#comments').scrollIntoViewIfNeeded(); await shot(a, '33-comment-signed-in', false);
await b.close();
