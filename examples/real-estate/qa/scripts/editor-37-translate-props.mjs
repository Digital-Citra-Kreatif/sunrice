import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { fillRich, openTranslation } from './editor-content.mjs';
import { PROP_ID } from './editor-translations-data.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
for (const [id, t] of Object.entries(PROP_ID)) {
  if (id === '10') continue;
  await openTranslation(p, BASE, id);
  await p.fill('#title', t.title); await p.fill('#slug', t.slug);
  await p.fill('#data-summary', t.summary); await p.fill('#data-address', t.address);
  await fillRich(p, '#data-description', t.description);
  for (const [i, l] of t.features.entries()) await p.fill(`#data-features-${i}-label`, l);
  await p.getByRole('button', { name: 'Publish', exact: true }).click();
  console.log(id, t.title, await toasts(p, 1200), await errors(p), p.url());
}
await shot(p, '68-translation-published', true);
await b.close();
