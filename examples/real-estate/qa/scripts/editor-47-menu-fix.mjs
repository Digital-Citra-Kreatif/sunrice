import { launch, session, shot, BASE, toasts, errors, drag } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('dialog', async d => { console.log('DIALOG', d.message()); await d.accept(); });
p.on('request', r => { if (r.method() !== 'GET' && r.url().includes('menus')) console.log('REQ', r.method(), r.url(), (r.postData() || '').slice(0, 1500)); });
async function dump(tag) {
  const items = await p.$$eval('input[aria-label="Label (English)"]', els => els.map(e => {
    let depth = 0, n = e; while ((n = n.parentElement)) if (n.querySelector && n.matches('[class*="border"]') && n.querySelector('button[aria-label^="Move"]')) depth++;
    const idIn = e.closest('div').parentElement.querySelector('input[aria-label="Label (Indonesian)"]');
    return (e.value || e.placeholder) + '/' + (idIn ? (idIn.value || '-') : '?');
  }));
  console.log(tag, items.join(' | '));
}
const mode = process.argv[2] || 'fix';
await p.goto(BASE + '/admin/cms/menus/main', { waitUntil: 'networkidle' });
await dump('loaded');
if (mode === 'fix') {
  // remove rows whose Indonesian label is empty among duplicates: Neighbourhoods(1st), Team(1st), Journal(2nd)
  const rm = async (name, nth) => {
    const btn = p.getByRole('button', { name: 'Move ' + name }).nth(nth);
    await btn.locator('xpath=ancestor::*[.//button[starts-with(@aria-label,"Remove")]][1]').getByRole('button', { name: /^Remove/ }).first().click();
    await p.waitForTimeout(300);
    const conf = p.locator('[role=alertdialog]'); if (await conf.count()) { console.log('confirm:', await conf.innerText()); await conf.getByRole('button', { name: /Remove/ }).click(); }
  };
  await rm('Neighbourhoods', 0); await rm('Journal', 1); await rm('Team', 0);
  await p.getByRole('button', { name: 'Move Villas' }).locator('xpath=ancestor::*[.//button[@aria-label="Out a level"]][1]').getByRole('button', { name: 'Out a level' }).first().click();
  await dump('before save');
  await p.getByRole('button', { name: 'Save', exact: true }).click(); console.log(await toasts(p, 1500), await errors(p));
  await p.reload({ waitUntil: 'networkidle' }); await dump('after reload');
  await shot(p, '82-menu-main-fixed', true);
}
await b.close();
