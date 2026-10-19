import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
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
        modules: { react: stub, 'react/jsx-runtime': stub, '@inertiajs/react': stub, '@sunrice/admin-sdk/ui': stub } as never,
        registerPage: (name: string, component: unknown) => { registered[name] = component; },
        adminUrl: (path = '') => `/admin/${path}`,
    };

    return registered;
}

test('builds a plugin bundle that uses the panel\'s React, Inertia and UI', async () => {
    const file = await buildFixture();
    const code = readFileSync(file, 'utf8');

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
