import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { SHARED_MODULES } from '../../packages/admin-sdk/dist/shared.js';

const require = createRequire(new URL('../../packages/admin/package.json', import.meta.url));
const publicKeys = (module: object) => Object.keys(module).filter((key) => !key.startsWith('__') && key !== 'browser' && key !== 'default').sort();

/*
 * The SDK points a plugin's imports at the panel's copies of these
 * modules, export by export. A name missing from the list would be
 * undefined in plugins, so the lists must match the real modules.
 */
test('the shared module lists match the modules the panel ships', async () => {
    for (const name of ['react', 'react/jsx-runtime', 'react-dom', 'react-dom/client'] as const) {
        assert.deepEqual([...SHARED_MODULES[name]].sort(), publicKeys(require(name)), name);
    }
    const inertia = await import('@inertiajs/react');
    assert.deepEqual([...SHARED_MODULES['@inertiajs/react']].sort(), publicKeys(inertia), '@inertiajs/react');
});

test('the UI list matches what the runtime provides', () => {
    const source = readFileSync(new URL('../../packages/admin/resources/js/runtime.ts', import.meta.url), 'utf8');
    const block = /const ui = \{([\s\S]*?)\};/.exec(source)?.[1] ?? '';
    const keys = block.split(',').map((key) => key.trim()).filter(Boolean).sort();

    assert.deepEqual([...SHARED_MODULES['@sunrice/admin-sdk/ui']].sort(), keys);
});
