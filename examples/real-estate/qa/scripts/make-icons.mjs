// Renders public/favicon.svg to PNG icons: apple-touch-icon.png (180) and favicon-32.png (32), and a
// favicon.ico (a PNG inside an ICO container, which every current browser reads).
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);
const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
const root = new URL('../../', import.meta.url).pathname;
const svg = readFileSync(root + 'public/favicon.svg', 'utf8');
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
async function render(size, path) {
    const page = await browser.newPage({ viewport: { width: size, height: size } });
    await page.setContent(`<style>html,body{margin:0;background:transparent}svg{display:block;width:${size}px;height:${size}px}</style>${svg}`);
    const png = await page.screenshot({ omitBackground: true });
    writeFileSync(root + path, png);
    return png;
}
await render(180, 'public/apple-touch-icon.png');
const png32 = await render(32, 'public/favicon-32.png');
const header = Buffer.alloc(22);
header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(1, 4);
header.writeUInt8(32, 6); header.writeUInt8(32, 7); header.writeUInt8(0, 8); header.writeUInt8(0, 9);
header.writeUInt16LE(1, 10); header.writeUInt16LE(32, 12); header.writeUInt32LE(png32.length, 14); header.writeUInt32LE(22, 18);
writeFileSync(root + 'public/favicon.ico', Buffer.concat([header, png32]));
await browser.close();
