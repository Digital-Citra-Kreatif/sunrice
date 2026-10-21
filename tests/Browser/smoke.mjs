#!/usr/bin/env node
/*
 * Browser smoke test: visits every admin page of the workbench app in a real
 * Chromium, at desktop and phone sizes, in light and dark mode, and fails on
 * JavaScript errors, console errors, failed requests and pages wider than the
 * screen. Not part of `composer test`; run it by hand after UI work.
 *
 *   composer serve                       # or: vendor/bin/testbench workbench:build && vendor/bin/testbench serve
 *   mkdir -p /tmp/pw && (cd /tmp/pw && npm init -y && npm i playwright-core)
 *   CHROMIUM_PATH=/path/to/chrome PLAYWRIGHT_DIR=/tmp/pw node tests/Browser/smoke.mjs
 *
 * playwright-core is deliberately not a dependency of the repository: install
 * it anywhere and point PLAYWRIGHT_DIR at that folder (or run from it). Any
 * Chromium or Chrome works; `npx playwright install chromium` in that folder
 * downloads one if you have none.
 *
 * Environment:
 *   SUNRICE_URL        the app (default http://127.0.0.1:8000)
 *   CHROMIUM_PATH      the browser executable (default: playwright's own download)
 *   PLAYWRIGHT_DIR     where playwright-core is installed (default: the current directory)
 *   SMOKE_SCREENSHOTS  a folder to save a screenshot of every page in (default: none)
 *   SMOKE_VIEWPORTS    desktop,mobile (default both)
 *   SMOKE_SCHEMES      light,dark (default both)
 *   SMOKE_ONLY         only pages whose name contains this text
 *   SMOKE_EMAIL / SMOKE_PASSWORD   for the sign-in form (default admin@example.com / password)
 *   SMOKE_ALLOW_HOSTS  extra hosts whose failed requests are ignored, comma separated
 *                      (fonts.bunny.net is always ignored: offline machines can't reach it)
 *
 * The workbench needs its demo content (the workbench seeder adds it) and at
 * least one image in the media library for the media details page.
 */
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

const require = createRequire(import.meta.url);
let chromium;
try {
    ({ chromium } = require(require.resolve('playwright-core', { paths: [process.env.PLAYWRIGHT_DIR ?? process.cwd(), process.cwd()] })));
} catch {
    console.error('playwright-core not found. Install it outside the repository and set PLAYWRIGHT_DIR:\n  mkdir -p /tmp/pw && (cd /tmp/pw && npm init -y && npm i playwright-core)\n  PLAYWRIGHT_DIR=/tmp/pw CHROMIUM_PATH=... node tests/Browser/smoke.mjs');
    process.exit(2);
}

const base = (process.env.SUNRICE_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, '');
const shots = process.env.SMOKE_SCREENSHOTS;
const viewports = { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } };
const wantedViewports = (process.env.SMOKE_VIEWPORTS ?? 'desktop,mobile').split(',').filter((name) => name in viewports);
const schemes = (process.env.SMOKE_SCHEMES ?? 'light,dark').split(',');
const only = process.env.SMOKE_ONLY;
const ignoredHosts = ['fonts.bunny.net', ...(process.env.SMOKE_ALLOW_HOSTS ?? '').split(',').filter(Boolean)];
if (shots) mkdirSync(shots, { recursive: true });

/** Find the first link on a page that matches, for records whose ids differ between installs. */
const firstLink = (from, pattern) => async (page) => {
    await visit(page, from);
    const hrefs = await page.$$eval('a[href]', (links) => links.map((link) => link.getAttribute('href')));
    const href = hrefs.find((candidate) => candidate && pattern.test(candidate));
    if (!href) throw new Error(`no link matching ${pattern} on ${from}`);

    return href;
};

const clickFirst = (selector) => async (page) => {
    const target = page.locator(selector).first();
    await target.waitFor({ state: 'visible', timeout: 5000 });
    await target.click();
    await page.waitForTimeout(450);
};

