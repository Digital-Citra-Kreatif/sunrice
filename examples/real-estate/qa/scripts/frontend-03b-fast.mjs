// Honeypot timing: a submit faster than the honeypot's minimum time.
import { launch, BASE, DESKTOP, shot } from './frontend-lib.mjs';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await c.newPage();
await p.goto(BASE + '/contact', { waitUntil: 'networkidle' });
await p.fill('#form-contact [name="data[name]"]', 'QA Too Fast');
await p.fill('#form-contact [name="data[email]"]', 'qa.fast@example.com');
await p.fill('#form-contact [name="data[message]"]', 'QA too fast test');
const r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
await p.click('#form-contact button[type=submit]'); const res = await r; await p.waitForLoadState('networkidle');
console.log('instant submit', res.status(), p.url(), JSON.stringify((await p.locator('body').innerText()).slice(0, 200)));
await shot(p, '22-honeypot-fast', false);
await b.close();
