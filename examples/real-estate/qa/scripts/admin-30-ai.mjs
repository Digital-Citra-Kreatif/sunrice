import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
import fs from 'fs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/ai', { waitUntil: 'networkidle' });
const out = {};
for (const [name, ro, n] of [['Ayu laptop – Claude (read-only)', true, '77'], ['Ayu laptop – Claude Code (full)', false, '78']]) {
  await p.getByRole('button', { name: 'New token' }).click(); await p.waitForTimeout(400);
  await p.fill('#token-name', name);
  if (ro) await p.click('#token-read-only');
  await p.getByRole('button', { name: 'Create token' }).click(); await p.waitForTimeout(1200);
  await shot(p, `${n}-ai-token-created-${ro ? 'readonly' : 'full'}`);
  const txt = await p.locator('[role=dialog]').innerText().catch(() => p.locator('main main').innerText());
  const m = txt.match(/sra_[A-Za-z0-9_\-]+/); out[ro ? 'ro' : 'full'] = m && m[0];
  console.log(txt.replace(/sra_[A-Za-z0-9_\-]+/g, 'sra_<redacted>').slice(0, 800));
  await p.keyboard.press('Escape'); await p.waitForTimeout(400);
}
fs.writeFileSync('/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/ai-tokens.json', JSON.stringify(out));
await p.reload({ waitUntil: 'networkidle' }); await shot(p, '79-ai-tokens-list');
console.log((await p.locator('main main').innerText()).slice(0, 600));
await b.close();
