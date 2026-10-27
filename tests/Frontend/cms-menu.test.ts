import { test } from 'node:test';
import assert from 'node:assert/strict';
import { flatten, indent, kindOf, labelFallback, newItem, outdent, removeItem, retarget, toTree, type FlatItem } from '../../packages/cms/resources/js/lib/menu.ts';

const item = (label: string, depth: number) => newItem({ url: `/${label}`, labels: { en: label }, depth });
const shape = (items: FlatItem[]) => items.map((i) => `${'-'.repeat(i.depth)}${i.labels.en}`);

test('round-trips a tree', () => {
    const tree = [
        { entry_id: null, url: '/a', labels: { en: 'A' }, new_tab: false, children: [{ entry_id: 3, url: null, labels: {}, new_tab: true, children: [] }] },
        { entry_id: null, url: '/b', labels: { en: 'B' }, new_tab: false, children: [] },
    ];
    const flat = flatten(tree);

    assert.deepEqual(flat.map((i) => i.depth), [0, 1, 0]);
    assert.deepEqual(toTree(flat), tree);
});

test('indents under the item above, within the depth', () => {
    const items = [item('a', 0), item('b', 0), item('c', 0)];

    assert.deepEqual(shape(indent(items, 1, 3)), ['a', '-b', 'c']);
    assert.deepEqual(shape(indent(items, 0, 3)), ['a', 'b', 'c']); // nothing above
    const deep = [item('a', 0), item('b', 1), item('c', 2), item('d', 2)];
    assert.deepEqual(shape(indent(deep, 3, 3)), shape(deep)); // the third level is the last
    assert.deepEqual(shape(indent([item('a', 0), item('b', 0), item('c', 1)], 1, 3)), ['a', '-b', '--c']); // children follow
    assert.deepEqual(shape(indent([item('a', 0), item('b', 0), item('c', 1), item('d', 2)], 1, 3)), ['a', 'b', '-c', '--d']); // too deep with its children
});

test('outdents with the children', () => {
    assert.deepEqual(shape(outdent([item('a', 0), item('b', 1), item('c', 2)], 1)), ['a', 'b', '-c']);
    assert.deepEqual(shape(outdent([item('a', 0)], 0)), ['a']);
});

test('outdents an item alone, after its parent\'s other children, which stay where they were', () => {
    const items = [item('p', 0), item('sale', 1), item('rent', 1), item('r1', 2), item('villas', 1), item('team', 0)];

    assert.deepEqual(shape(outdent(items, 2)), ['p', '-sale', '-villas', 'rent', '-r1', 'team']);
    assert.deepEqual(shape(indent(outdent(items, 2), 3, 3)), ['p', '-sale', '-villas', '-rent', '--r1', 'team']); // and back in, among them
});

test('removes an item with its children', () => {
    assert.deepEqual(shape(removeItem([item('a', 0), item('a1', 1), item('b', 0)], 0)), ['b']);
});

test('the menu editor\'s entry search says to create the page first when nothing matches', async () => {
    const { readFile } = await import('node:fs/promises');
    const page = await readFile(new URL('../../packages/cms/resources/js/pages/Menus/Edit.tsx', import.meta.url), 'utf8');
    const picker = await readFile(new URL('../../packages/cms/resources/js/components/EntriesPicker.tsx', import.meta.url), 'utf8');

    assert.match(page, /emptyHint=\{menuEntryHint\(\)\}/);
    assert.match(page, /create the page first \(Content → Entries\)/);
    assert.match(picker, /t\('No entries match\.'\)\}\{emptyHint/);
});

test('a listing link keeps its filters through the tree', () => {
    const listing = newItem({ collection_id: 3, query: 'listing_type=for-sale' });
    assert.equal(newItem({ url: '/x' }).query, null);
    assert.equal(toTree([listing])[0]!.query, 'listing_type=for-sale');
    assert.equal(flatten(toTree([listing]))[0]!.query, 'listing_type=for-sale');
});

test('a filtered listing link falls back to the main language label, like an address (NEW2-i18n-2)', () => {
    assert.equal(labelFallback({ url: null, collection_id: 3, query: 'listing_type=for-sale' }), 'main');
    assert.equal(labelFallback({ url: null, collection_id: 3, query: '  ' }), 'title');
    assert.equal(labelFallback({ url: null, collection_id: 3, query: null }), 'title');
    assert.equal(labelFallback({ url: null, collection_id: null }), 'title');
    assert.equal(labelFallback({ url: '/contact', collection_id: null }), 'main');
});

test('names what an item links to', () => {
    assert.equal(kindOf(newItem({ url: '/one' })), 'url');
    assert.equal(kindOf(newItem({ entry_id: 3 })), 'entry');
    assert.equal(kindOf(newItem({ term_id: 4, collection_id: 2 })), 'term');
    assert.equal(kindOf(newItem({ taxonomy_id: 5, collection_id: 2 })), 'index');
    assert.equal(kindOf(newItem({ collection_id: 2, query: 'a=1' })), 'listing');
});

test('changes where an item links, keeping its labels, new tab, place and children', () => {
    const items = [item('a', 0), { ...newItem({ url: '/oen', labels: { en: 'One', id: 'Satu' }, new_tab: true }), depth: 0 }, item('c', 1), item('d', 0)];
    const changed = retarget(items, 1, { url: '/one' });

    assert.deepEqual(changed[1], { ...items[1], url: '/one' });
    assert.equal(changed[1]!.key, items[1]!.key);
    assert.deepEqual(shape(changed), shape(items));
    assert.deepEqual(toTree(changed)[1]!.children.map((child) => child.labels.en), ['c']);
    // Another kind of target clears the old one.
    const listing = retarget(changed, 1, { collection_id: 7, query: 'a=1', target: { title: 'Blog', kind: 'Listing page', live: true } });
    assert.deepEqual([listing[1]!.url, listing[1]!.collection_id, listing[1]!.query, listing[1]!.labels], [null, 7, 'a=1', { en: 'One', id: 'Satu' }]);
    assert.equal(retarget(listing, 1, { entry_id: 9 })[1]!.query, null);
});
