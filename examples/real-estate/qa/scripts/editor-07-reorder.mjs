import { launch, session, shot, BASE, toasts, drag } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('request', r => { if (r.method() !== 'GET') console.log('REQ', r.method(), r.url()); });
await p.goto(BASE + '/admin/cms/collections/agents/entries', { waitUntil: 'networkidle' });
await p.getByRole('button', { name: 'Reorder' }).click();
await p.waitForTimeout(600);
await drag(p, p.getByLabel('Move Sekar Ayuningtyas'), p.getByLabel('Move Rafael Hartono'), { dy: -10 });
console.log(await toasts(p));
await shot(p, '12-agents-after-drag');
console.log((await p.locator('main main').innerText()).slice(150, 500));
// keyboard: focus Maarten handle, space, up, space
await p.getByLabel('Move Maarten de Vries').focus();
await p.keyboard.press('Space'); await p.waitForTimeout(200);
await p.keyboard.press('ArrowUp'); await p.waitForTimeout(200);
await p.keyboard.press('Space'); await p.waitForTimeout(1000);
console.log('kbd', await toasts(p));
console.log((await p.locator('main main').innerText()).slice(150, 500));
// put Maarten back last via keyboard
await p.getByLabel('Move Maarten de Vries').focus();
await p.keyboard.press('Space'); await p.waitForTimeout(200);
await p.keyboard.press('ArrowDown'); await p.waitForTimeout(200);
await p.keyboard.press('Space'); await p.waitForTimeout(1000);
await p.getByRole('button', { name: 'Done' }).click();
await p.reload({ waitUntil: 'networkidle' });
console.log('after reload', (await p.locator('tbody').innerText()).replace(/\s+/g,' '));
await b.close();
