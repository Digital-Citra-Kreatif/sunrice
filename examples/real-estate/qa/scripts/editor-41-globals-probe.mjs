import { launch, session, shot, BASE, dumpForm } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
await p.goto(BASE + '/admin/cms/globals', { waitUntil: 'networkidle' });
console.log(p.url(), (await p.locator('main main').innerText()).slice(0, 500));
const links = await p.$$eval('main a', as => as.map(a => a.innerText.trim() + ' ' + a.getAttribute('href')));
console.log(links);
await shot(p, '72-globals', true);
for (const g of ['company', 'social', 'footer']) {
  const href = links.find(l => l.toLowerCase().includes(g));
}
await p.goto(BASE + '/admin/cms/globals/company', { waitUntil: 'networkidle' });
console.log(p.url(), (await dumpForm(p)).join('\n'));
await p.goto(BASE + '/admin/cms/globals/social', { waitUntil: 'networkidle' });
console.log(p.url(), (await p.locator('main main').innerText()).slice(0, 300));
await b.close();
