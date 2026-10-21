// Crawl a list of URLs as a guest: meta, JSON-LD, headings, landmarks, a11y basics, timings.
// node frontend-01-crawl.mjs urls.txt out.json [mobile]
import fs from 'node:fs';
import { launch, BASE, DESKTOP, MOBILE } from './frontend-lib.mjs';
const [list, out, mode] = process.argv.slice(2);
const urls = fs.readFileSync(list, 'utf8').split('\n').map((s) => s.trim()).filter((s) => s !== '#' && !s.startsWith('#')).map((s) => s === '' ? '/' : s);
const b = await launch();
const results = [];
for (const u of [...new Set(urls)]) {
  const c = await b.newContext(mode === 'mobile' ? MOBILE : DESKTOP);
  const p = await c.newPage();
  const errs = []; let reqs = 0; let bytes = 0; const types = {};
  p.on('console', (m) => m.type() === 'error' && errs.push('console: ' + m.text().slice(0, 200)));
  p.on('pageerror', (e) => errs.push('pageerror: ' + e.message));
  p.on('response', async (r) => { reqs++; if (r.status() >= 400) errs.push(r.status() + ' ' + r.url());
    try { const len = Number((await r.headerValue('content-length')) || (await r.body()).length); bytes += len; const t = (r.headers()['content-type'] || '').split(';')[0]; types[t] = (types[t] || 0) + len; } catch {} });
  await p.addInitScript(() => { window.__cls = 0; new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
  let resp;
  try { resp = await p.goto(BASE + u, { waitUntil: 'networkidle', timeout: 30000 }); } catch (e) { results.push({ u, error: e.message }); await c.close(); continue; }
  await p.waitForTimeout(300);
  const info = await p.evaluate(() => {
    const q = (s) => document.querySelector(s);
    const m = (n) => q(`meta[name="${n}"]`)?.content ?? q(`meta[property="${n}"]`)?.content ?? null;
    const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => { try { return JSON.parse(s.textContent); } catch (e) { return { PARSE_ERROR: e.message, raw: s.textContent.slice(0, 200) }; } });
    const nav = performance.getEntriesByType('navigation')[0];
    const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map((h) => h.tagName + ':' + h.textContent.trim().replace(/\s+/g, ' ').slice(0, 50));
    const imgsNoAlt = [...document.querySelectorAll('img')].filter((i) => !i.hasAttribute('alt')).map((i) => i.src);
    const linksNoText = [...document.querySelectorAll('a,button')].filter((a) => !(a.textContent.trim() || a.getAttribute('aria-label') || a.getAttribute('title') || a.querySelector('img[alt]:not([alt=""])'))).map((a) => a.outerHTML.slice(0, 120));
    const ids = {}; document.querySelectorAll('[id]').forEach((e) => ids[e.id] = (ids[e.id] || 0) + 1);
    const inputsNoLabel = [...document.querySelectorAll('input:not([type=hidden]):not([type=submit]),select,textarea')].filter((i) => !(i.labels?.length || i.getAttribute('aria-label') || i.getAttribute('aria-labelledby'))).filter(i => i.offsetParent !== null).map((i) => i.name);
    return {
      title: document.title, lang: document.documentElement.lang, desc: m('description'), robots: m('robots'),
      canonical: q('link[rel=canonical]')?.href ?? null,
      hreflang: [...document.querySelectorAll('link[rel=alternate][hreflang]')].map((l) => l.hreflang + '=' + l.href.replace(location.origin, '')),
      og: { title: m('og:title'), type: m('og:type'), url: m('og:url'), image: m('og:image'), locale: m('og:locale'), site: m('og:site_name'), desc: m('og:description') },
      tw: { card: m('twitter:card'), title: m('twitter:title') },
      favicon: [...document.querySelectorAll('link[rel~=icon],link[rel=apple-touch-icon]')].map((l) => l.rel + ':' + l.getAttribute('href')),
      ldTypes: ld.map((x) => x.PARSE_ERROR ? 'PARSE_ERROR' : (x['@graph'] ? x['@graph'].map((g) => g['@type']).join('+') : x['@type'])),
      ld,
      h1: [...document.querySelectorAll('h1')].length, heads,
      landmarks: { header: !!q('header'), nav: document.querySelectorAll('nav').length, main: document.querySelectorAll('main').length, footer: !!q('footer'), skip: !!q('a[href="#main"],a[href="#content"],a.skip-link') },
      imgsNoAlt, linksNoText: linksNoText.slice(0, 5), dupIds: Object.entries(ids).filter(([, v]) => v > 1).map(([k]) => k), inputsNoLabel,
      ttfb: Math.round(nav.responseStart - nav.requestStart), dcl: Math.round(nav.domContentLoadedEventEnd), load: Math.round(nav.loadEventEnd),
      htmlBytes: nav.transferSize || nav.encodedBodySize, cls: Number(window.__cls.toFixed(3)),
      overflow: document.documentElement.scrollWidth - window.innerWidth,
      blockingCss: [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.getAttribute('href')),
      headScripts: [...document.head.querySelectorAll('script[src]')].map((s) => s.getAttribute('src') + (s.defer ? ' defer' : s.type === 'module' ? ' module' : s.async ? ' async' : ' BLOCKING')),
      draftBanner: !!document.body.innerText.match(/draft|preview/i) && document.body.innerText.match(/[^\n]*(draft|preview)[^\n]*/i)[0].slice(0, 80),
    };
  });
  results.push({ u, status: resp.status(), cache: resp.headers()['x-cache-status'] ?? null, reqs, bytes, types, errs, ...info });
  process.stderr.write(`${resp.status()} ${u} ${info.ttfb}ms ${reqs}req ${Math.round(bytes / 1024)}KB ${info.title}\n`);
  await c.close();
}
fs.writeFileSync(out, JSON.stringify(results, null, 1));
await b.close();
