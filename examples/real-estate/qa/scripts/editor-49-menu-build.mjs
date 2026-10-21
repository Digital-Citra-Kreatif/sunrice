import { launch, session, shot, BASE, toasts, errors, pick } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const handle = process.argv[2] || 'main';
async function count() { return p.locator('button[aria-label^="Move "]').count(); }
async function addEntry(title, label) {
  await p.getByRole('button', { name: 'Entry', exact: true }).click(); await p.waitForTimeout(400);
  await p.fill('#menu-entry', title.slice(0, 8)); await p.waitForTimeout(800);
  await p.getByRole('option', { name: new RegExp('^' + title.replace(/[()]/g, '.')) }).first().click();
  if (label) await p.fill('#menu-label', label);
  await p.locator('[role=dialog]').getByRole('button', { name: 'Add' }).click(); await p.waitForTimeout(400);
}
async function addListing(name, label) {
  await p.getByRole('button', { name: 'Listing', exact: true }).click(); await p.waitForTimeout(400);
  const d = p.locator('[role=dialog]');
  await p.click('#menu-target'); await p.waitForTimeout(200);
  await p.getByRole('option', { name: new RegExp(name) }).first().click();
  if (label) await p.fill('#menu-label', label);
  await d.getByRole('button', { name: 'Add' }).click(); await p.waitForTimeout(400);
}
async function addLink(url, label) {
  await p.getByRole('button', { name: 'Link', exact: true }).click(); await p.waitForTimeout(400);
  const d = p.locator('[role=dialog]');
  await p.fill('#menu-url', url); await p.fill('#menu-label', label);
  await d.getByRole('button', { name: 'Add' }).click(); await p.waitForTimeout(400);
}
async function addTerm(name, label) {
  await p.getByRole('button', { name: 'Term', exact: true }).click(); await p.waitForTimeout(400);
  const d = p.locator('[role=dialog]');
  await p.click('#menu-target'); await p.waitForTimeout(200);
  console.log('term options', (await p.getByRole('option').allInnerTexts()).length);
  await p.getByRole('option', { name: new RegExp(name) }).first().click();
  if (label) await p.fill('#menu-label', label);
  await d.getByRole('button', { name: 'Add' }).click(); await p.waitForTimeout(400);
}
async function indent(name) {
  await p.getByRole('button', { name: 'Move ' + name }).last().locator('xpath=ancestor::*[.//button[@aria-label="In a level"]][1]').getByRole('button', { name: 'In a level' }).first().click(); await p.waitForTimeout(250);
}
async function setId(map) {
  const en = p.locator('input[aria-label="Label (English)"]'); const idl = p.locator('input[aria-label="Label (Indonesian)"]');
  for (let k = 0; k < await en.count(); k++) { const nm = (await en.nth(k).inputValue()) || (await en.nth(k).getAttribute('placeholder')); if (map[nm]) await idl.nth(k).fill(map[nm]); else console.log('no id label for', nm); }
}
await p.goto(BASE + `/admin/cms/menus/${handle}`, { waitUntil: 'networkidle' });
if (handle === 'main') {
  await addListing('Properties', 'Properties');
  await addLink('/properties?listing_type=for-sale', 'For sale'); await indent('For sale');
  await addLink('/properties?listing_type=for-rent', 'For rent'); await indent('For rent');
  await addTerm('Villa', 'Villas'); await indent('Villas');
  await addListing('Neighbourhoods', 'Neighbourhoods');
  await addListing('Agents', 'Team');
  await addListing('Journal', 'Journal');
  await addEntry('About'); await addEntry('Our story'); await indent('Our story');
  await addEntry('Services');
  await addEntry('Contact');
  await setId({ Properties: 'Properti', 'For sale': 'Dijual', 'For rent': 'Disewakan', Villas: 'Vila', Neighbourhoods: 'Kawasan', Team: 'Tim', Journal: 'Jurnal', About: 'Tentang kami', 'Our story': 'Kisah kami', Services: 'Layanan', Contact: 'Kontak' });
}
if (handle === 'footer') {
  await addListing('Properties', 'Properties'); await addListing('Neighbourhoods', 'Neighbourhoods'); await addListing('Agents', 'Our team'); await addListing('Journal', 'Journal');
  await addEntry('Services'); await addEntry('Sell with us'); await addEntry('Contact');
  await addLink('mailto:hello@lumina-estates.test', 'hello@lumina-estates.test');
  await setId({ Properties: 'Properti', Neighbourhoods: 'Kawasan', 'Our team': 'Tim kami', Journal: 'Jurnal', Services: 'Layanan', 'Sell with us': 'Jual bersama kami', Contact: 'Kontak', 'hello@lumina-estates.test': 'hello@lumina-estates.test' });
}
if (handle === 'legal') {
  await addEntry('Privacy policy', 'Privacy'); await addEntry('Terms');
  await setId({ Privacy: 'Privasi', Terms: 'Syarat dan ketentuan' });
}
const before = await count();
await p.getByRole('button', { name: 'Save', exact: true }).click(); console.log('save1', await toasts(p, 1200), await errors(p));
await p.reload({ waitUntil: 'networkidle' }); const after1 = await count();
if (process.argv[3] === 'twice') { await p.getByRole('button', { name: 'Save', exact: true }).click().catch(() => {}); await p.waitForTimeout(1200); }
await p.reload({ waitUntil: 'networkidle' }); const after2 = await count();
console.log(handle, 'items before save', before, 'after save 1', after1, 'after unchanged save 2', after2);
await shot(p, `83-menu-${handle}-built`, true);
await b.close();
