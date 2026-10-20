import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
for (const id of [13, 23, 26]) {
  await p.goto(BASE + `/admin/cms/entries/${id}/edit`, { waitUntil: 'networkidle' });
  const [popup] = await Promise.all([c.waitForEvent('page', { timeout: 6000 }).catch(() => null), p.getByRole('button', { name: 'Preview' }).or(p.getByRole('link', { name: 'Preview' })).first().click()]);
  await p.waitForTimeout(1200);
  if (popup) { await popup.waitForLoadState('networkidle').catch(() => {}); console.log(id, 'preview popup', popup.url(), (await popup.title())); await popup.screenshot({ path: `/home/user/realestate/qa/screenshots/editor-116-preview-${id}.png` }); console.log('  text', (await popup.locator('body').innerText()).slice(0, 200).replace(/\n/g, ' | ')); await popup.close(); }
  else { console.log(id, 'no popup; dialogs', (await p.locator('[role=dialog]').allInnerTexts()).map(x => x.slice(0, 300)), p.url()); await shot(p, `116-preview-${id}`); await p.keyboard.press('Escape'); }
  const view = p.getByRole('link', { name: 'View' });
  console.log('  view link', await view.count() ? await view.first().getAttribute('href') : 'none');
}
await b.close();
