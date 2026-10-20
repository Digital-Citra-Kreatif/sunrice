import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch();
const B = await session(b, 'budi'); const A = await session(b, 'ayu');
await B.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' });
await B.p.fill('#data-summary', 'A three-bedroom villa on the Sayan ridge with an infinity pool over the Ayung river valley, ten minutes from central Ubud. Viewings by appointment.');
await A.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' });
await A.p.waitForTimeout(800);
console.log('ayu sees', await A.p.locator('[role=alertdialog],[role=dialog]').allInnerTexts());
await A.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-110-lock-ayu-sees.png' });
// View only
await A.p.getByRole('button', { name: 'View only' }).click(); await A.p.waitForTimeout(500);
await A.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-111-lock-view-only.png' });
console.log('ayu view-only banner', (await A.p.locator('main main').innerText()).slice(0, 250).replace(/\n/g, ' | '));
// take over from view-only state
const to = A.p.getByRole('button', { name: /Take over/ });
console.log('take over btn in view-only', await to.count());
if (await to.count()) { await to.first().click(); await A.p.waitForTimeout(400); const conf = A.p.locator('[role=alertdialog]').getByRole('button', { name: 'Take over' }); if (await conf.count()) await conf.click(); }
await A.p.waitForTimeout(1500);
console.log('ayu editable', await A.p.locator('#data-summary').isEditable(), 'ayu summary', (await A.p.inputValue('#data-summary')).slice(-40));
// Budi's side: wait for heartbeat to detect
for (let i = 0; i < 40; i++) { const d = await B.p.locator('[role=alertdialog],[role=dialog]').count(); if (d) break; await B.p.waitForTimeout(1000); }
console.log('budi sees', await B.p.locator('[role=alertdialog],[role=dialog]').allInnerTexts(), await toasts(B.p, 100));
await B.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-112-lock-budi-taken-over.png' });
console.log('budi editable', await B.p.locator('#data-summary').isEditable());
// Budi tries to save
await B.p.keyboard.press('Control+s'); console.log('budi ctrl+s', await toasts(B.p, 1500));
// Ayu leaves; budi reloads: his unsaved changes kept separately?
await A.p.goto(BASE + '/admin', { waitUntil: 'networkidle' });
await B.p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' }); await B.p.waitForTimeout(1500);
console.log('budi reopen', (await B.p.locator('main main').innerText()).slice(0, 400).replace(/\n/g, ' | '));
console.log('dialogs', await B.p.locator('[role=alertdialog],[role=dialog]').allInnerTexts());
await B.p.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-113-lock-budi-reopen.png', fullPage: false });
await b.close();
