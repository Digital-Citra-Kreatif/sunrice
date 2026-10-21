import { test } from 'node:test';
import assert from 'node:assert/strict';
import { flatten, indent, moveItem, newItem, outdent, removeItem, toTree, type FlatItem } from '../../packages/cms/resources/js/lib/menu.ts';

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

test('moves past siblings, children included', () => {
    const items = [item('a', 0), item('a1', 1), item('b', 0), item('b1', 1), item('c', 0)];

    assert.deepEqual(shape(moveItem(items, 2, -1)), ['b', '-b1', 'a', '-a1', 'c']);
    assert.deepEqual(shape(moveItem(items, 0, 1)), ['b', '-b1', 'a', '-a1', 'c']);
    assert.deepEqual(shape(moveItem(items, 4, 1)), shape(items)); // last
    assert.deepEqual(shape(moveItem(items, 1, -1)), shape(items)); // first child
    assert.deepEqual(shape(moveItem(items, 3, 1)), shape(items)); // last child: doesn't leave its parent
});

test('removes an item with its children', () => {
    assert.deepEqual(shape(removeItem([item('a', 0), item('a1', 1), item('b', 0)], 0)), ['b']);
});
