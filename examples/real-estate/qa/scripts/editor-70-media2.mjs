import { launch, session, shot, BASE, toasts, errors, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/media', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Open qa-test-landscape' }).click(); await p.waitForTimeout(800);
console.log(p.url());
await shot(p, '121-media-detail');
console.log((await p.locator('[role=dialog]').innerText().catch(() => p.locator('main main').innerText())).slice(0, 900));
console.log((await dumpForm(p)).filter(x => !x.includes('Search')).join('\n'));
await b.close();
