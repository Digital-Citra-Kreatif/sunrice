// Comments as a guest: first comment (held), HTML/script/link, reply, rate limit; then as signed-in Ayu.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const POST = '/journal/five-things-to-check-before-renewing-a-villa-lease-in-bali';
const b = await launch();
const out = (k, v) => console.log(k.padEnd(26), String(v).replace(/\s+/g, ' ').slice(0, 400));
async function comment(p, url, name, email, body, notify, shotName) {
  await p.goto(BASE + url, { waitUntil: 'networkidle' });
  const f = p.locator('#comment-form');
  if (name) { await f.locator('[name=author_name]').fill(name); await f.locator('[name=author_email]').fill(email); }
  await f.locator('[name=body]').fill(body);
  if (notify) await f.locator('[name=notify_replies]').check();
  await p.waitForTimeout(2200);
  const r = p.waitForResponse((x) => x.url().includes('/comments/'));
  await Promise.all([p.waitForLoadState('networkidle'), f.locator('button[type=submit]').click()]);
  const res = await r; await p.waitForTimeout(500);
  const msg = await p.evaluate(() => [...document.querySelectorAll('#comments [role=status], #comments [role=alert], .sunrice-comments-notice, .sunrice-comment-errors, #comments .notice')].map((e) => e.textContent.trim()).join(' | ') || document.querySelector('#comments')?.innerText.slice(0, 200));
  out(name || 'signed-in', `${res.status()} url=${p.url()} scrollY=${await p.evaluate(() => scrollY)} :: ${msg}`);
  if (shotName) { await p.locator('#comments').scrollIntoViewIfNeeded().catch(() => {}); await shot(p, shotName, false); }
}
const g = await (await b.newContext(DESKTOP)).newPage();
await comment(g, POST, 'QA Guest Frontend', 'qa.guest@example.com', 'QA guest first comment: does the lease renewal advice apply to leasehold land too? (frontend test)', true, '30-comment-guest-held');
await comment(g, POST, 'QA Guest Frontend', 'qa.guest@example.com', 'QA markup test: <b>bold</b> <script>alert("xss")</script> <a href="javascript:alert(1)">click</a> visit https://example.com/page?x=1&y=2 and www.example.org\nsecond line', false, '31-comment-markup');
await comment(g, POST, 'QA Guest Frontend', 'qa.guest@example.com', 'QA rate test 3', false);
await comment(g, POST, 'QA Guest Frontend', 'qa.guest@example.com', 'QA rate test 4', false, '32-comment-rate');
// The page as a guest now: are held comments visible to their author?
await g.goto(BASE + POST + '?listings=' + Date.now(), { waitUntil: 'networkidle' });
out('guest sees', await g.evaluate(() => document.querySelector('#comments')?.innerText.slice(0, 300)));
// Signed in as Ayu
const c = await b.newContext(DESKTOP);
await login(c);
const a = await c.newPage();
const resp = await a.goto(BASE + POST, { waitUntil: 'networkidle' });
out('ayu cache header', `${resp.headers()['x-cache-status'] ?? 'none'} cache-control=${resp.headers()['cache-control']}`);
out('ayu form fields', await a.evaluate(() => [...document.querySelectorAll('#comment-form input:not([type=hidden]), #comment-form textarea')].map((e) => e.name).join(',') + ' :: ' + (document.querySelector('#comment-form')?.innerText.slice(0, 120))));
await comment(a, POST, null, null, 'QA signed-in comment from Ayu (frontend test).', false, '33-comment-signed-in');
await b.close();
