import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/');
const { chromium } = require('playwright-core');
export const BASE = 'http://127.0.0.1:8200';
export const SHOTS = '/home/user/realestate/qa/screenshots';
const STATE = '/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/admin-state-ayu.json';
export async function launch() {
  return chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
}
export async function ctx(browser, { state = true } = {}) {
  const opts = { viewport: { width: 1440, height: 900 } };
  if (state && fs.existsSync(STATE)) opts.storageState = STATE;
  const c = await browser.newContext(opts);
  return c;
}
export async function saveState(c) { await c.storageState({ path: STATE }); }
export async function shot(page, name, full = false) {
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${SHOTS}/admin-${name}.png`, fullPage: full });
  console.log('shot', name);
}
export async function login(page, email = 'ayu@lumina-estates.test', password = 'Lumina!Admin2026') {
  await page.goto(BASE + '/admin/login');
  await page.fill('input[type=email], input[name=email]', email);
  await page.fill('input[type=password]', password);
  await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit]')]);
  await page.waitForTimeout(800);
}
export async function texts(page, sel = 'main') {
  return (await page.locator(sel).first().innerText()).slice(0, 4000);
}
export function logErrors(page) {
  page.on('pageerror', e => console.log('PAGEERROR', e.message));
  page.on('console', m => { if (m.type() === 'error') console.log('CONSOLE', m.text()); });
  page.on('requestfailed', r => console.log('REQFAIL', r.url()));
  page.on('response', r => { if (r.status() >= 400) console.log('HTTP', r.status(), r.url()); });
}
export async function labelIds(page) {
  return page.$$eval('input[id$="-label"]', els => els.map(e => e.id).filter(id => /^field-.*-label$/.test(id) && !/config/.test(id)));
}
// click an "Add field" button (locator), choose type, return id prefix (e.g. "field-f3") of the new field
export async function addField(page, addBtn, type, { label, handle, required, translatable } = {}) {
  const before = await labelIds(page);
  await addBtn.click(); await page.waitForTimeout(250);
  await page.getByRole('menuitem', { name: type, exact: true }).click(); await page.waitForTimeout(350);
  const after = await labelIds(page);
  const id = after.find(x => !before.includes(x));
  if (!id) throw new Error('no new field for ' + type);
  const pre = id.replace(/-label$/, '');
  if (label) await page.fill('#' + pre + '-label', label);
  if (handle) await page.fill('#' + pre + '-handle', handle);
  if (required) await page.click('#' + pre + '-required');
  if (translatable) { await page.click('#' + pre + '-translatable'); await page.getByRole('option', { name: translatable }).click(); }
  return pre;
}
export async function toasts(page) { await page.waitForTimeout(1500); return page.locator('[data-sonner-toast]').allInnerTexts(); }
export async function errors(page) { return page.$$eval('[id$="-error"], .text-destructive, [data-slot=form-message]', e => e.map(x => (x.id||'') + ': ' + x.innerText.trim()).filter(x => x.length > 3)); }
async function radixPick(page, buttonSel, name) {
  await page.click(buttonSel); await page.waitForTimeout(200);
  await page.getByRole('option', { name, exact: true }).click(); await page.waitForTimeout(150);
}
// build fields described by specs; addBtnFn returns the "Add field" locator for this container
export async function buildFields(page, addBtnFn, specs) {
  for (const f of specs) {
    const pre = await addField(page, addBtnFn(), f.type, { label: f.label, handle: f.handle, required: f.required, translatable: f.tr });
    const card = page.locator(`#${pre}-validation`).locator('xpath=ancestor::*[.//*[@id="' + pre + '-label"]][1]');
    const set = async (k, v) => { await page.fill(`#${pre}-config-${k}`, String(v)); };
    if (f.max != null) await set('max', f.max);
    if (f.min != null) await set('min', f.min);
    if (f.step != null) await set('step', f.step);
    if (f.rows != null) await set('rows', f.rows);
    if (f.maxKb != null) await set('max_kb', f.maxKb);
    if (f.ext != null) await set('types', f.ext);
    if (f.placeholder != null) await set('placeholder', f.placeholder);
    if (f.options) {
      for (const [v, l] of f.options) {
        await card.getByRole('button', { name: 'Add option' }).first().click(); await page.waitForTimeout(120);
        await card.getByPlaceholder('Value').last().fill(v);
        await card.getByPlaceholder('Label').last().fill(l);
      }
    }
    if (f.multiple) await page.click(`#${pre}-config-multiple`);
    if (f.default != null) { if (f.type === 'Toggle') await page.click(`#${pre}-config-default`); else await set('default', f.default); }
    if (f.types) for (const t of f.types) await card.getByRole('checkbox', { name: t, exact: true }).first().click();
    if (f.collections) for (const t of f.collections) await card.getByRole('checkbox', { name: t, exact: true }).first().click();
    if (f.blocks) for (const t of f.blocks) await card.getByRole('checkbox', { name: t, exact: true }).first().click();
    if (f.taxonomy) await radixPick(page, `#${pre}-config-taxonomy`, f.taxonomy);
    if (f.fieldset) await radixPick(page, `#${pre}-config-fieldset`, f.fieldset);
    if (f.validation) await page.fill(`#${pre}-validation`, f.validation);
    if (f.instructions) await page.fill(`#${pre}-instructions`, f.instructions);
    if (f.children) await buildFields(page, () => card.getByRole('button', { name: 'Add field' }).last(), f.children);
    // collapse the card to keep the page manageable
    f._pre = pre;
  }
}
export async function createDef(page, kind, title, handle, specs, shotName) {
  // kind: 'fieldsets' | 'blueprints'
  await page.goto(BASE + `/admin/cms/${kind}/create`, { waitUntil: 'networkidle' });
  await page.fill('#title', title); await page.fill('#handle', handle);
  await buildFields(page, () => page.getByRole('button', { name: 'Add field' }).last(), specs);
  if (shotName) await shot(page, shotName, true);
  await page.locator('button[type=submit]').first().click();
  const t = await toasts(page); const e = await errors(page);
  console.log(kind, handle, t, page.url(), e.length ? e : '');
  return { t, e };
}
export async function pick(page, sel, name) {
  await page.click(sel); await page.waitForTimeout(200);
  const opt = page.getByRole('option', { name, exact: true });
  if (!(await opt.count())) { console.log('OPTIONS for', sel, await page.getByRole('option').allInnerTexts()); await page.keyboard.press('Escape'); return false; }
  await opt.first().click(); await page.waitForTimeout(150); return true;
}
export async function createCollection(page, c, shotName) {
  await page.goto(BASE + '/admin/collections/create', { waitUntil: 'networkidle' });
  await page.fill('#field-title', c.title);
  await page.fill('#field-handle', c.handle);
  if (c.blueprint) await pick(page, '#field-blueprint', c.blueprint);
  for (const k of ['dated', 'ordered', 'hierarchical', 'comments']) if (c[k]) await page.click('#field-' + k);
  if (c.route) await page.fill('#field-route', c.route);
  if (c.listing_route) await page.fill('#field-listing_route', c.listing_route);
  if (c.listing_blueprint) await pick(page, '#field-listing_blueprint', c.listing_blueprint);
  if (c.per_page) await page.fill('#field-per_page', String(c.per_page));
  if (c.schema) await pick(page, '#field-schema_type', c.schema);
  if (c.seo_title) await pick(page, '#field-seo_title_field', c.seo_title);
  if (c.seo_description) await pick(page, '#field-seo_description_field', c.seo_description);
  if (c.seo_image) await pick(page, '#field-seo_image_field', c.seo_image);
  if (shotName) await shot(page, shotName, true);
  await page.locator('button[type=submit]').first().click();
  console.log('collection', c.handle, await toasts(page), page.url(), await errors(page));
}
