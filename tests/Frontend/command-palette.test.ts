import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import {
    commandScore, matchCommands, navigationCommands, queryCommandSources, registerCommandSource, subscribeCommandSources, type CommandItem,
} from '../../packages/admin/resources/js/lib/command-sources.ts';
import { loadAdminModule, render } from './support/render.ts';

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

type CommandList = {
    itemScore: (value: string, keywords: string[] | undefined, search: string) => number;
    displayOrder: (items: { score: number; group: string | null }[]) => number[];
    nextActive: (values: string[], active: string | undefined, key: string, loop?: boolean) => string | undefined;
    isNavigationKey: (key: string) => boolean;
};

test('the command list filters items on their value and keywords with the palette ranking', async () => {
    const { itemScore } = await loadAdminModule<CommandList>('lib/command-list.ts');

    assert.equal(itemScore('Media library', undefined, ''), 1);
    assert.equal(itemScore('Media library', undefined, 'media'), 4);
    assert.equal(itemScore('Media library', undefined, 'lib'), 3);
    assert.equal(itemScore('Users', ['people'], 'people'), 1);
    assert.equal(itemScore('Users', ['people'], 'xyz'), 0);
    assert.equal(itemScore('Café', undefined, 'cafe'), 4);
});

test('the command list shows matches best first, groups by their best item, ties in document order', async () => {
    const { displayOrder } = await loadAdminModule<CommandList>('lib/command-list.ts');

    // Equal scores (no search, or no filtering): document order.
    assert.deepEqual(displayOrder([{ score: 1, group: 'a' }, { score: 1, group: 'a' }, { score: 1, group: null }, { score: 1, group: 'b' }]), [0, 1, 2, 3]);
    // Within a group by score; the group with the better best item first.
    assert.deepEqual(displayOrder([
        { score: 1, group: 'a' }, { score: 3, group: 'a' },
        { score: 4, group: 'b' }, { score: 2, group: 'b' }, { score: 4, group: 'b' },
    ]), [2, 4, 3, 1, 0]);
    // Ungrouped items rank alongside groups.
    assert.deepEqual(displayOrder([{ score: 2, group: 'a' }, { score: 3, group: null }, { score: 1, group: null }]), [1, 0, 2]);
    assert.deepEqual(displayOrder([]), []);
});

test('the command list moves the active item with Up, Down, Home and End, wrapping round', async () => {
    const { nextActive, isNavigationKey } = await loadAdminModule<CommandList>('lib/command-list.ts');
    const values = ['a', 'b', 'c'];

    assert.equal(nextActive(values, 'a', 'ArrowDown'), 'b');
    assert.equal(nextActive(values, 'c', 'ArrowDown'), 'a');
    assert.equal(nextActive(values, 'a', 'ArrowUp'), 'c');
    assert.equal(nextActive(values, 'b', 'ArrowUp'), 'a');
    assert.equal(nextActive(values, 'b', 'Home'), 'a');
    assert.equal(nextActive(values, 'a', 'End'), 'c');
    assert.equal(nextActive(values, undefined, 'ArrowDown'), 'a');
    assert.equal(nextActive(values, 'gone', 'ArrowUp'), 'c');
    assert.equal(nextActive(values, 'c', 'ArrowDown', false), 'c');
    assert.equal(nextActive(values, 'a', 'ArrowUp', false), 'a');
    assert.equal(nextActive([], 'a', 'ArrowDown'), undefined);
    assert.equal(isNavigationKey('ArrowDown'), true);
    assert.equal(isNavigationKey('Enter'), false);
});

test('renders the command list as a combobox controlling a listbox of options', async () => {
    const { createElement: h } = await import('react');
    const ui = await loadAdminModule<Record<string, ComponentType<any>>>('components/ui/command.tsx');
    const html = render(ui.Command!, { label: 'Pick a page' },
        h(ui.CommandInput!, { placeholder: 'Search…' }),
        h(ui.CommandList!, null,
            h(ui.CommandEmpty!, null, 'Nothing found.'),
            h(ui.CommandGroup!, { heading: 'Pages' },
                h(ui.CommandItem!, { value: 'users', keywords: ['people'] }, 'Users'),
                h(ui.CommandItem!, { value: 'media', disabled: true }, 'Media'),
            ),
            h(ui.CommandSeparator!, null),
            h(ui.CommandItem!, null, 'Settings'),
        ),
    );

    const listId = /<div id="([^"]+)" role="listbox"/.exec(html)?.[1];
    assert.ok(listId);
    assert.match(html, new RegExp(`<input[^>]*role="combobox"[^>]*aria-expanded="true"[^>]*aria-controls="${listId}"[^>]*aria-autocomplete="list"`));
    assert.match(html, /aria-label="Pick a page"/);
    assert.equal(html.match(/role="option"/g)?.length, 3);
    assert.match(html, /role="option" aria-selected="false" aria-disabled="true"[^>]*data-value="media"/);
    assert.match(html, /data-value="Settings"/, 'an item without a value is known by its text');
    const headingId = /<div id="([^"]+)" aria-hidden="true" data-slot="command-group-heading"[^>]*>Pages<\/div>/.exec(html)?.[1];
    assert.ok(headingId);
    assert.match(html, new RegExp(`role="group" aria-labelledby="${headingId}"`));
    assert.match(html, /role="separator"/);
    assert.doesNotMatch(html, /Nothing found/, 'the empty state waits until the items are counted');
    assert.doesNotMatch(html, /hidden=""/);
});
