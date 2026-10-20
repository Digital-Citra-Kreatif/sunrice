import { launch, ctx, shot, BASE, logErrors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/activity', { waitUntil: 'networkidle' });
const combos = p.locator('main main button[role=combobox]');
const n = await combos.count(); console.log('combos', n);
for (let i = 0; i < n; i++) { await combos.nth(i).click(); await p.waitForTimeout(300); console.log(i, await p.getByRole('option').allInnerTexts()); await p.keyboard.press('Escape'); }
// filter: Everything -> structure-ish
await combos.nth(1).click(); await p.waitForTimeout(300);
const opts = await p.getByRole('option').allInnerTexts();
const pickName = opts.find(o => /blueprint/i.test(o)) || opts[2];
await p.getByRole('option', { name: pickName, exact: true }).click(); await p.waitForTimeout(1200);
console.log('filtered by', pickName, p.url());
await shot(p, '81-activity-filtered', true);
console.log((await p.locator('main main').innerText()).slice(0, 2500));
await b.close();
