import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('request', r => { if (r.method() !== 'GET') console.log('REQ', r.method(), r.url()); });
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
await p.goto(BASE + '/admin/cms/entries/10/edit', { waitUntil: 'networkidle' });
await shot(p, '23-published-with-draft-changes');
console.log('top', (await p.locator('main main').innerText()).slice(0, 120).replace(/\n/g, ' | '));
await p.fill('#data-price', '17950000000');
await p.getByRole('button', { name: 'Publish changes' }).click();
console.log('publish', await toasts(p, 1500));
const rev = p.locator('text=Revisions').locator('xpath=ancestor::*[contains(@class,"card") or @data-slot="card"][1]');
console.log('revcard', await rev.innerText().catch(() => 'n/a'));
await shot(p, '24-revisions-after-price-change');
// restore the oldest revision
const restores = p.getByRole('button', { name: 'Restore' });
console.log('restore buttons', await restores.count());
await restores.last().click();
await p.waitForTimeout(700);
console.log('dialogs', await p.locator('[role=alertdialog],[role=dialog]').allInnerTexts());
await shot(p, '25-restore-confirm');
const confirm = p.locator('[role=alertdialog],[role=dialog]').getByRole('button', { name: /Restore/ });
if (await confirm.count()) await confirm.first().click();
await p.waitForLoadState('networkidle');
const t = await toasts(p, 1200); console.log('restore toast', t);
console.log('price now', await p.inputValue('#data-price'));
console.log('top', (await p.locator('main main').innerText()).slice(0, 120).replace(/\n/g, ' | '));
await shot(p, '26-after-restore');
// undo
const undo = p.locator('[data-sonner-toast]').getByRole('button', { name: /Undo/ });
console.log('undo in toast', await undo.count());
if (await undo.count()) { await undo.first().click(); await p.waitForLoadState('networkidle'); console.log('undo toast', await toasts(p)); console.log('price after undo', await p.inputValue('#data-price')); }
await shot(p, '27-after-undo');
await b.close();
