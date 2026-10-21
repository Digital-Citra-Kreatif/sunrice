import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/');
const { chromium } = require('playwright-core');
export const BASE = 'http://127.0.0.1:8200';
export const SHOTS = '/home/user/realestate/qa/screenshots';
const SP = '/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad';
export const USERS = {
  budi: ['budi@lumina-estates.test', 'Lumina!Budi2026'],
  ayu: ['ayu@lumina-estates.test', 'Lumina!Admin2026'],
  citra: ['citra@lumina-estates.test', 'Lumina!Citra2026'],
  dewi: ['dewi@lumina-estates.test', 'Lumina!Dewi2026'],
};
export async function launch() {
  return chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
}
export async function session(browser, who = 'budi') {
  const statePath = `${SP}/editor-state-${who}.json`;
  const opts = { viewport: { width: 1440, height: 900 } };
  if (fs.existsSync(statePath)) opts.storageState = statePath;
  const c = await browser.newContext(opts);
  const p = await c.newPage();
  logErrors(p);
  await p.goto(BASE + '/admin', { waitUntil: 'networkidle' });
  if (p.url().includes('/login')) {
    await p.fill('input[type=email], input[name=email]', USERS[who][0]);
    await p.fill('input[type=password]', USERS[who][1]);
    await Promise.all([p.waitForLoadState('networkidle'), p.click('button[type=submit]')]);
    await p.waitForTimeout(800);
    await c.storageState({ path: statePath });
  }
  return { c, p };
}
let n = null;
export async function shot(page, name, full = false) {
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${SHOTS}/editor-${name}.png`, fullPage: full });
  console.log('shot', name);
}
export function logErrors(page) {
  page.on('pageerror', e => console.log('PAGEERROR', e.message));
  page.on('console', m => { if (m.type() === 'error') console.log('CONSOLE', m.text().slice(0, 300)); });
  page.on('response', r => { if (r.status() >= 400) console.log('HTTP', r.status(), r.request().method(), r.url()); });
}
export async function toasts(page, wait = 1200) { await page.waitForTimeout(wait); return page.locator('[data-sonner-toast]').allInnerTexts(); }
export async function errors(page) { return page.$$eval('[id$="-error"], .text-destructive, [data-slot=form-message]', e => e.map(x => (x.id||'') + ': ' + x.innerText.trim()).filter(x => x.length > 3)); }
export async function pick(page, sel, name) {
  await page.click(sel); await page.waitForTimeout(200);
  const opt = page.getByRole('option', { name, exact: true });
  if (!(await opt.count())) { console.log('OPTIONS for', sel, await page.getByRole('option').allInnerTexts()); await page.keyboard.press('Escape'); return false; }
  await opt.first().click(); await page.waitForTimeout(150); return true;
}
export async function dumpForm(page) {
  return page.$$eval('input, textarea, select, button[role=combobox], [contenteditable=true], button[role=switch], button[role=checkbox]', els => els.map(e => {
    const lab = e.id ? document.querySelector(`label[for="${e.id}"]`) : null;
    return `${e.tagName.toLowerCase()}${e.type ? '[' + e.type + ']' : ''} id=${e.id} name=${e.getAttribute('name') || ''} role=${e.getAttribute('role') || ''} label=${lab ? lab.innerText.trim() : (e.getAttribute('aria-label') || '')}`;
  }));
}
export async function drag(page, from, to, { dy = 0 } = {}) {
  const a = await from.boundingBox(); const t = await to.boundingBox();
  await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
  await page.mouse.down();
  await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2 + 8, { steps: 4 });
  const ty = t.y + t.height / 2 + dy;
  await page.mouse.move(t.x + t.width / 2, ty, { steps: 15 });
  await page.waitForTimeout(250);
  await page.mouse.up();
  await page.waitForTimeout(800);
}
