import { launch, session, shot, BASE, toasts, errors, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
await p.goto(BASE + '/admin/cms/entries/23/edit', { waitUntil: 'networkidle' });
const gal = p.locator('text=Gallery').locator('xpath=following::button[normalize-space()="Choose"][1]');
await gal.click(); await p.waitForTimeout(800);
await shot(p, '127-media-picker');
const d = p.locator('[role=dialog]');
console.log('picker', (await d.innerText()).slice(0, 400).replace(/\n/g, ' | '));
// open folder
const folder = d.getByText('QA test uploads').first(); if (await folder.count()) { await folder.click(); await p.waitForTimeout(800); }
console.log('picker in folder', (await d.innerText()).slice(0, 400).replace(/\n/g, ' | '));
await shot(p, '128-media-picker-folder');
for (const n of ['qa-test-landscape', 'qa-test-portrait', 'qa-test-fake']) { const it = d.getByRole('button', { name: new RegExp(n) }).or(d.getByRole('checkbox', { name: new RegExp(n) })).or(d.getByText(n, { exact: true })); console.log(n, await it.count()); if (await it.count()) await it.first().click(); await p.waitForTimeout(200); }
await shot(p, '129-media-picker-selected');
console.log('picker btns', await d.getByRole('button').allInnerTexts());
const use = d.getByRole('button', { name: /^(Use|Choose|Add|Insert|Select)/ }).last();
await use.click(); await p.waitForTimeout(600);
await shot(p, '130-gallery-filled');
console.log('gallery area', (await p.locator('main main').innerText()).match(/Gallery[\s\S]{0,300}/)?.[0]?.replace(/\n/g, ' | '));
await p.getByRole('button', { name: 'Save draft' }).click(); console.log('save', await toasts(p, 1200), await errors(p));
await b.close();
