import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const handle = process.argv[2] || 'main';
p.on('dialog', async d => { await d.accept(); });
for (let round = 0; round < 8; round++) {
  await p.goto(BASE + `/admin/cms/menus/${handle}`, { waitUntil: 'networkidle' });
  let n = await p.locator('button[aria-label^="Move "]').count();
  console.log('round', round, 'items', n);
  if (!n) break;
  while ((n = await p.locator('button[aria-label^="Remove"]').count()) > 0) {
    await p.locator('button[aria-label^="Remove"]').first().click(); await p.waitForTimeout(150);
    const conf = p.locator('[role=alertdialog]'); if (await conf.count()) await conf.getByRole('button', { name: /Remove/ }).click();
  }
  await p.getByRole('button', { name: 'Save', exact: true }).click(); console.log(await toasts(p, 1000));
}
await b.close();
