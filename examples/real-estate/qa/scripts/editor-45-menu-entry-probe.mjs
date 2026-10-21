import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/menus/main', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Entry' }).click(); await p.waitForTimeout(500);
const d = p.locator('[role=dialog]');
console.log(await d.innerText());
console.log((await dumpForm(p)).filter(x => !x.includes('label=Indonesian')).slice(-8).join('\n'));
await shot(p, '77-menu-add-entry');
await b.close();
