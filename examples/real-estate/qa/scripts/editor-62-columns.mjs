import { launch, session, shot, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/properties/entries', { waitUntil: 'networkidle' });
for (const col of ['Status', 'Price', 'Status (field)']) {
  await p.getByRole('button', { name: 'Columns' }).click(); await p.waitForTimeout(300);
  const it = p.getByRole('menuitemcheckbox', { name: col, exact: true });
  console.log(col, await it.count(), await it.getAttribute('aria-checked').catch(() => ''));
  await it.click(); await p.waitForTimeout(400);
  if (await p.getByRole('menu').count()) await p.keyboard.press('Escape');
}
await p.waitForTimeout(500);
await shot(p, '105-list-custom-columns');
console.log((await p.locator('thead').innerText()).replace(/\s+/g, ' '));
console.log((await p.locator('tbody tr').nth(1).innerText()).replace(/\s+/g, ' '));
await b.close();
