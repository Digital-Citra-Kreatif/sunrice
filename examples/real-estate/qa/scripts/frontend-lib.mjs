// Shared helpers for the frontend (visitor) QA scripts.
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
export const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
export const BASE = 'http://127.0.0.1:8200';
export const SHOTS = '/home/user/realestate/qa/screenshots/';
export async function launch(opts = {}) {
  return chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', ...opts });
}
export const DESKTOP = { viewport: { width: 1440, height: 900 } };
export const MOBILE = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
export async function shot(page, name, full = true) {
  await page.screenshot({ path: SHOTS + 'frontend-' + name + '.png', fullPage: full });
}
export function logErrors(page, sink = []) {
  page.on('console', (m) => m.type() === 'error' && sink.push('console: ' + m.text()));
  page.on('pageerror', (e) => sink.push('pageerror: ' + e.message));
  page.on('requestfailed', (r) => sink.push('FAILED ' + r.url()));
  page.on('response', (r) => r.status() >= 400 && sink.push(r.status() + ' ' + r.url()));
  return sink;
}
export async function revealAll(page) {
  await page.evaluate(() => document.querySelectorAll('.reveal').forEach((e) => e.classList.add('is-visible')));
}
export async function login(context, email = 'ayu@lumina-estates.test', password = 'Lumina!Admin2026') {
  const p = await context.newPage();
  await p.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
  await p.fill('input[type=email]', email);
  await p.fill('input[type=password]', password);
  await p.click('button[type=submit]');
  await p.waitForLoadState('networkidle');
  await p.waitForTimeout(1000);
  return p;
}
