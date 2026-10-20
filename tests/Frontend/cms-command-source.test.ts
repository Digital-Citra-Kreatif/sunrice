import assert from 'node:assert/strict';
import { test } from 'node:test';
import { entryCommands, searchEntries } from '../../packages/cms/resources/js/lib/command-source.ts';

const href = (path: string) => `/admin/${path}`;

test('lists the entry picker\'s results as commands that open the editor', () => {
    const commands = entryCommands([
        { id: 7, title: 'Hello', collection: 'Blog', collectionHandle: 'blog', status: 'published' },
        { id: 9, title: 'Soon', collection: 'Pages', collectionHandle: 'pages', status: 'draft' },
    ], href);

    assert.deepEqual(commands, [
        { id: '7', label: 'Hello', description: 'Blog', group: 'Entries', icon: 'file-text', url: '/admin/cms/collections/blog/entries/7/edit' },
        { id: '9', label: 'Soon', description: 'Pages · draft', group: 'Entries', icon: 'file-text', url: '/admin/cms/collections/pages/entries/9/edit' },
    ]);
});

test('searches the entry picker with the query, and gives nothing when refused', async () => {
    const calls: string[] = [];
    const original = globalThis.fetch;
    globalThis.fetch = (async (url: string, init?: RequestInit) => {
        calls.push(url);
        assert.equal(init?.credentials, 'same-origin');
        const ok = !url.includes('forbidden');
        return { ok, json: async () => ({ items: [{ id: 1, title: 'Found', collection: 'Blog', status: 'published' }] }) } as Response;
    }) as typeof fetch;

    try {
        const found = await searchEntries(' hello ', new AbortController().signal, href);
        assert.deepEqual(found.map((c) => c.label), ['Found']);
        assert.deepEqual(calls, ['/admin/cms/entries/picker?search=hello']);
        assert.deepEqual(await searchEntries('forbidden', new AbortController().signal, href), []);
    } finally {
        globalThis.fetch = original;
    }
});
