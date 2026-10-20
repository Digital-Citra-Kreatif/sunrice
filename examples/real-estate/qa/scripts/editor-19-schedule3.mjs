import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
await p.goto(BASE + '/admin/cms/entries/25/edit', { waitUntil: 'networkidle' });
console.log('status card', (await p.locator('main main').innerText()).match(/Status\n.*\n.*/g));
const pub = p.getByRole('button', { name: /^Publish/ }).first();
const box = await pub.boundingBox();
await p.mouse.click(box.x + box.width + 18, box.y + box.height / 2);
await p.waitForTimeout(300);
console.log('menu', await p.getByRole('menuitem').allInnerTexts());
await shot(p, '36b-published-menu');
const unpub = p.getByRole('menuitem', { name: /Unpublish/ });
if (await unpub.count()) {
  await unpub.click(); await p.waitForTimeout(500);
  const conf = p.locator('[role=alertdialog]');
  if (await conf.count()) { console.log('confirm', await conf.innerText()); await conf.getByRole('button', { name: /Unpublish/ }).click(); }
  console.log(await toasts(p, 1500));
  console.log('top', (await p.locator('main main').innerText()).slice(0, 150).replace(/\n/g, ' | '));
}
// schedule properly
const pub2 = p.getByRole('button', { name: 'Publish', exact: true });
const b2 = await pub2.boundingBox();
await p.mouse.click(b2.x + b2.width + 18, b2.y + b2.height / 2);
await p.getByRole('menuitem', { name: 'Publish on a date…' }).click();
await p.waitForTimeout(400);
const dlg = p.getByRole('dialog', { name: 'Publish on a date' });
await dlg.getByText(/2026/).first().click(); await p.waitForTimeout(300);
const pop = p.locator('[data-slot=popover-content][data-state=open]');
console.log('day button names', (await pop.locator('button').evaluateAll(bs => bs.map(b => b.getAttribute('aria-label') || b.innerText))).slice(0, 40).join(' ; '));
const when = new Date(Date.now() + 5 * 60000 + 40000);
const hh = String(when.getUTCHours()).padStart(2, '0'), mm = String(when.getUTCMinutes()).padStart(2, '0');
await pop.getByRole('button', { name: 'Tuesday, October 20th, 2026' }).click().catch(async () => { await pop.getByRole('button', { name: 'Go to the next month' }).click(); await pop.getByRole('button', { name: 'Tuesday, October 20th, 2026' }).click(); });
const ti = pop.locator('input'); console.log('time input type', await ti.getAttribute('type'), await ti.inputValue()); await ti.click(); await ti.press('Control+a').catch(()=>{}); await ti.fill(`${hh}:${mm}`); console.log('time now', await ti.inputValue());
await p.waitForTimeout(300);
await dlg.locator('h2').click(); await p.waitForTimeout(300);
console.log('dialog', await dlg.innerText());
await shot(p, '37-schedule-set-correct');
await dlg.getByRole('button', { name: 'Publish' }).click();
console.log(await toasts(p, 1500));
console.log('top', (await p.locator('main main').innerText()).slice(0, 150).replace(/\n/g, ' | '));
console.log('status card', (await p.locator('main main').innerText()).match(/Status\n.*\n.*/g));
await shot(p, '38-scheduled-state');
console.log('scheduled', hh + ':' + mm, 'now', new Date().toISOString());
await b.close();
