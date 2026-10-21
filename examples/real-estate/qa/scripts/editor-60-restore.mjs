import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/properties/entries?status=trash', { waitUntil: 'networkidle' });
console.log(p.url(), (await p.locator('tbody').innerText().catch(() => '')).slice(0, 200));
if (!(await p.locator('tbody tr', { hasText: 'Berawa' }).count())) { await p.click('text=Status: all'); await p.getByRole('option', { name: 'Trash' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500); }
await p.locator('tbody tr', { hasText: 'Berawa' }).locator('input[type=checkbox], button[role=checkbox]').first().click();
await p.waitForTimeout(400);
await shot(p, '98-trash-selected');
console.log('bulk bar', (await p.locator('main main').innerText()).slice(0, 400).replace(/\n/g, ' | '));
const r = p.getByRole('button', { name: /Restore/ });
console.log('restore buttons', await r.count());
if (await r.count()) { await r.first().click(); await p.waitForTimeout(500); const cf = p.locator('[role=alertdialog]'); if (await cf.count()) { console.log(await cf.innerText()); await cf.getByRole('button', { name: /Restore/ }).click(); } console.log(await toasts(p, 1200)); }
else { await p.locator('tbody tr', { hasText: 'Berawa' }).getByRole('link').first().click(); await p.waitForLoadState('networkidle'); await shot(p, '99-trashed-entry'); console.log((await p.locator('main main').innerText()).slice(0, 300)); }
await b.close();
