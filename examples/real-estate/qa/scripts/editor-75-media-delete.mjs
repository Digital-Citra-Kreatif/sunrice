import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.getByText('QA test uploads').first().click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
await p.getByRole('button', { name: 'Open qa-test-landscape' }).click(); await p.waitForTimeout(800);
console.log('detail', (await p.locator('[role=dialog]').innerText()).slice(0, 600).replace(/\n/g, ' | '));
await shot(p, '131-media-detail-used');
await p.locator('[role=dialog]').getByRole('button', { name: 'Move to trash' }).click(); await p.waitForTimeout(600);
console.log('trash confirm', await p.locator('[role=alertdialog],[role=dialog]').allInnerTexts().then(a => a.map(x => x.slice(0, 300))));
await shot(p, '132-media-trash-in-use');
const cancel = p.locator('[role=alertdialog]').getByRole('button', { name: 'Cancel' }); if (await cancel.count()) await cancel.click();
await p.keyboard.press('Escape');
// remove from entry 23
await p.goto(BASE + '/admin/cms/entries/23/edit', { waitUntil: 'networkidle' });
const gal = p.locator('text=Gallery').locator('xpath=ancestor::*[.//button[normalize-space()="Choose"]][1]');
const rm = gal.getByRole('button', { name: /Remove/ });
console.log('remove btns', await rm.evaluateAll(bs => bs.map(b => b.getAttribute('aria-label'))));
while (await rm.count()) { await rm.first().click(); await p.waitForTimeout(250); }
await p.getByRole('button', { name: 'Save draft' }).click(); console.log('entry saved', await toasts(p, 1000));
// delete all qa media: select, trash, then delete for good
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.getByText('QA test uploads').first().click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
for (const n of ['qa-test-square', 'qa-test-portrait', 'qa-test-landscape', 'qa-test-huge', 'qa-test-fake']) await p.getByRole('checkbox', { name: 'Select ' + n }).click();
await p.getByRole('button', { name: 'Move to trash' }).click(); await p.waitForTimeout(500);
const c1 = p.locator('[role=alertdialog]'); if (await c1.count()) { console.log('bulk trash confirm', (await c1.innerText()).slice(0, 300)); await c1.getByRole('button').last().click(); }
console.log('trashed', await toasts(p, 1000));
await p.getByRole('button', { name: 'Trash' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
console.log('trash view', (await p.locator('main main').innerText()).slice(0, 400).replace(/\n/g, ' | '));
await shot(p, '133-media-trash');
const all = p.locator('button[role=checkbox][aria-label^="Select qa-test"]');
for (let i = 0; i < await all.count(); i++) await all.nth(i).click();
const dfg = p.getByRole('button', { name: /Delete for good|Delete forever|Delete permanently/ });
console.log('delete-for-good btns', await dfg.count());
if (await dfg.count()) { await dfg.first().click(); await p.waitForTimeout(500); const c2 = p.locator('[role=alertdialog]'); if (await c2.count()) { console.log('confirm', (await c2.innerText()).slice(0, 300)); await c2.getByRole('button').last().click(); } console.log('deleted', await toasts(p, 1200)); }
await shot(p, '134-media-trash-empty');
console.log('trash after', (await p.locator('main main').innerText()).slice(0, 300).replace(/\n/g, ' | '));
await b.close();
