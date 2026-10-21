const { chromium } = require('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules/playwright-core');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto('http://127.0.0.1:8200/_inertia/devtools/entries');
  await p.screenshot({ path: '/home/user/realestate/qa/screenshots/security-01-inertia-devtools-open.png' });
  await p.goto('http://127.0.0.1:8200/admin/login');
  await p.fill('input[type=email]', 'fajar@lumina-estates.test');
  await p.fill('input[type=password]', 'Lumina!Fajar2026');
  await p.keyboard.press('Enter');
  await p.waitForTimeout(1500);
  await p.screenshot({ path: '/home/user/realestate/qa/screenshots/security-02-password-oracle-no-access.png' });
  await b.close();
})();
