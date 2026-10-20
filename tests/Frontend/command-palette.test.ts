import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    commandScore, matchCommands, navigationCommands, queryCommandSources, registerCommandSource, subscribeCommandSources, type CommandItem,
} from '../../packages/admin/resources/js/lib/command-sources.ts';
import { loadAdminModule } from './support/render.ts';

const navigation = [
    { group: null, items: [{ key: 'users', label: 'Users', url: 'users', icon: 'users', children: [] }] },
    { group: 'Content', items: [
        { key: 'entries', label: 'Entries', url: 'cms/entries', icon: 'file-text', children: [
            { key: 'entries.blog', label: 'Blog', url: 'cms/collections/blog/entries', icon: '' },
            { key: 'entries.pages', label: 'Pages', url: 'cms/collections/pages/entries', icon: 'book-open' },
        ] },
    ] },
];

test('turns the navigation into commands, children under their parent', () => {
    const commands = navigationCommands(navigation, (url) => `/admin/${url}`);

    assert.deepEqual(commands.map((c) => [c.id, c.label, c.group, c.url, c.icon, c.description]), [
        ['users', 'Users', 'Pages', '/admin/users', 'users', undefined],
        ['entries', 'Entries', 'Content', '/admin/cms/entries', 'file-text', undefined],
        ['entries.blog', 'Blog', 'Content', '/admin/cms/collections/blog/entries', 'file-text', 'Entries'],
        ['entries.pages', 'Pages', 'Content', '/admin/cms/collections/pages/entries', 'book-open', 'Entries'],
    ]);
    assert.deepEqual(commands[2]?.keywords, ['Entries']);
});

test('matches on the label first, then on words of it, keywords and group, and ignores accents and case', () => {
    const items: CommandItem[] = [
        { id: 'a', label: 'Media library', group: 'Pages' },
        { id: 'b', label: 'Libraries', group: 'Pages' },
        { id: 'c', label: 'Users', group: 'Pages', keywords: ['people', 'library cards'] },
        { id: 'd', label: 'Café', group: 'Content' },
    ];

    assert.deepEqual(matchCommands(items, '').map((i) => i.id), ['a', 'b', 'c', 'd']);
    assert.deepEqual(matchCommands(items, 'lib').map((i) => i.id), ['b', 'a', 'c']);
    assert.deepEqual(matchCommands(items, 'media lib').map((i) => i.id), ['a']);
    assert.deepEqual(matchCommands(items, 'cafe').map((i) => i.id), ['d']);
    assert.deepEqual(matchCommands(items, 'content').map((i) => i.id), ['d']);
    assert.deepEqual(matchCommands(items, 'xyz'), []);
    assert.equal(commandScore(items[0]!, 'media'), 4);
    assert.equal(commandScore(items[0]!, 'rary'), 2);
    assert.equal(commandScore(items[2]!, 'people'), 1);
    assert.equal(commandScore(items[2]!, 'people nobody'), 0);
});

test('asks registered sources once the query is long enough, and drops the ones that fail', async () => {
    const asked: string[] = [];
    let changes = 0;
    const unsubscribe = subscribeCommandSources(() => { changes++; });
    const unregisterEntries = registerCommandSource((query) => { asked.push(query); return [{ id: '1', label: `Entry for ${query}`, group: 'Entries' }]; });
    const unregisterBroken = registerCommandSource(() => Promise.reject(new Error('down')), { minLength: 1 });
    const unregisterEager = registerCommandSource(() => [{ id: 'x', label: 'Always' }], { minLength: 0 });

    assert.equal(changes, 3);
    assert.deepEqual((await queryCommandSources('a', new AbortController().signal)).map((i) => i.id), ['x']);
    assert.deepEqual(asked, []);
    assert.deepEqual((await queryCommandSources('ab', new AbortController().signal)).map((i) => i.label), ['Entry for ab', 'Always']);
    assert.deepEqual(asked, ['ab']);

    unregisterEntries();
    unregisterBroken();
    unregisterEager();
    assert.equal(changes, 6);
    assert.deepEqual(await queryCommandSources('abc', new AbortController().signal), []);
    unsubscribe();
});

test('recognises Cmd+K and Ctrl+K, not other K presses', async () => {
    const { isCommandPaletteShortcut } = await loadAdminModule<{ isCommandPaletteShortcut: (event: object) => boolean }>('components/app/command-palette.tsx');
    const key = (overrides: object) => ({ key: 'k', metaKey: false, ctrlKey: false, altKey: false, shiftKey: false, ...overrides });

    assert.equal(isCommandPaletteShortcut(key({ metaKey: true })), true);
    assert.equal(isCommandPaletteShortcut(key({ ctrlKey: true, key: 'K' })), true);
    assert.equal(isCommandPaletteShortcut(key({})), false);
    assert.equal(isCommandPaletteShortcut(key({ metaKey: true, shiftKey: true })), false);
    assert.equal(isCommandPaletteShortcut(key({ ctrlKey: true, key: 'j' })), false);
});
