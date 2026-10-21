// Opens the mobile menu and screenshots it: node qa/scripts/mobile-nav.mjs <url> <out.png>
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
const [url, out] = process.argv.slice(2);
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const page = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
await page.goto(url, { waitUntil: 'networkidle' });
await page.click('[data-nav-toggle]');
await page.waitForTimeout(300);
await page.screenshot({ path: out });
await browser.close();
