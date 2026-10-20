// Forms as a visitor: contact (invalid, valid, honeypot, rate limit), inquiry, valuation uploads, newsletter; EN and ID.
import { launch, BASE, DESKTOP, shot } from './frontend-lib.mjs';
const UP = '/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/fe-up/';
const b = await launch();
const ctx = () => b.newContext(DESKTOP);
const out = (k, v) => console.log(k.padEnd(28), typeof v === 'string' ? v.replace(/\s+/g, ' ').slice(0, 300) : JSON.stringify(v));
async function msgs(p, sel) {
  return p.evaluate((sel) => {
    const f = document.querySelector(sel);
    const scope = f?.closest('section,div.card,aside,main') || document.body;
    const t = [...document.querySelectorAll('[role=alert],[role=status],.alert,.form-success,.form-error,.error,.field-error,[id$="-error"],.notice,.flash, .text-red-700, .text-red-600')].map((e) => e.textContent.trim()).filter(Boolean);
    return [...new Set(t)].join(' | ').slice(0, 600);
  }, sel);
}
async function submit(p, sel) {
  await Promise.all([p.waitForLoadState('networkidle'), p.locator(sel + ' button[type=submit]').click()]);
  await p.waitForTimeout(600);
}
let p, c, r;
// 1. Contact invalid (server-side: drop the browser's required checks)
c = await ctx(); p = await c.newPage();
for (const [lang, path] of [['en', '/contact'], ['id', '/id/kontak']]) {
  await p.goto(BASE + path, { waitUntil: 'networkidle' });
  await p.evaluate(() => { document.querySelector('#form-contact').noValidate = true; });
  await p.fill('#form-contact [name="data[email]"]', 'not-an-email');
  await p.waitForTimeout(2200);
  r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
  await submit(p, '#form-contact'); r = await r;
  out(`contact invalid ${lang}`, `${r.status()} url=${p.url()} :: ${await msgs(p, '#form-contact')}`);
  out(`  focus after error ${lang}`, await p.evaluate(() => document.activeElement?.name || document.activeElement?.tagName));
  out(`  aria-invalid ${lang}`, await p.evaluate(() => [...document.querySelectorAll('#form-contact [aria-invalid=true]')].map((e) => e.name + '>' + e.getAttribute('aria-describedby'))));
  await shot(p, `20-contact-invalid-${lang}`, false);
  await p.locator('#form-contact').scrollIntoViewIfNeeded(); await shot(p, `20-contact-invalid-${lang}-form`, false);
}
// 2. Contact valid (EN and ID)
for (const [lang, path, name] of [['en', '/contact', 'QA Frontend Tester'], ['id', '/id/kontak', 'QA Penguji Frontend']]) {
  await p.goto(BASE + path, { waitUntil: 'networkidle' });
  await p.fill('#form-contact [name="data[name]"]', name);
  await p.fill('#form-contact [name="data[email]"]', 'qa.frontend@example.com');
  await p.fill('#form-contact [name="data[phone]"]', '+62 811 0000 0001');
  await p.selectOption('#form-contact [name="data[topic]"]', { index: 1 });
  await p.fill('#form-contact [name="data[message]"]', `QA frontend test (${lang}): <b>bold?</b> <script>alert(1)</script> https://example.com — please ignore.`);
  await p.waitForTimeout(2200);
  r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
  await submit(p, '#form-contact'); r = await r;
  out(`contact valid ${lang}`, `${r.status()} url=${p.url()} :: ${await msgs(p, '#form-contact')}`);
  out(`  form still shown ${lang}`, String(await p.locator('#form-contact').count()));
  await shot(p, `21-contact-thanks-${lang}`, false);
}
await c.close();
// 3. Honeypot filled, and too-fast submit
c = await ctx(); p = await c.newPage();
await p.goto(BASE + '/contact', { waitUntil: 'networkidle' });
await p.evaluate(() => { const h = document.querySelector('#form-contact [name^=my_name_]'); h.value = 'I am a bot'; });
await p.fill('#form-contact [name="data[name]"]', 'QA Honeypot Bot');
await p.fill('#form-contact [name="data[email]"]', 'qa.bot@example.com');
await p.fill('#form-contact [name="data[message]"]', 'QA honeypot test');
await p.waitForTimeout(2200);
r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
await submit(p, '#form-contact'); r = await r;
out('honeypot filled', `${r.status()} url=${p.url()} :: ${await msgs(p, '#form-contact')} :: body=${(await p.locator('body').innerText()).slice(0, 120)}`);
await p.goto(BASE + '/contact', { waitUntil: 'networkidle' });
await p.fill('#form-contact [name="data[name]"]', 'QA Too Fast');
await p.fill('#form-contact [name="data[email]"]', 'qa.fast@example.com');
await p.fill('#form-contact [name="data[message]"]', 'QA too fast test');
r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
await submit(p, '#form-contact'); r = await r;
out('submitted instantly', `${r.status()} url=${p.url()} :: ${await msgs(p, '#form-contact')} :: ${(await p.locator('body').innerText()).slice(0, 100)}`);
await shot(p, '22-honeypot-fast', false);
await c.close();
// 4. Rate limit: 7 quick valid submissions
c = await ctx(); p = await c.newPage();
const codes = [];
for (let i = 1; i <= 7; i++) {
  await p.goto(BASE + '/contact', { waitUntil: 'networkidle' });
  await p.fill('#form-contact [name="data[name]"]', `QA Rate ${i}`);
  await p.fill('#form-contact [name="data[email]"]', 'qa.rate@example.com');
  await p.fill('#form-contact [name="data[message]"]', `QA rate-limit test ${i}`);
  await p.waitForTimeout(2100);
  r = p.waitForResponse((x) => x.url().includes('/forms/contact'));
  await submit(p, '#form-contact'); r = await r;
  codes.push(r.status());
  if (r.status() === 429) { out('rate limited page', (await p.locator('body').innerText()).slice(0, 200)); await shot(p, '23-rate-limit', false); break; }
}
out('rate codes', codes);
await c.close();
// 5. Inquiry (property prefilled), EN and ID
c = await ctx(); p = await c.newPage();
for (const [lang, path] of [['en', '/properties/kemang-garden-house'], ['id', '/id/properties/rumah-taman-kemang']]) {
  await p.goto(BASE + path, { waitUntil: 'networkidle' });
  const f = '#form-inquiry';
  out(`inquiry fields ${lang}`, await p.evaluate((f) => [...document.querySelectorAll(f + ' input:not([type=hidden]),' + f + ' textarea,' + f + ' input[type=hidden][name^=data]')].map((e) => `${e.name}=${e.value}`).join(' ; '), f));
  await p.fill(f + ' [name="data[name]"]', 'QA Frontend Inquiry');
  await p.fill(f + ' [name="data[email]"]', 'qa.frontend@example.com');
  await p.fill(f + ' [name="data[preferred_date]"]', '2026-11-05').catch(() => {});
  await p.waitForTimeout(2200);
  r = p.waitForResponse((x) => x.url().includes('/forms/inquiry'));
  await submit(p, f); r = await r;
  out(`inquiry ${lang}`, `${r.status()} url=${p.url()} :: ${await msgs(p, f)}`);
  await shot(p, `24-inquiry-thanks-${lang}`, false);
}
// past date
await p.goto(BASE + '/properties/kemang-garden-house', { waitUntil: 'networkidle' });
await p.fill('#form-inquiry [name="data[name]"]', 'QA Past Date');
await p.fill('#form-inquiry [name="data[email]"]', 'qa.frontend@example.com');
await p.fill('#form-inquiry [name="data[preferred_date]"]', '2020-01-01').catch(() => {});
await p.waitForTimeout(2200);
r = p.waitForResponse((x) => x.url().includes('/forms/inquiry'));
await submit(p, '#form-inquiry'); r = await r;
out('inquiry past date', `${r.status()} :: ${await msgs(p, '#form-inquiry')}`);
await c.close();
// 6. Valuation uploads (new context per try: 3/min limit)
for (const [label, file] of [['valid jpg', 'ok.jpg'], ['3.4MB jpg (under 5MB rule)', 'mid3.jpg'], ['8MB jpg (over)', 'big6.jpg'], ['php renamed jpg', 'shell.jpg'], ['real php', 'shell.php'], ['polyglot jpg', 'polyglot.jpg']]) {
  c = await ctx(); p = await c.newPage();
  await p.goto(BASE + '/sell-with-us', { waitUntil: 'networkidle' });
  const f = '#form-valuation';
  await p.fill(f + ' [name="data[name]"]', 'QA Valuation ' + label);
  await p.fill(f + ' [name="data[email]"]', 'qa.frontend@example.com');
  await p.fill(f + ' [name="data[address]"]', 'Jl. QA Test No. 1, Kemang');
  await p.locator(f + ' [name="data[photos]"]').setInputFiles(UP + file);
  await p.waitForTimeout(2200);
  r = p.waitForResponse((x) => x.url().includes('/forms/valuation'), { timeout: 20000 }).catch(() => null);
  await submit(p, f); r = await r;
  out(`valuation ${label}`, `${r?.status()} url=${p.url()} :: ${await msgs(p, f)} :: ${r && r.status() >= 400 ? (await p.locator('body').innerText()).slice(0, 150) : ''}`);
  await shot(p, `25-valuation-${file.replace('.', '-')}`, false);
  await c.close();
  await new Promise((res) => setTimeout(res, 21000));
}
// 7. Newsletter EN invalid + valid, ID valid
c = await ctx(); p = await c.newPage();
for (const [lang, path, email] of [['en', '/', 'bad@'], ['en', '/', 'qa.frontend+news@example.com'], ['id', '/id', 'qa.frontend+berita@example.com'], ['id', '/id', 'salah@']]) {
  await p.goto(BASE + path, { waitUntil: 'networkidle' });
  await p.evaluate(() => { document.querySelector('#form-newsletter').noValidate = true; });
  await p.fill('#form-newsletter [name="data[email]"]', email);
  await p.waitForTimeout(2200);
  r = p.waitForResponse((x) => x.url().includes('/forms/newsletter'));
  await submit(p, '#form-newsletter'); r = await r;
  out(`newsletter ${lang} ${email}`, `${r.status()} url=${p.url()} scrollY=${await p.evaluate(() => scrollY)} :: ${await msgs(p, '#form-newsletter')}`);
  await p.locator('#form-newsletter').scrollIntoViewIfNeeded(); await shot(p, `26-newsletter-${lang}-${email.includes('@example') ? 'ok' : 'bad'}`, false);
}
await c.close();
await b.close();
