import { launch, session, shot, BASE, toasts } from './editor-lib.mjs';
import { fillFields, publishOnDate } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/collections/journal/entries', { waitUntil: 'networkidle' });
console.log((await p.locator('tbody tr').first().innerText()).replace(/\s+/g, ' '));
await p.goto(BASE + '/admin/cms/entries/35/edit', { waitUntil: 'networkidle' });
if (await p.getByRole('button', { name: 'Take over' }).count()) { await p.getByRole('button', { name: 'Take over' }).click(); await p.waitForTimeout(500); await p.getByRole('alertdialog').getByRole('button', { name: 'Take over' }).click().catch(()=>p.locator('[role=dialog]').getByRole('button', { name: 'Take over' }).click()); await p.waitForTimeout(1200); console.log('took over', await toasts(p, 300)); }
await fillFields(p, { author_agent: 'Rafael Hartono', related: ['Seminyak Beachside Villa'] }, { author_agent: 'entry', related: 'entries' });
console.log(await publishOnDate(p, 'Monday, September 28th, 2026', '09:00'));
await shot(p, '46-citra-post-published-by-budi');
await b.close();
