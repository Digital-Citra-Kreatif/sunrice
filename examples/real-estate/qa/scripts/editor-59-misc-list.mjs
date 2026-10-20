import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
// clear outdated by updating ID SEO
for (const [id, t, d] of [[10, 'Villa Arunika, Pererenan: vila 4 kamar dengan kolam renang dijual', 'Vila tropis modern empat kamar tidur dengan kolam renang 15 m menghadap sawah di Pererenan, Canggu. Rp17,95 miliar.'], [11, 'Rumah Taman Kemang: rumah 5 kamar tidur dijual di Jakarta Selatan', 'Rumah keluarga lima kamar tidur dengan kolam renang dan taman di lahan 1.100 m² di Kemang. Rp42 miliar.']]) {
  await p.goto(BASE + `/admin/cms/entries/${id}/edit?locale=id`, { waitUntil: 'networkidle' });
  await p.fill('#seo-title', t); await p.fill('#seo-description', d);
  await p.getByRole('button', { name: /^Publish/ }).first().click(); console.log(id, 'id seo', await toasts(p, 1200));
  console.log('  bar', (await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g, ' | '));
}
// Trash and restore a property (Berawa retail = id 21)
await p.goto(BASE + '/admin/cms/entries/21/edit', { waitUntil: 'networkidle' });
await p.locator('main main button:has(svg.lucide-trash-2), main main button[aria-label*="rash"], main main button[aria-label*="elete"]').first().click();
await p.waitForTimeout(500);
console.log('trash dialog', await p.locator('[role=alertdialog],[role=dialog]').allInnerTexts());
await shot(p, '95-trash-confirm');
const conf = p.locator('[role=alertdialog],[role=dialog]').getByRole('button', { name: /trash|Delete|Move/i });
if (await conf.count()) await conf.first().click();
await p.waitForLoadState('networkidle');
console.log('after trash', p.url(), await toasts(p, 1000));
await shot(p, '96-after-trash');
// find trash view
await p.goto(BASE + '/admin/cms/collections/properties/entries', { waitUntil: 'networkidle' });
await p.click('text=Status: all'); await p.waitForTimeout(300);
console.log('status options', await p.getByRole('option').allInnerTexts());
const trashOpt = p.getByRole('option', { name: /Trash/i });
if (await trashOpt.count()) { await trashOpt.click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500); }
await shot(p, '97-properties-trash');
console.log((await p.locator('main main').innerText()).slice(0, 700).replace(/\n/g, ' | '));
const restore = p.getByRole('button', { name: /Restore/ }).first();
if (await restore.count()) { await restore.click(); await p.waitForTimeout(400); const cf = p.locator('[role=alertdialog]'); if (await cf.count()) await cf.getByRole('button', { name: /Restore/ }).click(); console.log('restore', await toasts(p, 1200)); }
await b.close();
