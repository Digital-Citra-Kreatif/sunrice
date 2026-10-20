import { launch, session, shot, BASE, toasts, errors, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
for (const n of ['qa-test-square', 'qa-test-portrait', 'qa-test-landscape', 'qa-test-huge', 'qa-test-fake']) await p.getByRole('checkbox', { name: 'Select ' + n }).click();
await p.getByRole('button', { name: 'Move', exact: true }).click(); await p.waitForTimeout(400);
const d = p.locator('[role=dialog]');
await d.getByRole('combobox').click(); await p.waitForTimeout(200);
console.log('folders', await p.getByRole('option').allInnerTexts());
await p.getByRole('option', { name: /QA test uploads/ }).click();
await d.getByRole('button', { name: 'Move' }).click(); console.log('moved', await toasts(p, 1000));
await shot(p, '125-media-after-move');
console.log((await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g, ' | '));
// search everywhere
await p.fill('input[placeholder^="Search everywhere"]', 'portrait'); await p.waitForTimeout(1200);
console.log('search', (await p.locator('main main').innerText()).slice(0, 300).replace(/\n/g, ' | '));
await shot(p, '126-media-search');
await b.close();
