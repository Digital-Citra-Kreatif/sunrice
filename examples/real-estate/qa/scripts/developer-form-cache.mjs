// With the page cache on: two fresh visitors load /contact (the second gets the kept page) and the second sends
// the contact form. Prints the cache status and what the page says after sending.
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
const base = process.argv[2] ?? 'http://127.0.0.1:8200';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const first = await browser.newContext();
const r1 = await (await first.newPage()).goto(base + '/contact');
const second = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await second.newPage();
const r2 = await page.goto(base + '/contact');
console.log('first', r1.headers()['x-cache-status'], 'second', r2.headers()['x-cache-status']);
await page.waitForTimeout(1500); // the honeypot's minimum time
await page.fill('#form-contact-name', 'QA developer (test, delete me)');
await page.fill('#form-contact-email', 'qa-developer@example.com');
await page.selectOption('#form-contact-topic', 'other');
await page.fill('#form-contact-message', 'Page-cache CSRF check from the developer QA. Please ignore.');
await Promise.all([page.waitForNavigation(), page.click('form#form-contact button[type=submit]')]);
console.log('after', page.url(), await page.locator('[role=status]').first().textContent().catch(() => 'no status'));
await page.screenshot({ path: 'qa/screenshots/developer-24-contact-sent-with-page-cache.png' });
await browser.close();
