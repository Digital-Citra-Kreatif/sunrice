import { launch, ctx, shot, BASE, logErrors, toasts } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const add = async (kind, target, label, url) => {
  await p.getByRole('button', { name: kind, exact: true }).click(); await p.waitForTimeout(400);
  if (kind === 'Link') await p.fill('#menu-url', url);
  else { await p.click('#menu-target'); await p.waitForTimeout(200); await p.getByRole('option', { name: target, exact: true }).first().click(); }
  if (label) await p.fill('#menu-label', label);
  await p.locator('[role=dialog]').getByRole('button', { name: 'Add', exact: true }).click(); await p.waitForTimeout(400);
};
await p.goto(BASE + '/admin/cms/menus/footer', { waitUntil: 'networkidle' });
await add('Listing', 'Properties'); await add('Listing', 'Neighbourhoods'); await add('Listing', 'Agents', 'Our team'); await add('Listing', 'Journal');
await add('Link', null, 'hello@lumina-estates.test', 'mailto:hello@lumina-estates.test');
// try a javascript: link to confirm it is refused
await p.getByRole('button', { name: 'Link', exact: true }).click(); await p.fill('#menu-url', 'javascript:alert(1)'); await p.fill('#menu-label', 'x');
await p.waitForTimeout(500); console.log('add disabled', await p.locator('[role=dialog]').getByRole('button', { name: 'Add', exact: true }).isDisabled());
console.log('js link dialog:', (await p.locator('[role=dialog]').innerText().catch(()=>'closed')).slice(0,300));
await shot(p, '63-menu-js-link');
if (await p.locator('[role=dialog]').count()) await p.keyboard.press('Escape');
await p.getByRole('button', { name: 'Save', exact: true }).click(); console.log('save', await toasts(p));
await shot(p, '64-menu-footer-saved', true);
// legal: try the Entry dialog with no pages
await p.goto(BASE + '/admin/cms/menus/legal', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Entry', exact: true }).click(); await p.fill('#menu-entry', 'Privacy'); await p.waitForTimeout(1000);
await shot(p, '65-menu-legal-no-pages');
console.log((await p.locator('[role=dialog]').innerText()).slice(0,400));
await b.close();
