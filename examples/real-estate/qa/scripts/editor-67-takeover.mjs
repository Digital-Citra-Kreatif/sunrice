import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch();
const B = await session(b, 'budi'); const A = await session(b, 'ayu');
B.p.on('dialog', async d => { console.log('BUDI DIALOG', d.message()); await d.dismiss(); });
await B.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' });
await B.p.fill('#data-bedrooms', '3'); // no change
await B.p.fill('#data-summary', 'A three-bedroom villa on the Sayan ridge with an infinity pool over the Ayung river valley. Budi unsaved edit.');
await A.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' }); await A.p.waitForTimeout(600);
await A.p.getByRole('button', { name: 'Take over' }).click(); await A.p.waitForTimeout(400);
await A.p.locator('[role=alertdialog],[role=dialog]').last().getByRole('button', { name: 'Take over' }).click(); await A.p.waitForTimeout(1500);
console.log('ayu editable', await A.p.locator('#data-summary').isEditable(), 'ayu summary', (await A.p.inputValue('#data-summary')).slice(-30));
const t0 = Date.now();
for (let i = 0; i < 70; i++) { if (await B.p.locator('[role=alertdialog],[role=dialog]').count()) break; await B.p.waitForTimeout(1000); }
console.log('budi notified after', Date.now() - t0, 'ms:', await B.p.locator('[role=alertdialog],[role=dialog]').allInnerTexts(), 'url', B.p.url());
await B.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-114-budi-taken-over.png' });
await A.p.goto(BASE + '/admin', { waitUntil: 'networkidle' }); await A.c.close();
await B.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' }); await B.p.waitForTimeout(1500);
const txt = (await B.p.locator('body').innerText());
console.log('budi reopen kept-edits banner?', (txt.match(/(unsaved|kept|load)[^\n]*/gi) || []).slice(0, 4));
await B.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-115-budi-kept-edits.png' });
const load = B.p.getByRole('button', { name: /Load|Restore|Bring back/i });
console.log('load btns', await load.allInnerTexts());
if (await load.count()) { await load.first().click(); await B.p.waitForTimeout(800); console.log('summary now', await B.p.inputValue('#data-summary')); }
// discard: reload original
await b.close();
