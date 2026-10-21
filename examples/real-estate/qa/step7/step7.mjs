import { chromium } from 'playwright-core';
const B = 'http://127.0.0.1:8000', OUT = new URL('.', import.meta.url).pathname;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
p.on('pageerror', e => console.log('ERR', e.message));
const shot = async (name, loc) => { if (loc) { await loc.scrollIntoViewIfNeeded(); } await p.waitForTimeout(300); await p.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 72, fullPage: !loc }); console.log('shot', name); };
await p.goto(B + '/_workbench/login/admin@example.com');
await p.goto(B + '/admin/cms/collections/blog/edit', { waitUntil: 'networkidle' });
await p.addStyleTag({ content: 'header { position: static !important; }' });
const entryId = p.locator('#field-route_id');
console.log('placeholder before', await entryId.getAttribute('placeholder'));
await shot('collection-urls-empty');
// The placeholder follows the main URL as it's typed.
await p.locator('#field-route').fill('/articles/{slug}');
console.log('placeholder after typing', await entryId.getAttribute('placeholder'));
await p.locator('#field-route').fill('');
console.log('placeholder emptied', await entryId.getAttribute('placeholder'));
await p.locator('#field-route').fill('/blog/{slug}');
await entryId.fill('/artikel/{slug}');
await p.locator('#field-listing_route_id').fill('/artikel');
await p.getByRole('button', { name: /^Save/ }).first().click();
await p.waitForLoadState('networkidle'); await p.waitForTimeout(800);
await p.addStyleTag({ content: 'header { position: static !important; }' });
await shot('collection-urls-saved');
// A pattern another collection has in Indonesian.
await entryId.fill('/{slug}');
await p.getByRole('button', { name: /^Save/ }).first().click();
await p.waitForTimeout(1200);
await shot('collection-url-conflict', p.locator('#field-route_id'));
await p.goto(B + '/admin/cms/taxonomies/topics/edit', { waitUntil: 'networkidle' });
await p.addStyleTag({ content: 'header { position: static !important; }' });
await p.locator('#field-route_id').fill('/topik/{slug}');
await p.getByRole('button', { name: /^Save/ }).first().click();
await p.waitForLoadState('networkidle'); await p.waitForTimeout(800);
await p.addStyleTag({ content: 'header { position: static !important; }' });
await shot('taxonomy-url-saved', p.locator('#field-route_id'));
// The site.
for (const [name, path] of [['site-id-listing', '/id/artikel'], ['site-old-address', '/id/blog']]) {
  const r = await p.goto(B + path, { waitUntil: 'networkidle' });
  console.log(path, '->', p.url(), r.status());
  await shot(name);
}
// Wildcard redirects.
await p.goto(B + '/admin/cms/redirects/create', { waitUntil: 'networkidle' });
await p.locator('#field-from_path').fill('/news/*');
await p.locator('#field-to_url').fill('/blog/*/x/*');
await p.getByRole('button', { name: /^(Create|Save)/ }).first().click();
await p.waitForTimeout(1000);
await shot('redirect-wildcard-error');
await p.locator('#field-to_url').fill('/blog/*');
await p.getByRole('button', { name: /^(Create|Save)/ }).first().click();
await p.waitForLoadState('networkidle'); await p.waitForTimeout(800);
await p.goto(B + '/admin/cms/redirects', { waitUntil: 'networkidle' });
await shot('redirects-list');
await p.goto(B + '/news/hello-world', { waitUntil: 'networkidle' });
console.log('/news/hello-world ->', p.url());
await p.goto(B + '/admin/language', { waitUntil: 'networkidle' }).catch(() => {});
await browser.close();
