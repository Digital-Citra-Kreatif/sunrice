import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + `/admin/cms/menus/main`, { waitUntil: 'networkidle' });
for (const k of ['Listing', 'Link', 'Term']) {
  await p.getByRole('button', { name: k, exact: true }).click(); await p.waitForTimeout(400);
  console.log(k, await p.locator('[role=dialog]').innerText());
  console.log((await dumpForm(p)).slice(-6).join('\n'));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
}
await b.close();
