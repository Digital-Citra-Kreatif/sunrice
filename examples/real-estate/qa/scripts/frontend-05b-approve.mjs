// Approve the QA guest's first and markup comments, reply to the first from the admin.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await login(c);
await p.goto(BASE + '/admin/cms/comments', { waitUntil: 'networkidle' });
const card = (t) => p.locator('main li, main article, main [data-comment], main tr').filter({ hasText: t }).last();
for (const t of ['QA guest first comment', 'QA markup test']) {
  await card(t).getByRole('button', { name: 'Approve' }).click();
  await p.waitForTimeout(1200);
  console.log('approved', t, (await p.locator('[data-sonner-toast]').allInnerTexts()).join(' / '));
}
await card('QA guest first comment').getByRole('button', { name: 'Reply' }).click();
await p.waitForTimeout(600);
await shot(p, '35-admin-reply-dialog', false);
const box = p.locator('[role=dialog] textarea, main textarea').last();
await box.fill('QA reply from Ayu in the admin: yes, the same checks apply to leasehold land.');
await p.locator('[role=dialog] button, main button').filter({ hasText: /^(Reply|Send|Post|Publish)/ }).last().click();
await p.waitForTimeout(1500);
console.log('after reply', (await p.locator('[data-sonner-toast]').allInnerTexts()).join(' / '));
await shot(p, '36-admin-after-reply', false);
await b.close();