/** Every page, signed in. `path` is fixed or found; `then` opens something on it. */
const pages = [
    { name: 'dashboard', path: '/admin' },
    { name: 'entries-home', path: '/admin/cms/entries' },
    { name: 'entries-list', path: '/admin/cms/collections/blog/entries' },
    { name: 'entry-editor', path: firstLink('/admin/cms/collections/blog/entries', /\/cms\/entries\/\d+\/edit$/) },
    { name: 'entry-editor-secondary', path: async (page) => `${await firstLink('/admin/cms/collections/blog/entries', /\/cms\/entries\/\d+\/edit$/)(page)}?locale=id` },
    { name: 'entry-create', path: '/admin/cms/collections/pages/entries/create' },
    { name: 'listing-page', path: '/admin/cms/collections/blog/listing/edit' },
    { name: 'terms-home', path: '/admin/cms/terms' },
    { name: 'terms-list', path: '/admin/cms/taxonomies/topics/terms' },
    { name: 'term-editor', path: firstLink('/admin/cms/taxonomies/topics/terms', /\/cms\/terms\/\d+\/edit(\?|$)/) },
    { name: 'globals-home', path: '/admin/cms/globals' },
    { name: 'globals', path: '/admin/cms/globals/site/values' },
    { name: 'menus-home', path: '/admin/cms/menus' },
    { name: 'menu', path: '/admin/cms/menus/main/links' },
    { name: 'comments', path: '/admin/cms/comments' },
    { name: 'submissions-home', path: '/admin/cms/submissions' },
    { name: 'submissions', path: '/admin/cms/forms/contact/submissions' },
    { name: 'blueprints', path: '/admin/cms/blueprints' },
    { name: 'blueprint-builder', path: firstLink('/admin/cms/blueprints', /\/cms\/blueprints\/\d+\/edit$/) },
    { name: 'fieldsets', path: '/admin/cms/fieldsets' },
    { name: 'fieldset-builder', path: firstLink('/admin/cms/fieldsets', /\/cms\/fieldsets\/\d+\/edit$/) },
    { name: 'media', path: '/admin/media' },
    { name: 'media-details', path: '/admin/media', then: clickFirst('button[aria-label^="Open "]') },
    { name: 'activity', path: '/admin/activity' },
    { name: 'ai-access', path: '/admin/ai' },
    { name: 'dialog', path: '/admin/ai', then: clickFirst('button:has-text("New token")') },
    ...['branding', 'security', 'mail', 'site', 'locales', 'code', 'comments'].map((section) => ({ name: `settings-${section}`, path: `/admin/settings/${section}` })),
    { name: 'users', path: '/admin/users' },
    { name: 'user-edit', path: firstLink('/admin/users', /\/users\/\d+\/edit$/) },
    { name: 'roles', path: '/admin/roles' },
    { name: 'role-create', path: '/admin/roles/create' },
    { name: 'collections', path: '/admin/cms/collections' },
    { name: 'collection-edit', path: firstLink('/admin/cms/collections', /\/collections\/\d+\/edit$/) },
    { name: 'forms-resource', path: '/admin/cms/forms' },
    { name: 'redirects', path: '/admin/cms/redirects' },
    { name: 'redirect-create', path: '/admin/cms/redirects/create' },
    { name: 'date-picker', path: '/admin/activity', then: clickFirst('button:has(svg.lucide-calendar)') },
    { name: 'docs', path: '/admin/docs' },
    { name: 'docs-page', path: '/admin/docs/panel/frontend' },
    { name: 'guide', path: '/admin/guide' },
    { name: 'guide-page', path: '/admin/guide/website/entries' },
    { name: 'command-palette', path: '/admin', then: async (page) => { await page.keyboard.press('ControlOrMeta+k'); await page.waitForTimeout(450); } },
    { name: 'mobile-nav', path: '/admin', mobileOnly: true, then: clickFirst('[data-sidebar="trigger"]') },
    { name: 'not-found', path: '/admin/this-page-does-not-exist', status: 404 },
];

/** Signed out. */
const guestPages = [
    { name: 'login', path: '/admin/login' },
    { name: 'forgot-password', path: '/admin/forgot-password' },
];

let current = { expected: new Set() };

async function visit(page, path) {
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600); // entrance animations

    return response;
}

const problems = [];

