// usage: node admin-explore.mjs <path> <shotname> [full]
import { launch, ctx, shot, BASE, logErrors } from './admin-lib.mjs';
const [,, path, name, full] = process.argv;
const b = await launch(); const c = await ctx(b); const p = await c.newPage(); logErrors(p);
const t0 = Date.now();
await p.goto(BASE + path, { waitUntil: 'networkidle' });
console.log('url', p.url(), 'ms', Date.now() - t0);
if (name) await shot(p, name, full === 'full');
console.log((await p.locator('main').first().innerText().catch(() => p.locator('body').innerText())).slice(0, 6000));
const fields = await p.$$eval('input,select,textarea,button,[role=switch],[role=combobox],[role=tab]', els => els.map(e => `${e.tagName.toLowerCase()}[${e.getAttribute('type')||e.getAttribute('role')||''}] name=${e.getAttribute('name')||''} id=${e.id||''} aria=${e.getAttribute('aria-label')||''} text=${(e.innerText||e.value||'').trim().slice(0,40)}`));
console.log('--- fields\n' + fields.join('\n'));
await b.close();
