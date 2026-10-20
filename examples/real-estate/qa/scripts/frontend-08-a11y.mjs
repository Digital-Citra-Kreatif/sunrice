// Keyboard and focus checks: header/dropdowns, skip link, filters, forms, mobile menu; reduced motion; contrast of key text.
import { launch, BASE, DESKTOP, MOBILE, shot } from './frontend-lib.mjs';
const b = await launch();
const c = await b.newContext(DESKTOP); const p = await c.newPage();
await p.goto(BASE + '/properties', { waitUntil: 'networkidle' });
const focusInfo = () => p.evaluate(() => {
  const e = document.activeElement; const s = getComputedStyle(e);
  const visible = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || (s.boxShadow && s.boxShadow !== 'none');
  const dd = e.closest('.nav-item')?.querySelector('.nav-dropdown'); const ddVis = dd ? getComputedStyle(dd).visibility + '/' + getComputedStyle(dd).opacity : '';
  return `${e.tagName}${e.id ? '#' + e.id : ''} "${(e.innerText || e.value || e.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 30)}" focus=${visible ? 'visible' : 'NONE'}(${s.outlineStyle} ${s.outlineWidth} ${s.outlineColor}) ${ddVis ? 'dropdown=' + ddVis : ''}`;
});
const seq = [];
for (let i = 0; i < 26; i++) { await p.keyboard.press('Tab'); seq.push(await focusInfo()); if (i === 0) await shot(p, '50-skip-link', false); if (i === 2) await shot(p, '51-focus-dropdown', false); }
console.log(seq.map((s, i) => `${i + 1}. ${s}`).join('\n'));
// Gallery: none on this site yet
console.log('gallery triggers on property:', await (async () => { await p.goto(BASE + '/properties/kemang-garden-house'); return p.locator('[data-gallery]').count(); })());
// Filter autosubmit with keyboard: change a select with arrow keys
await p.goto(BASE + '/properties', { waitUntil: 'networkidle' });
await p.focus('#filter-listing_type');
const nav = p.waitForNavigation({ timeout: 3000 }).then(() => true).catch(() => false);
await p.keyboard.press('ArrowDown');
console.log('select ArrowDown navigates (autosubmit)?', await nav, p.url());
await shot(p, '52-filter-keyboard', false);
// Mobile menu
const m = await (await b.newContext(MOBILE)).newPage();
await m.goto(BASE + '/', { waitUntil: 'networkidle' });
await shot(m, '53-mobile-home', false);
await m.focus('[data-nav-toggle]'); await m.keyboard.press('Enter'); await m.waitForTimeout(500);
console.log('mobile menu expanded', await m.getAttribute('[data-nav-toggle]', 'aria-expanded'));
await shot(m, '54-mobile-menu-open', false);
const tabs = [];
for (let i = 0; i < 18; i++) { await m.keyboard.press('Tab'); tabs.push(await m.evaluate(() => { const e = document.activeElement; const r = e.getBoundingClientRect(); return `${e.tagName} "${(e.innerText || '').trim().slice(0, 20)}" inMenu=${!!e.closest('#mobile-nav')} onscreen=${r.top >= 0 && r.top < innerHeight}`; })); }
console.log('mobile tab order:\n' + tabs.join('\n'));
await m.keyboard.press('Escape'); await m.waitForTimeout(300);
console.log('after Esc expanded', await m.getAttribute('[data-nav-toggle]', 'aria-expanded'), 'focus', await m.evaluate(() => document.activeElement.getAttribute('data-nav-toggle') !== null));
// Reduced motion
const rm = await (await b.newContext({ ...DESKTOP, reducedMotion: 'reduce' })).newPage();
await rm.goto(BASE + '/', { waitUntil: 'networkidle' });
console.log('reduced motion: hidden .reveal', await rm.evaluate(() => [...document.querySelectorAll('.reveal')].filter((e) => getComputedStyle(e).opacity === '0').length), 'scroll-behavior', await rm.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior));
// No-JS visitors / crawlers: content visible?
const nj = await (await b.newContext({ ...DESKTOP, javaScriptEnabled: false })).newPage();
await nj.goto(BASE + '/', { waitUntil: 'networkidle' });
console.log('no-JS hidden .reveal', await nj.evaluate(() => [...document.querySelectorAll('.reveal')].filter((e) => getComputedStyle(e).opacity === '0').length));
// Contrast of key text
const contrast = await (async () => {
  await p.goto(BASE + '/properties/kemang-garden-house', { waitUntil: 'networkidle' });
  return p.evaluate(() => {
    const lum = (rgb) => { const [r, g, b] = rgb.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
    const parse = (s) => (s.match(/[\d.]+/g) || []).slice(0, 4).map(Number);
    const bgOf = (e) => { while (e) { const c = parse(getComputedStyle(e).backgroundColor); if (c.length >= 3 && (c[3] === undefined || c[3] > 0.5)) return c.slice(0, 3); e = e.parentElement; } return [255, 255, 255]; };
    const seen = new Map();
    for (const e of document.querySelectorAll('body *')) {
      if (!e.childNodes.length || ![...e.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim())) continue;
      const s = getComputedStyle(e); if (s.visibility === 'hidden' || s.display === 'none') continue;
      const fg = parse(s.color).slice(0, 3); const bg = bgOf(e);
      const a = lum(fg), bb = lum(bg); const ratio = (Math.max(a, bb) + 0.05) / (Math.min(a, bb) + 0.05);
      const size = parseFloat(s.fontSize); const large = size >= 24 || (size >= 18.66 && Number(s.fontWeight) >= 700);
      const key = `${s.color} on rgb(${bg.join(',')}) ${size}px`;
      if (ratio < (large ? 3 : 4.5) && !seen.has(key)) seen.set(key, `${ratio.toFixed(2)} "${e.textContent.trim().slice(0, 40)}"`);
    }
    return [...seen.entries()].map(([k, v]) => `${v} :: ${k}`);
  });
})();
console.log('low contrast (property page):\n' + contrast.join('\n'));
await b.close();
