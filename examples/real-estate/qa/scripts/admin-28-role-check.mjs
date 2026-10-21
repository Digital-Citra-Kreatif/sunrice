import { launch, shot, BASE, login } from './admin-lib.mjs';
const b = await launch();
const users = [['budi', 'Budi'], ['citra', 'Citra'], ['dewi', 'Dewi'], ['eko', 'Eko']];
const urls = ['/admin/cms/entries', '/admin/cms/collections/pages/entries', '/admin/cms/collections/journal/entries', '/admin/cms/collections/journal/entries/create', '/admin/cms/collections/properties/entries', '/admin/cms/collections/properties/entries/create', '/admin/cms/collections/agents/entries',
  '/admin/cms/taxonomies/locations/terms', '/admin/cms/taxonomies/topics/terms', '/admin/cms/terms', '/admin/media', '/admin/cms/globals', '/admin/cms/menus/main', '/admin/cms/comments', '/admin/cms/submissions',
  '/admin/users', '/admin/roles', '/admin/settings', '/admin/settings/code', '/admin/cms/blueprints', '/admin/collections', '/admin/activity', '/admin/ai', '/admin/docs', '/admin/guide'];
for (const [u, f] of users) {
  const c = await b.newContext({ viewport: { width: 1440, height: 900 } }); const p = await c.newPage();
  await login(p, `${u}@lumina-estates.test`, `Lumina!${f}2026`);
  console.log(`\n##### ${u}: after login ${p.url()}`);
  await shot(p, `72-role-${u}-dashboard`);
  const side = await p.$$eval('[data-sidebar=menu-button], [data-slot=sidebar-menu-button]', e => e.map(x => x.innerText.trim()).filter(Boolean));
  console.log('sidebar:', side.join(', '));
  const res = [];
  for (const url of urls) {
    const r = await p.goto(BASE + url, { waitUntil: 'domcontentloaded' });
    const final = p.url().replace(BASE, '');
    const h = await p.locator('h1').first().innerText().catch(() => '');
    res.push(`${r.status()} ${url}${final !== url ? ' -> ' + final : ''} [${h.trim()}]`);
    if (u === 'eko' && url === '/admin/cms/collections/journal/entries') await shot(p, '73-role-eko-forbidden-journal');
  }
  console.log(res.join('\n'));
  await c.close();
}
await b.close();
