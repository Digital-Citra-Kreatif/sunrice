import { launch, ctx, shot, BASE, logErrors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
await p.goto(BASE + '/admin/cms/menus/main', { waitUntil: 'networkidle' });
for (const k of ['Listing', 'Term', 'Link', 'Entry']) {
  await p.getByRole('button', { name: k, exact: true }).click(); await p.waitForTimeout(600);
  await shot(p, '60-menu-add-' + k.toLowerCase());
  console.log('=== ' + k); console.log((await p.locator('[role=dialog]').innerText().catch(() => 'no dialog')).slice(0, 1200));
  console.log(await p.$$eval('[role=dialog] input, [role=dialog] button, [role=dialog] [role=combobox], [role=dialog] select', e => e.map(x => `${x.tagName} id=${x.id} ph=${x.getAttribute('placeholder')} text=${(x.innerText||x.value||'').slice(0,40)}`)).catch(()=>[]));
  await p.keyboard.press('Escape'); await p.waitForTimeout(300);
}
await b.close();
