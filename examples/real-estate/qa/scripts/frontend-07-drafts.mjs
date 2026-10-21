// Drafts and previews: a live entry with a newer draft, as Ayu (banner, ?draft) and as a guest; the editor's preview link.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const POST = '/journal/qa-frontend-ai-drafted-market-note';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await login(c);
let r = await p.goto(BASE + POST, { waitUntil: 'networkidle' });
const banner = await p.evaluate(() => { const e = document.body.firstElementChild; return e.outerHTML.slice(0, 300) + ' TEXT=' + e.innerText; });
console.log('ayu live', r.status(), r.headers()['x-cache-status'] ?? '-', 'h1=', await p.locator('h1').innerText(), '\n  first body el:', banner);
await shot(p, '40-draft-banner', false);
r = await p.goto(BASE + POST + '?draft', { waitUntil: 'networkidle' });
console.log('ayu ?draft', r.status(), 'cache-control=', r.headers()['cache-control'], 'robots=', await p.locator('meta[name=robots]').getAttribute('content').catch(() => null), 'h1=', await p.locator('h1').innerText());
await shot(p, '41-draft-view', false);
// Guest
const g = await (await b.newContext(DESKTOP)).newPage();
r = await g.goto(BASE + POST + '?draft', { waitUntil: 'networkidle' });
console.log('guest ?draft', r.status(), r.headers()['x-cache-status'], 'h1=', await g.locator('h1').innerText(), 'banner?', (await g.locator('body').innerText()).includes('draft'));
// Editor preview link
await p.goto(BASE + '/admin/cms/entries/50/edit', { waitUntil: 'networkidle' });
const [popup] = await Promise.all([c.waitForEvent('page', { timeout: 8000 }).catch(() => null), p.getByRole('button', { name: 'Preview' }).or(p.getByRole('link', { name: 'Preview' })).first().click()]);
await p.waitForTimeout(1500);
if (popup) {
  await popup.waitForLoadState('networkidle');
  const url = popup.url();
  console.log('preview url', url);
  const pr = await g.goto(url, { waitUntil: 'networkidle' });
  console.log('preview as guest', pr.status(), 'cache-control=', pr.headers()['cache-control'], 'x-robots=', pr.headers()['x-robots-tag'], 'robots=', await g.locator('meta[name=robots]').getAttribute('content').catch(() => null), 'h1=', await g.locator('h1').innerText(), 'canonical=', await g.locator('link[rel=canonical]').getAttribute('href').catch(() => null));
  await shot(g, '42-preview-guest', false);
  const u = new URL(url); u.searchParams.set('signature', 'x' + u.searchParams.get('signature')?.slice(1));
  const bad = await g.goto(u.href); console.log('tampered preview', bad.status());
  const exp = new URL(url); if (exp.searchParams.get('expires')) { exp.searchParams.set('expires', String(Number(exp.searchParams.get('expires')) + 86400)); const e2 = await g.goto(exp.href); console.log('extended expiry', e2.status()); }
} else console.log('no preview popup', p.url());
await b.close();
