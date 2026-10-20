import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { fillFields, openPublishMenu } from './editor-content.mjs';
const b = await launch(); const { c, p } = await session(b, 'citra');
await shot(p, '43-citra-dashboard');
console.log('nav', await p.$$eval('aside a, nav a', a => a.map(x => x.innerText.trim()).filter(Boolean)));
await p.goto(BASE + '/admin/cms/collections/journal/entries/create', { waitUntil: 'networkidle' });
await p.fill('#title', 'Five things to check before renewing a villa lease in Bali');
await fillFields(p, { excerpt: 'Lease renewals are where many Bali villa owners lose value. Five points to agree in writing before the current term runs out.',
  body: '<p>Many leasehold villas in Bali were built on 25-year leases signed in the early 2000s. Those terms are now ending, and renewal is rarely as simple as a signature.</p><h2>Check these five points</h2><ol><li>The exact end date and any notice period in the original lease</li><li>Whether the extension price was fixed in advance</li><li>Who owns the buildings when the lease ends</li><li>Changes in zoning since the villa was built</li><li>The landowner family\'s current heirs and their consent</li></ol><p>If in doubt, ask a notary to review the original deed before you open negotiations.</p>',
  author_agent: 'Rafael Hartono', topics: ['Buying guides'], related: ['Seminyak Beachside Villa'] }, { body: 'rich', author_agent: 'entry', topics: 'checks', related: 'entries' });
await p.getByRole('button', { name: /Create draft/ }).click();
await p.waitForTimeout(1500); await p.waitForLoadState('networkidle');
console.log('citra created', p.url(), await toasts(p, 300), await errors(p));
await shot(p, '44-citra-post-created');
const top = (await p.locator('main main').innerText()).slice(0, 200).replace(/\n/g, ' | ');
console.log('top', top);
console.log('buttons', await p.locator('main main button').allInnerTexts().then(a => a.filter(Boolean).slice(0, 12)));
// try direct publish endpoint? find publish route by trying the visible UI only
const pub = p.getByRole('button', { name: /Publish|Submit|review/i });
console.log('publish-like buttons', await pub.allInnerTexts());
// other collections
for (const coll of ['properties', 'pages']) {
  const r = await p.goto(BASE + `/admin/cms/collections/${coll}/entries/create`); console.log(coll, 'create status', r.status());
}
await b.close();
