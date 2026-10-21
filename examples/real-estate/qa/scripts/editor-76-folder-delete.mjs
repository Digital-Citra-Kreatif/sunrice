import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Actions for QA test uploads' }).click(); await p.waitForTimeout(300);
console.log('menu', await p.getByRole('menuitem').allInnerTexts());
await p.getByRole('menuitem', { name: /Delete/ }).first().click(); await p.waitForTimeout(400);
const cf = p.locator('[role=alertdialog]'); if (await cf.count()) { console.log((await cf.innerText()).slice(0, 200)); await cf.getByRole('button').last().click(); }
console.log(await toasts(p, 1000));
await p.reload({ waitUntil: 'networkidle' });
console.log('media now', (await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g, ' | '));
await shot(p, '135-media-final');
await b.close();
