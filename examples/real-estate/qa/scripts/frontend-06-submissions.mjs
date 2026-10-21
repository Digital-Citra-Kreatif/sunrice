// The QA submissions in the admin (as Ayu): list, a valuation with a file.
import { launch, BASE, DESKTOP, shot, login } from './frontend-lib.mjs';
const b = await launch(); const c = await b.newContext(DESKTOP); const p = await login(c);
await p.goto(BASE + '/admin', { waitUntil: 'networkidle' });
const links = await p.locator('a').evaluateAll((as) => as.map((a) => a.getAttribute('href')).filter((h) => h && /submission|forms/.test(h)));
console.log([...new Set(links)].join(' '));
const target = links.find((h) => /submissions/.test(h)) || '/admin/cms/forms';
await p.goto(new URL(target, BASE).href, { waitUntil: 'networkidle' });
await shot(p, '38-admin-submissions', false);
console.log((await p.locator('main').last().innerText()).slice(0, 1500).replace(/\n+/g, ' | '));
await b.close();
