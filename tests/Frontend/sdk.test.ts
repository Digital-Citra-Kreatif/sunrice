import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { build } from 'vite';
import { sunrice } from '../../packages/admin-sdk/dist/vite.js';
import { SDK_VERSION } from '../../packages/admin-sdk/dist/shared.js';

const outDir = mkdtempSync(join(tmpdir(), 'sunrice-sdk-'));

async function buildFixture(): Promise<string> {
    await build({
        root: new URL('fixtures/plugin', import.meta.url).pathname,
        logLevel: 'silent',
        esbuild: { jsx: 'automatic' },
        plugins: [sunrice({ entry: new URL('fixtures/plugin/entry.tsx', import.meta.url).pathname, outDir })],
    });

    // Load the output as an ES module, so each test can import a fresh copy.
    writeFileSync(join(outDir, 'package.json'), '{"type": "module"}');

    return join(outDir, 'admin.js');
}

/** A stand-in for the panel's window.Sunrice. */
function fakeRuntime(version: number) {
    const registered: Record<string, unknown> = {};
    const stub = new Proxy({}, { get: (_, key) => (key === 'then' ? undefined : () => null) });
    globalThis.Sunrice = {
        version,
        modules: { react: stub, 'react/jsx-runtime': stub, '@inertiajs/react': stub, '@imajiner/admin-sdk/ui': stub } as never,
        registerPage: (name: string, component: unknown) => { registered[name] = component; },
        registerPageLoader: (name: string, load: unknown) => { registered[name] = load; },
        adminUrl: (path = '') => `/admin/${path}`,
        t: (text: string) => text,
    };

    return registered;
}

test('builds a plugin bundle that uses the panel\'s React, Inertia and UI', async () => {
    const file = await buildFixture();
    const code = readFileSync(file, 'utf8');
    // Nothing split off: one file, as before.
    assert.ok(!existsSync(join(outDir, 'chunks')));

    assert.match(code, /globalThis\.Sunrice/);
    // React's own code isn't in the bundle.
    assert.doesNotMatch(code, /react\.production|__CLIENT_INTERNALS|useSyncExternalStore\s*=\s*function/);
    assert.ok(code.length < 3000, `the bundle should be tiny, was ${code.length} bytes`);

    const registered = fakeRuntime(SDK_VERSION);
    await import(`${pathToFileURL(file).href}?ok`);
    assert.deepEqual(Object.keys(registered), ['Shop/Orders/Index']);
});

test('refuses to run in a panel with another runtime version', async () => {
    const file = join(outDir, 'admin.js');
    fakeRuntime(SDK_VERSION + 1);

    await assert.rejects(import(`${pathToFileURL(file).href}?mismatch`), /built for admin runtime/);
});

const splitDir = mkdtempSync(join(tmpdir(), 'sunrice-sdk-split-'));

async function buildSplitFixture(): Promise<string> {
    await build({
        root: new URL('fixtures/plugin-split', import.meta.url).pathname,
        logLevel: 'silent',
        esbuild: { jsx: 'automatic' },
        plugins: [sunrice({ entry: new URL('fixtures/plugin-split/entry.tsx', import.meta.url).pathname, outDir: splitDir })],
    });
    writeFileSync(join(splitDir, 'package.json'), '{"type": "module"}');

    return join(splitDir, 'admin.js');
}

test('splits a bundle with lazy pages into chunks next to a small admin.js', async () => {
    const file = await buildSplitFixture();
    const chunks = readdirSync(join(splitDir, 'chunks'));
    const facade = readFileSync(file, 'utf8');

    // admin.js only imports the main chunk (by a URL without the panel's ?v= query), and no chunk imports admin.js.
    assert.match(facade, /^export \* from '\.\/chunks\/admin-[\w-]+\.js';$/m);
    assert.ok(facade.length < 300, facade);
    for (const chunk of chunks) {
        assert.doesNotMatch(readFileSync(join(splitDir, 'chunks', chunk), 'utf8'), /admin\.js['"]/, chunk);
    }
    assert.ok(chunks.some((chunk) => chunk.startsWith('Orders-')), chunks.join(', '));
    assert.ok(chunks.some((chunk) => chunk.startsWith('money-')), chunks.join(', '));

    // A stand-in document that records the modulepreload links.
    const preloaded: string[] = [];
    Object.assign(globalThis, {
        CSS: { escape: (value: string) => value },
        document: {
            querySelector: () => null,
            createElement: () => ({}),
            head: { appendChild: (link: { rel: string; href: string }) => preloaded.push(`${link.rel} ${link.href.split('/').pop()}`) },
        },
    });
    try {
        const registered = fakeRuntime(SDK_VERSION);
        await import(`${pathToFileURL(file).href}?v=123`);
        assert.deepEqual(Object.keys(registered).sort(), ['Shop/Orders/Index', 'Shop/Orders/Show']);

        const page = await (registered['Shop/Orders/Index'] as () => Promise<{ default: unknown }>)();
        assert.equal(typeof page.default, 'function');
        // The page's own imports were preloaded as it loaded.
        assert.ok(preloaded.some((link) => /^modulepreload money-[\w-]+\.js$/.test(link)), preloaded.join(', '));
        // One copy of the main chunk: the entry and the page pushed to the same list.
        assert.deepEqual((globalThis as { splitLoads?: string[] }).splitLoads, ['entry', 'orders']);

        assert.equal(typeof await (registered['Shop/Orders/Show'] as () => Promise<unknown>)(), 'function');
    } finally {
        Reflect.deleteProperty(globalThis, 'document');
        Reflect.deleteProperty(globalThis, 'CSS');
    }
});
