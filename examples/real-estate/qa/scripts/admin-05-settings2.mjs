import { launch, ctx, shot, BASE, logErrors } from './admin-lib.mjs';
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const toast = async () => { await p.waitForTimeout(1500); console.log('TOAST', await p.locator('[data-sonner-toast]').allInnerTexts()); };
await p.goto(BASE + '/admin/settings/site', { waitUntil: 'networkidle' });
await p.fill('#field-name', 'Lumina Estates');
await p.fill('#field-description', 'Premium homes, villas, apartments and land for sale and rent in Jakarta and Bali.');
await p.fill('#field-separator', '·');
const sd = p.locator('#field-structuredData'); console.log('sd state', await sd.getAttribute('data-state'));
if ((await sd.getAttribute('data-state')) !== 'checked') await sd.click();
await p.click('button[type=submit]'); await toast();
await p.reload({ waitUntil: 'networkidle' });
console.log('after reload sd', await p.locator('#field-structuredData').getAttribute('data-state'), 'sep', await p.inputValue('#field-separator'));
await shot(p, '13-site-saved', true);
// comments
await p.goto(BASE + '/admin/settings/comments', { waitUntil: 'networkidle' });
await shot(p, '14-comments-before', true);
await p.click('#field-moderation'); await p.getByRole('option', { name: "Hold a commenter's first comment for approval" }).click();
await p.fill('#field-depth', '3');
await p.fill('#field-closeAfterDays', '365');
const nm = p.locator('#field-notifyModerators'); console.log('nm', await nm.getAttribute('data-state'));
if ((await nm.getAttribute('data-state')) !== 'checked') await nm.click();
await p.click('button[type=submit]'); await toast();
await shot(p, '15-comments-saved', true);
// site code
await p.goto(BASE + '/admin/settings/code', { waitUntil: 'networkidle' });
await p.fill('#field-head', '<!-- Lumina analytics placeholder: replace with the real snippet -->');
await p.click('button[type=submit]'); await toast();
await shot(p, '16-site-code-saved');
// mail
await p.goto(BASE + '/admin/settings/mail', { waitUntil: 'networkidle' });
await shot(p, '17-mail');
await p.getByRole('button', { name: 'Send a test email' }).click(); await p.waitForTimeout(800);
await shot(p, '18-mail-test-dialog');
console.log((await p.locator('body').innerText()).slice(-800));
await b.close();
