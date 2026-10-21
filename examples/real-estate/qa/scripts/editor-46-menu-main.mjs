import { launch, session, shot, BASE, toasts, errors, drag } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/menus/main', { waitUntil: 'networkidle' });
async function addEntry(title) {
  await p.getByRole('button', { name: 'Entry' }).click(); await p.waitForTimeout(400);
  await p.fill('#menu-entry', title.slice(0, 8)); await p.waitForTimeout(800);
  const o = p.getByRole('option', { name: new RegExp('^' + title) }).first();
  if (await o.count()) await o.click(); else console.log('MISSING', title, await p.getByRole('option').allInnerTexts());
  await p.locator('[role=dialog]').getByRole('button', { name: 'Add' }).click(); await p.waitForTimeout(400);
}
for (const t of ['About', 'Our story', 'Services', 'Contact']) await addEntry(t);
await shot(p, '78-menu-entries-added', true);
// indent Our story under About
await p.getByRole('button', { name: 'Move Our story' }).locator('xpath=ancestor::*[.//button[@aria-label="In a level"]][1]').getByRole('button', { name: 'In a level' }).first().click();
await p.waitForTimeout(300);
// drag Journal above Team
await drag(p, p.getByRole('button', { name: 'Move Journal' }), p.getByRole('button', { name: 'Move Team' }), { dy: -10 });
await shot(p, '79-menu-after-indent-drag', true);
const labels = await p.$$eval('input[aria-label="Label (English)"], input', els => els.filter(e => (e.getAttribute('aria-label') || '').startsWith('Label (English)') || (e.labels?.[0]?.innerText || '') === 'Label (English)').map(e => e.value));
console.log('order', labels);
// Indonesian labels: map english label/placeholder → id
const ID = { 'Properties': 'Properti', 'For sale': 'Dijual', 'For rent': 'Disewakan', 'Villas': 'Vila', 'Neighbourhoods': 'Kawasan', 'Team': 'Tim', 'Journal': 'Jurnal', 'About': 'Tentang kami', 'Our story': 'Kisah kami', 'Services': 'Layanan', 'Contact': 'Kontak' };
const en = p.locator('input[aria-label="Label (English)"]'); const idl = p.locator('input[aria-label="Label (Indonesian)"]');
const n = await en.count();
for (let k = 0; k < n; k++) { const e = en.nth(k); const name = (await e.inputValue()) || (await e.getAttribute('placeholder')); const key = { Agents: 'Team', Villa: 'Villas' }[name] || name; if (ID[key]) await idl.nth(k).fill(ID[key]); else console.log('no id for', name); }
// test out-a-level then in again on "For rent"
const row = p.getByRole('button', { name: 'Move For rent' }).locator('xpath=ancestor::*[.//button[@aria-label="Out a level"]][1]');
await row.getByRole('button', { name: 'Out a level' }).first().click(); await p.waitForTimeout(300);
await shot(p, '80-menu-outdented');
await p.getByRole('button', { name: 'Move For rent' }).locator('xpath=ancestor::*[.//button[@aria-label="In a level"]][1]').getByRole('button', { name: 'In a level' }).first().click(); await p.waitForTimeout(300);
await p.getByRole('button', { name: 'Save', exact: true }).click();
console.log('save', await toasts(p, 1500), await errors(p));
await p.reload({ waitUntil: 'networkidle' });
await shot(p, '81-menu-main-saved', true);
const en2 = p.locator('input[aria-label="Label (English)"]'); const out = [];
for (let k = 0; k < await en2.count(); k++) out.push((await en2.nth(k).inputValue()) || await en2.nth(k).getAttribute('placeholder'));
console.log('saved order', out.join(', '));
await b.close();
