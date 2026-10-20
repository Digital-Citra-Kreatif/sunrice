import { launch, shot, BASE, login, logErrors } from './admin-lib.mjs';
const b = await launch(); const c = await b.newContext({ viewport: { width: 1440, height: 900 } }); const p = await c.newPage(); logErrors(p);
await login(p, 'eko@lumina-estates.test', 'Lumina!Eko2026');
await p.goto(BASE + '/admin/cms/collections/properties/entries/create', { waitUntil: 'networkidle' });
await shot(p, '74-eko-property-create', true);
const t = await p.locator('main main').innerText(); 
const i = t.indexOf('Agent'); console.log(t.slice(i - 50, i + 700));
await b.close();