function watch(page) {
    const report = (kind, detail) => problems.push(`[${current.label}] ${kind}: ${detail}`);
    const ignored = (url) => ignoredHosts.some((host) => url.includes(`//${host}`));
    page.on('pageerror', (error) => report('page error', error.message));
    page.on('console', (message) => {
        if (message.type() !== 'error') return;
        const location = message.location()?.url ?? '';
        if (ignored(location) || (/Failed to load resource/.test(message.text()) && (current.expected.has(location) || /net::ERR_/.test(message.text())))) return;
        report('console error', message.text());
    });
    page.on('requestfailed', (request) => {
        const error = request.failure()?.errorText ?? '';
        if (ignored(request.url()) || error === 'net::ERR_ABORTED') return; // aborted: Inertia cancelled a visit
        report('request failed', `${request.url()} ${error}`);
    });
    page.on('response', (response) => {
        if (response.status() < 400 || current.expected.has(response.url()) || ignored(response.url())) return;
        report(`HTTP ${response.status()}`, response.url());
    });
}

async function checkPage(page, entry, label) {
    current = { label, expected: new Set() };
    try {
        const path = typeof entry.path === 'function' ? await entry.path(page) : entry.path;
        current.expected = new Set(entry.status ? [base + path] : []);
        const response = await visit(page, path);
        if (entry.status && response?.status() !== entry.status) problems.push(`[${label}] expected HTTP ${entry.status}, got ${response?.status()}`);
        if (entry.then) await entry.then(page);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 1) problems.push(`[${label}] the page is ${overflow}px wider than the screen`);
        if (shots) await page.screenshot({ path: join(shots, `${label}.png`), fullPage: !entry.then });
    } catch (error) {
        problems.push(`[${label}] ${error.message.split('\n')[0]}`);
    }
}

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
let count = 0;
for (const viewportName of wantedViewports) {
    for (const scheme of schemes) {
        const context = await browser.newContext({ viewport: viewports[viewportName], colorScheme: scheme, reducedMotion: 'no-preference' });
        // The panel follows the system theme unless a choice is stored (hooks/use-appearance.ts).
        await context.addInitScript(() => { try { localStorage.removeItem('sunrice-appearance'); } catch { /* none */ } });
        const page = await context.newPage();
        watch(page);
        const suffix = `${viewportName}-${scheme}`;

        for (const entry of guestPages) {
            if (only && !entry.name.includes(only)) continue;
            await checkPage(page, entry, `${entry.name}-${suffix}`);
            count++;
        }

        // Sign in through the form; with two-factor on, the code page is checked too.
        current = { label: `sign-in-${suffix}`, expected: new Set() };
        await visit(page, '/admin/login');
        await page.fill('input[type="email"], input[name="email"]', process.env.SMOKE_EMAIL ?? 'admin@example.com');
        await page.fill('input[type="password"]', process.env.SMOKE_PASSWORD ?? 'password');
        await Promise.all([page.waitForURL((url) => !url.pathname.endsWith('/admin/login'), { timeout: 10000 }).catch(() => {}), page.locator('button[type="submit"]').first().click()]);
        await page.waitForLoadState('networkidle');
        if (page.url().includes('/login/code')) {
            await page.waitForTimeout(600);
            if (shots) await page.screenshot({ path: join(shots, `two-factor-${suffix}.png`) });
            await visit(page, '/_workbench/login/' + encodeURIComponent(process.env.SMOKE_EMAIL ?? 'admin@example.com'));
        } else if (page.url().endsWith('/admin/login')) {
            await visit(page, '/_workbench/login/' + encodeURIComponent(process.env.SMOKE_EMAIL ?? 'admin@example.com'));
        }

        for (const entry of pages) {
            if (only && !entry.name.includes(only)) continue;
            if (entry.mobileOnly && viewportName !== 'mobile') continue;
            await checkPage(page, entry, `${entry.name}-${suffix}`);
            count++;
        }
        await context.close();
    }
}
await browser.close();

if (problems.length > 0) {
    console.error(`${problems.length} problem(s) on ${count} page views:\n  ${problems.join('\n  ')}`);
    process.exit(1);
}
console.log(`OK: ${count} page views, no errors${shots ? `; screenshots in ${shots}` : ''}.`);
