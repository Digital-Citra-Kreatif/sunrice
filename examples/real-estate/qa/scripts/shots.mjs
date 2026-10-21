// Batch screenshots: node qa/scripts/shots.mjs <base-url> <out-dir> <prefix> <width> <path>... (full page)
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
const [base, out, prefix, width, ...paths] = process.argv.slice(2);
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const w = Number(width);
const context = await browser.newContext({ viewport: { width: w, height: w < 500 ? 844 : 900 }, deviceScaleFactor: w < 500 ? 2 : 1 });
for (const path of paths) {
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.querySelectorAll('.reveal').forEach((e) => e.classList.add('is-visible')));
    await page.waitForTimeout(300);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    const name = `${prefix}${path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home'}.png`;
    await page.screenshot({ path: `${out}/${name}`, fullPage: true });
    console.log(JSON.stringify({ path, status: response?.status(), overflow, errors, name }));
    await page.close();
}
await browser.close();
