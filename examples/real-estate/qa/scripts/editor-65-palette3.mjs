import { launch, session, shot, BASE } from './editor-lib.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
p.on('request', r => { if (r.url().includes('/admin') && r.resourceType() !== 'document' && !r.url().match(/\.(js|css|svg)/)) console.log('REQ', r.method(), r.url()); });
for (const url of ['/admin', '/admin/cms/entries', '/admin/cms/entries/12/edit', '/admin/media']) {
  await p.goto(BASE + url, { waitUntil: 'networkidle' });
  await p.keyboard.press('Control+k'); await p.waitForTimeout(300); await p.keyboard.type('Menteng', { delay: 50 }); await p.waitForTimeout(2500);
  console.log(url, '->', await p.getByRole('option').allInnerTexts().then(a => a.map(x => x.replace(/\n/g, ' / '))));
  await p.keyboard.press('Escape'); await p.waitForTimeout(200);
}
await b.close();
