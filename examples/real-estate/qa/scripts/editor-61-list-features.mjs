import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
import fs from 'fs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/properties/entries', { waitUntil: 'networkidle' });
// filters
await p.click('text=Listing types: all'); await p.waitForTimeout(300); console.log('lt opts', await p.getByRole('option').allInnerTexts());
await p.getByRole('option', { name: 'For rent' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
await p.click('text=Locations: all'); await p.waitForTimeout(300); console.log('loc opts', await p.getByRole('option').allInnerTexts());
await p.getByRole('option', { name: /Kemang/ }).first().click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(500);
console.log('filtered', await p.locator('tbody tr').allInnerTexts().then(a => a.map(x => x.split('\n')[0])), p.url());
await shot(p, '100-list-filtered');
// clear and search
await p.getByRole('button', { name: 'Clear' }).click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(400);
await p.fill('input[placeholder^="Search titles"]', 'villa'); await p.waitForTimeout(1000);
console.log('search villa', await p.locator('tbody tr').allInnerTexts().then(a => a.map(x => x.split('\n')[0])));
await p.fill('input[placeholder^="Search titles"]', ''); await p.waitForTimeout(800);
// columns
await p.getByRole('button', { name: 'Columns' }).click(); await p.waitForTimeout(300);
const items = await p.locator('[role=menuitemcheckbox], [role=menu] [role=checkbox], [role=dialog] label').allInnerTexts();
console.log('column choices', items);
await shot(p, '101-columns-menu');
for (const col of ['Price', 'Status', 'Property type', 'Location', 'Featured', 'Agent']) {
  const it = p.getByRole('menuitemcheckbox', { name: new RegExp('^' + col) });
  if (await it.count()) { await it.first().click(); await p.waitForTimeout(250); }
}
await p.keyboard.press('Escape'); await p.waitForTimeout(500);
// sort by title desc
await p.getByRole('button', { name: /^Title/ }).first().click().catch(() => p.click('th:has-text("Title")')); await p.waitForTimeout(600);
await p.getByRole('button', { name: /^Title/ }).first().click().catch(() => p.click('th:has-text("Title")')); await p.waitForTimeout(600);
await shot(p, '102-list-columns-sorted');
const head = await p.locator('thead').innerText(); console.log('head', head.replace(/\s+/g, ' '));
const first = await p.locator('tbody tr').first().innerText(); console.log('first row', first.replace(/\s+/g, ' '));
// reload: prefs kept?
await p.reload({ waitUntil: 'networkidle' }); await p.waitForTimeout(500);
console.log('after reload head', (await p.locator('thead').innerText()).replace(/\s+/g, ' '), '| first', (await p.locator('tbody tr').first().innerText()).split('\n')[0]);
// CSV export
const [dl] = await Promise.all([p.waitForEvent('download', { timeout: 15000 }).catch(e => null), p.getByRole('button', { name: 'Export CSV' }).or(p.getByRole('link', { name: 'Export CSV' })).first().click()]);
if (dl) { const path = '/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/export.csv'; await dl.saveAs(path); const t = fs.readFileSync(path, 'utf8'); console.log('csv', dl.suggestedFilename(), t.split('\n').length, 'lines'); console.log(t.split('\n').slice(0, 3).join('\n').slice(0, 800)); }
else { await p.waitForTimeout(800); console.log('no download; dialog?', await p.locator('[role=dialog]').allInnerTexts()); await shot(p, '103-export'); }
// bulk bar
await p.locator('thead input[type=checkbox], thead button[role=checkbox]').first().click(); await p.waitForTimeout(400);
console.log('bulk', (await p.locator('main main').innerText()).match(/selected[\s\S]{0,200}/)?.[0]?.replace(/\n/g, ' | '));
await shot(p, '104-bulk-bar');
await p.locator('thead input[type=checkbox], thead button[role=checkbox]').first().click();
await b.close();
