import { launch, session, shot, BASE, toasts, pick, dumpForm } from './editor-lib.mjs';
const b = await launch();
const { c, p } = await session(b, 'ayu');
await p.goto(BASE + '/admin/settings/site', { waitUntil: 'networkidle' });
console.log((await dumpForm(p)).join('\n'));
const hp = p.locator('label', { hasText: /Homepage|Home page/i });
console.log('hp label', await hp.allInnerTexts());
await shot(p, '64-settings-site', true);
await b.close();
