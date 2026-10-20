// Screenshots of each page type, desktop and mobile, after scrolling through the page like a visitor.
// node frontend-02-shots.mjs "<nn>:<slug>:<path>" ... [--mobile]
import { launch, BASE, DESKTOP, MOBILE, shot } from './frontend-lib.mjs';
const args = process.argv.slice(2);
const mobile = args.includes('--mobile');
const b = await launch();
const c = await b.newContext(mobile ? MOBILE : DESKTOP);
for (const a of args.filter((x) => !x.startsWith('--'))) {
  const [nn, slug, path] = a.split('|');
  const p = await c.newPage();
  await p.goto(BASE + path, { waitUntil: 'networkidle' });
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo({ top: y, behavior: 'instant' }); await new Promise((r) => setTimeout(r, 80)); } window.scrollTo({ top: 0, behavior: 'instant' }); });
  await p.waitForTimeout(1200);
  await shot(p, `${nn}-${slug}${mobile ? '-mobile' : ''}`);
  await p.close();
}
await b.close();
