import { launch, session, shot, BASE, toasts, errors } from './editor-lib.mjs';
import { createEntry, fillFields } from './editor-content.mjs';
import { PROPS, PROP_TYPES } from './editor-props-data.mjs';
const b = await launch(); const { c, p } = await session(b, 'budi');
const only = process.argv[2];
// First property: type description by keyboard to test the rich text editor
const first = PROPS[0];
if (!only || only === 'first') {
  await p.goto(BASE + '/admin/cms/collections/properties/entries/create', { waitUntil: 'networkidle' });
  await p.fill('#title', first.title);
  const f = { ...first.f }; delete f.description;
  await fillFields(p, f, PROP_TYPES);
  const ed = p.locator('#data-description');
  await ed.click();
  await p.keyboard.type('Designed by a Bali-based studio and completed in 2022, Villa Arunika pairs polished concrete, teak and natural stone with ');
  await p.keyboard.press('Control+b'); await p.keyboard.type('big sliding doors'); await p.keyboard.press('Control+b');
  await p.keyboard.type(' that open the living pavilion completely to the garden.');
  await p.keyboard.press('Enter');
  await p.keyboard.type('## The villa'); await p.keyboard.press('Enter');
  await p.keyboard.type('- Open living and dining pavilion with a chef\'s kitchen'); await p.keyboard.press('Enter');
  await p.keyboard.type('Four en-suite bedrooms, the master with a garden bathroom'); await p.keyboard.press('Enter');
  await p.keyboard.type('15 m saltwater pool and sunken lounge'); await p.keyboard.press('Enter');
  await p.keyboard.type('Separate staff quarters and storage'); await p.keyboard.press('Enter'); await p.keyboard.press('Enter');
  await p.keyboard.type('Location');
  // make it H3 via toolbar
  const h3 = p.locator('button[aria-label*="eading 3"], button[title*="eading 3"]');
  console.log('h3 buttons', await h3.count(), await p.locator('#data-description').locator('xpath=ancestor::div[1]/..').locator('button').evaluateAll(bs => bs.map(b => (b.getAttribute('aria-label') || b.title || b.innerText))));
  if (await h3.count()) await h3.first().click();
  await p.keyboard.press('End'); await p.keyboard.press('Enter');
  await p.keyboard.type('Pererenan is the quieter side of Canggu: five minutes to the beach, ten to Batu Bolong.');
  // link via toolbar
  await shot(p, '17-property-description-typed');
  console.log('HTML', await ed.innerHTML());
  await p.getByRole('button', { name: 'Create draft' }).click();
  await p.waitForSelector('text=Save draft'); await p.waitForLoadState('networkidle');
  console.log(await toasts(p), await errors(p), p.url());
  await p.getByRole('button', { name: 'Publish', exact: true }).click(); console.log(await toasts(p));
  await shot(p, '18-property-first-saved', true);
}
if (!only || only === 'rest') {
  for (const pr of PROPS.slice(1)) {
    await createEntry(p, BASE, 'properties', pr.title, pr.f, PROP_TYPES, { publish: !pr.draft });
  }
  await p.goto(BASE + '/admin/cms/collections/properties/entries', { waitUntil: 'networkidle' });
  await shot(p, '19-properties-list', true);
}
await b.close();
