// Admin round trip as Ayu: comments list, approve the QA guest comment, reply to it; submissions list.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await login(c);
await p.goto(BASE + '/admin/cms/comments', { waitUntil: 'networkidle' });
await shot(p, '34-admin-comments', false);
const txt = await p.locator('main').last().innerText();
console.log(txt.slice(0, 1500).replace(/\n+/g, ' | '));
console.log('buttons:', (await p.locator('main button, main a').evaluateAll((bs) => bs.map((x) => (x.getAttribute('aria-label') || x.innerText.trim()).slice(0, 40)))).filter(Boolean).join(' ; ').slice(0, 1500));
await b.close();
