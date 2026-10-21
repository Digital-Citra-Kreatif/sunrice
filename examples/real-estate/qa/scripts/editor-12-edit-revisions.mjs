import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('request', r => { if (r.method() !== 'GET') console.log('REQ', r.method(), r.url()); });
await p.goto(BASE + '/admin/cms/entries/10/edit', { waitUntil: 'networkidle' });
// fix heading: click into "Location" paragraph and press Subheading
await p.locator('#data-description p', { hasText: /^Location$/ }).click();
await p.getByRole('button', { name: 'Subheading' }).click();
console.log('desc', (await p.locator('#data-description').innerHTML()).slice(-300));
// unsaved change warning: try to navigate away via sidebar link
let dialogSeen = null;
p.on('dialog', async d => { dialogSeen = d.type() + ': ' + d.message(); await d.dismiss(); });
await p.getByRole('link', { name: 'Dashboard' }).click();
await p.waitForTimeout(800);
console.log('after nav attempt url', p.url(), 'dialog', dialogSeen, await p.locator('[role=alertdialog],[role=dialog]').allInnerTexts());
await shot(p, '21-unsaved-warning');
// cancel in-app dialog if any
const stay = p.getByRole('button', { name: /Stay|Keep editing|Cancel/ });
if (await stay.count()) await stay.first().click();
await p.waitForTimeout(300);
// Ctrl+S
await p.keyboard.press('Control+s');
console.log('ctrl+s', await toasts(p, 1500), p.url());
await shot(p, '22-after-ctrl-s');
console.log('header', (await p.locator('main main').innerText()).slice(0, 200));
await b.close();
