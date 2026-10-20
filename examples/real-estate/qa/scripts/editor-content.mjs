// Shared content + form-filling helpers for the editor run
import { pick, toasts, errors } from './editor-lib.mjs';
export async function fillRich(p, sel, html) {
  // type rich text via the TipTap editor: paste HTML through clipboard event
  const ed = p.locator(sel);
  await ed.click();
  await p.keyboard.press('Control+a');
  await p.evaluate(({ sel, html }) => {
    const el = document.querySelector(sel);
    const dt = new DataTransfer();
    dt.setData('text/html', html);
    dt.setData('text/plain', html.replace(/<[^>]+>/g, ' '));
    el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  }, { sel, html });
  await p.waitForTimeout(200);
}
export async function check(p, scopeSel, label) {
  const scope = scopeSel ? p.locator(scopeSel) : p;
  await scope.getByRole('checkbox', { name: label, exact: true }).first().click();
}

// spec: { title, slug?, fields: { handle: value }, types: { handle: 'select'|'rich'|'checks'|'entry'|'entries'|'toggle'|'repeater' } }
export async function fillFields(p, fields, types = {}, prefix = 'data') {
  for (const [h, v] of Object.entries(fields)) {
    const t = types[h] || 'text';
    const id = `#${prefix}-${h}`;
    if (v == null) continue;
    if (t === 'text') { await p.fill(id, String(v)); }
    else if (t === 'select') { if (!(await pick(p, id, v))) throw new Error('select ' + h + ' ' + v); }
    else if (t === 'rich') { await fillRich(p, id, v); }
    else if (t === 'toggle') { if (v) await p.click(id); }
    else if (t === 'checks') { for (const l of v) await p.getByText(l, { exact: true }).first().click(); }
    else if (t === 'entry' || t === 'entries') {
      for (const title of [].concat(v)) {
        await p.fill(id, title.slice(0, 12)); await p.waitForTimeout(700);
        const opt = p.getByRole('option', { name: title }).first();
        if (await opt.count()) await opt.click();
        else { console.log('ENTRY OPTION MISSING', h, title, await p.getByRole('option').allInnerTexts()); }
        await p.waitForTimeout(250);
      }
    }
    else if (t.startsWith('repeater:')) {
      const btn = t.slice(9);
      for (let i = 0; i < v.length; i++) {
        await p.getByRole('button', { name: btn, exact: true }).click(); await p.waitForTimeout(200);
        for (const [k, val] of Object.entries(v[i])) {
          const sid = `#${prefix}-${h}-${i}-${k}`;
          if (k === 'network') await pick(p, sid, val); else await p.fill(sid, val);
        }
      }
    }
  }
}
export async function createEntry(p, BASE, coll, title, fields, types, { publish = true, slug } = {}) {
  await p.goto(BASE + `/admin/cms/collections/${coll}/entries/create`, { waitUntil: 'networkidle' });
  await p.fill('#title', title);
  if (slug) await p.fill('#slug', slug);
  await fillFields(p, fields, types);
  const t0 = Date.now();
  await p.getByRole('button', { name: 'Create draft' }).click();
  await p.waitForSelector('text=Save draft', { timeout: 15000 }).catch(() => {});
  await p.waitForLoadState('networkidle');
  const tt = await toasts(p, 600); const er = await errors(p);
  console.log('created', coll, title, Date.now() - t0 + 'ms', tt, er.length ? er : '', p.url());
  if (publish && !er.length) {
    await p.getByRole('button', { name: 'Publish', exact: true }).click();
    console.log('  publish', await toasts(p, 1200));
  }
}

export async function openPublishMenu(p) {
  await p.keyboard.press('Escape'); await p.locator('h1').first().click().catch(()=>{}); await p.waitForTimeout(150);
  const pub = p.getByRole('button', { name: /^Publish/ }).first();
  const box = await pub.boundingBox();
  await p.mouse.click(box.x + box.width + 18, box.y + box.height / 2);
  await p.waitForTimeout(300);
}
// dayLabel like 'Monday, June 15th, 2026'; time 'HH:MM'
export async function publishOnDate(p, dayLabel, time) {
  await openPublishMenu(p);
  await p.getByRole('menuitem', { name: 'Publish on a date…' }).click();
  await p.waitForTimeout(400);
  const dlg = p.getByRole('dialog', { name: 'Publish on a date' });
  await dlg.getByText(/20\d\d/).first().click(); await p.waitForTimeout(300);
  const pop = p.locator('[data-slot=popover-content][data-state=open]');
  for (let i = 0; i < 24 && !(await pop.getByRole('button', { name: dayLabel }).count()); i++) {
    await pop.getByRole('button', { name: 'Go to the previous month' }).click(); await p.waitForTimeout(120);
  }
  await pop.getByRole('button', { name: dayLabel }).click();
  await pop.locator('input').fill(time);
  await dlg.locator('h2').click(); await p.waitForTimeout(200);
  const txt = await dlg.innerText();
  await dlg.getByRole('button', { name: 'Publish' }).click();
  const { toasts } = await import('./editor-lib.mjs');
  return [txt.match(/Date and time\n(.*)/)?.[1], await toasts(p, 1200)];
}

export async function addBlock(p, name) {
  await p.getByRole('button', { name: 'Add block' }).click(); await p.waitForTimeout(250);
  await p.getByRole('menuitem', { name, exact: true }).click(); await p.waitForTimeout(300);
}
export async function fillLink(p, id, url, text) {
  await p.fill(id, url);
  if (text) await p.locator(id).locator('xpath=ancestor::*[.//input[@placeholder="Link text"]][1]').getByPlaceholder('Link text').fill(text);
}
export async function setParent(p, title) {
  await p.fill('#parent', title.slice(0, 10)); await p.waitForTimeout(800);
  const o = p.getByRole('option', { name: title }).first();
  if (await o.count()) await o.click(); else console.log('PARENT OPTION MISSING', title, await p.getByRole('option').allInnerTexts());
  await p.waitForTimeout(300);
}

export async function openTranslation(p, BASE, id, label = 'Bahasa Indonesia') {
  const r = await p.goto(BASE + `/admin/cms/entries/${id}/edit?locale=id`, { waitUntil: 'networkidle' });
  if (r.status() === 404) {
    await p.goto(BASE + `/admin/cms/entries/${id}/edit`, { waitUntil: 'networkidle' });
    await p.getByRole('button', { name: label }).click(); await p.waitForTimeout(800); await p.waitForLoadState('networkidle');
  }
}
