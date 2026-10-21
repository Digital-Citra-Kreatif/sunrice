import { launch, session, shot, BASE, toasts, errors, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Open qa-test-landscape' }).click(); await p.waitForTimeout(600);
await p.fill('#media-alt', 'QA test image, plain teal rectangle (to be deleted)');
await p.locator('[role=dialog]').getByRole('button', { name: 'Save' }).click(); console.log('alt save', await toasts(p, 1000));
await p.keyboard.press('Escape'); await p.waitForTimeout(300);
// folder
await p.getByRole('button', { name: 'New folder' }).click(); await p.waitForTimeout(400);
console.log('folder dialog', (await p.locator('[role=dialog]').innerText().catch(() => '')).slice(0, 200));
const fin = p.locator('[role=dialog] input').first(); await fin.fill('QA test uploads'); await p.keyboard.press('Enter'); await p.waitForTimeout(800);
console.log('folder', await toasts(p, 300));
await shot(p, '122-media-folder');
// select 3 qa images and look at bulk actions
for (const n of ['qa-test-square', 'qa-test-portrait', 'qa-test-landscape']) await p.getByRole('checkbox', { name: 'Select ' + n }).click();
await p.waitForTimeout(300);
console.log('bulk', (await p.locator('main main').innerText()).slice(0, 300).replace(/\n/g, ' | '));
await shot(p, '123-media-bulk');
const mv = p.getByRole('button', { name: /Move/ }).first();
if (await mv.count()) { await mv.click(); await p.waitForTimeout(400); console.log('move ui', (await p.locator('[role=dialog],[role=menu]').allInnerTexts()).join(' / ').slice(0, 300)); const opt = p.getByRole('menuitem', { name: /QA test uploads/ }).or(p.getByRole('option', { name: /QA test uploads/ })).or(p.getByRole('button', { name: /QA test uploads/ })); if (await opt.count()) { await opt.first().click(); await p.waitForTimeout(400); const ok = p.locator('[role=dialog]').getByRole('button', { name: /Move/ }); if (await ok.count()) await ok.first().click(); console.log('moved', await toasts(p, 800)); } else await p.keyboard.press('Escape'); }
// search
await p.fill('input[placeholder^="Search everywhere"]', 'portrait'); await p.waitForTimeout(1000);
console.log('search', (await p.locator('main main').innerText()).slice(0, 300).replace(/\n/g, ' | '));
await shot(p, '124-media-search');
await b.close();
