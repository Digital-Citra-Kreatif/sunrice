import { launch, session, shot, BASE, toasts, drag } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/pages/entries', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Reorder' }).click(); await p.waitForTimeout(600);
await shot(p, '61-pages-reorder-mode', true);
console.log((await p.locator('main main').innerText()).slice(0, 900).replace(/\n/g, ' | '));
await drag(p, p.getByLabel('Move Home'), p.getByLabel('Move About'), { dy: -12 });
console.log(await toasts(p));
// Move Contact above Privacy policy is already; move "Sell with us" above About? keep: Home, About, Services, Sell with us, Contact, Privacy, Terms
await shot(p, '62-pages-after-drag', true);
console.log((await p.locator('main main').innerText()).slice(0, 900).replace(/\n/g, ' | '));
// try dragging a child (Buy) under a different parent to see if nesting via drag works
await b.close();
