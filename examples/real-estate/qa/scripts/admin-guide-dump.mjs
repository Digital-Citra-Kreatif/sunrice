import { launch, ctx, BASE } from './admin-lib.mjs';
import fs from 'fs';
const out = '/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/guide';
fs.mkdirSync(out, { recursive: true });
const pages = ['panel/welcome','panel/dashboard','panel/media','panel/appearance','panel/settings','panel/users-roles','panel/ai-access','panel/activity-log','website/entries','website/languages','website/terms','website/globals','website/menus','website/comments','website/submissions','website/seo','website/structure','website/settings'];
const b = await launch(); const c = await ctx(b); const p = await c.newPage();
for (const g of pages) { await p.goto(BASE + '/admin/guide/' + g, { waitUntil: 'networkidle' }); const t = await p.locator('article, .prose').first().innerText().catch(async () => p.locator('main main').innerText()); fs.writeFileSync(`${out}/${g.replace('/', '-')}.txt`, t); console.log(g, t.length); }
await b.close();
