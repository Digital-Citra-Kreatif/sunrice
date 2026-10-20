import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('NATIVE DIALOG', d.type(), d.message()); await d.accept('/sell-with-us'); });
// link in rich text on "Sell" page (40)
await p.goto(BASE + '/admin/cms/entries/40/edit', { waitUntil: 'networkidle' });
const li = p.locator('#data-sections-0-body li', { hasText: 'Free valuation within 48 hours' });
await li.click(); await p.keyboard.press('Home'); await p.keyboard.down('Shift'); await p.keyboard.press('End'); await p.keyboard.up('Shift');
await p.locator('#data-sections-0-body').locator('xpath=ancestor::*[.//button[@aria-label="Link"]][1]').getByRole('button', { name: 'Link' }).first().click();
await p.waitForTimeout(600);
console.log('link ui', await p.locator('[role=dialog],[data-slot=popover-content][data-state=open]').allInnerTexts());
await shot(p, '147-richtext-link-ui');
const pop = p.locator('[role=dialog],[data-slot=popover-content][data-state=open]').last();
if (await pop.count()) {
  const inp = pop.locator('input').first(); await inp.fill('/sell-with-us');
  const btn = pop.getByRole('button', { name: /Apply|Save|Add|Insert|Set|OK/i }); if (await btn.count()) await btn.first().click(); else await p.keyboard.press('Enter');
}
await p.waitForTimeout(400);
console.log('html', (await p.locator('#data-sections-0-body').innerHTML()).match(/<a[^>]*>[^<]*<\/a>/)?.[0]);
await p.getByRole('button', { name: 'Publish changes' }).click(); console.log('sell publish', await toasts(p, 1200), await errors(p));
// publish pending summary change on 13 + update ID summary
await p.goto(BASE + '/admin/cms/entries/13/edit', { waitUntil: 'networkidle' });
const kept = p.getByRole('button', { name: 'Restore' }).first();
console.log('13 summary', await p.inputValue('#data-summary'));
await p.getByRole('button', { name: 'Publish changes' }).click(); console.log('13 publish', await toasts(p, 1200));
await p.goto(BASE + '/admin/cms/entries/13/edit?locale=id', { waitUntil: 'networkidle' });
await p.fill('#data-summary', 'Vila tiga kamar tidur di punggung bukit Sayan dengan kolam infinity menghadap lembah Sungai Ayung, sepuluh menit dari pusat Ubud. Kunjungan dengan perjanjian.');
await p.getByRole('button', { name: /^Publish/ }).first().click(); console.log('13 id publish', await toasts(p, 1200));
await b.close();
