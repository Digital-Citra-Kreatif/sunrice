import { launch, shot, BASE, logErrors } from './editor-lib.mjs';
const b = await launch(); const c = await b.newContext({ viewport: { width: 1440, height: 900 } }); const p = await c.newPage(); logErrors(p);
async function comment(name, email, body, shotName) {
  await p.goto(BASE + '/journal/living-in-ubud-a-year-in-the-hills', { waitUntil: 'networkidle' });
  const f = p.locator('#comment-form');
  await f.locator('[name=author_name]').fill(name); await f.locator('[name=author_email]').fill(email); await f.locator('[name=body]').fill(body);
  await p.waitForTimeout(2500);
  await f.locator('button[type=submit]').click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(800);
  const t = await p.locator('body').innerText();
  console.log(name, '->', (t.match(/[^\n]*(thank|moderat|approv|review|too many|error)[^\n]*/i) || [''])[0].slice(0, 200));
  if (shotName) await shot(p, shotName, false);
}
await comment('Sophie Laurent', 'sophie.laurent@example.com', 'Lovely piece, Maarten. We visited a plot near Sayan in January and the rain advice is spot on. How long did your building permit take?', '136-visitor-comment');
await comment('Cheap Villas Now', 'promo@spam.example', 'Best villa deals here!!! visit www.cheap-villas-now.example for 90% off');
await p.goto(BASE + '/contact', { waitUntil: 'networkidle' });
const cf = p.locator('#form-contact');
await cf.locator('[name="data[name]"]').fill('Hendra Gunawan'); await cf.locator('[name="data[email]"]').fill('hendra.gunawan@example.co.id');
await cf.locator('[name="data[phone]"]').fill('+62 812 9988 7766'); await cf.locator('[name="data[topic]"]').selectOption({ index: 3 });
await cf.locator('[name="data[message]"]').fill('Good afternoon, we are looking for a four-bedroom house with a garden in Kemang or Cipete for a two-year lease from January. Budget around IDR 70 million per month. Could Sekar contact me?');
await p.waitForTimeout(2500);
await cf.locator('button[type=submit]').click(); await p.waitForLoadState('networkidle'); await p.waitForTimeout(800);
console.log('after contact', ((await p.locator('body').innerText()).match(/[^\n]*(thank|received)[^\n]*/i) || [''])[0]);
await shot(p, '137-visitor-contact');
await b.close();
