import { launch, session, shot, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const t0 = Date.now(); await p.goto(BASE + '/admin', { waitUntil: 'networkidle' }); console.log('dashboard load', Date.now() - t0, 'ms');
await shot(p, '148-dashboard-final', true);
console.log((await p.locator('main main').innerText()).slice(0, 900).replace(/\n/g, ' | '));
for (const u of ['/admin/cms/entries', '/admin/cms/collections/properties/entries', '/admin/cms/entries/47/edit', '/admin/cms/menus/main', '/admin/media']) { const t = Date.now(); await p.goto(BASE + u, { waitUntil: 'networkidle' }); console.log(u, Date.now() - t, 'ms'); }
const q = await b.newContext({ viewport: { width: 1440, height: 900 } }); const s = await q.newPage();
await s.goto(BASE + '/', { waitUntil: 'networkidle' }); await s.screenshot({ path: '/home/user/realestate/qa/screenshots/editor-149-site-home.png', fullPage: true });
await b.close();
