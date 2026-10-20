import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
import { execSync } from 'child_process';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/settings/security', { waitUntil: 'networkidle' });
await p.click('#field-twoFactor'); await p.locator('button[type=submit]').first().click();
console.log('2fa on', await toasts(p)); await shot(p, '84-2fa-on');
const c2 = await b.newContext({ viewport: { width: 1440, height: 900 } }); const q = await c2.newPage(); logErrors(q);
await q.goto(BASE + '/admin/login'); await q.fill('input[type=email]', 'ayu@lumina-estates.test'); await q.fill('input[type=password]', 'Lumina!Admin2026');
await Promise.all([q.waitForLoadState('networkidle'), q.click('button[type=submit]')]); await q.waitForTimeout(3000);
console.log('after password', q.url()); await shot(q, '85-2fa-code-screen');
console.log((await q.locator('body').innerText()).slice(0, 500));
const log = execSync('tail -c 200000 /home/user/realestate/storage/logs/laravel.log').toString();
const subj = [...log.matchAll(/Subject: (.*)/g)].map(m => m[1]); console.log('subjects', subj.slice(-3));
const codes = [...log.matchAll(/\b(\d{6})\b/g)].map(m => m[1]); const code = codes[codes.length - 1]; console.log('code', code);
// wrong code first
await q.fill('input[autocomplete=one-time-code], input[name=code], input[inputmode=numeric]', '000000'); await q.click('button[type=submit]'); await q.waitForTimeout(1500);
console.log('wrong code msg', (await q.locator('body').innerText()).match(/.*(code|wrong|match|invalid).*/gi));
await shot(q, '86-2fa-wrong-code');
await q.fill('input[autocomplete=one-time-code], input[name=code], input[inputmode=numeric]', code); await q.click('button[type=submit]'); await q.waitForTimeout(2500);
console.log('after code', q.url()); await shot(q, '87-2fa-signed-in');
// turn off again
await p.reload({ waitUntil: 'networkidle' }); await p.click('#field-twoFactor'); await p.locator('button[type=submit]').first().click();
console.log('2fa off', await toasts(p)); await shot(p, '88-2fa-off');
console.log('state', await p.locator('#field-twoFactor').getAttribute('data-state'));
await b.close();
