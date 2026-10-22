import { test } from 'node:test';
import assert from 'node:assert/strict';
import { childIndexes, reorderLevel, subtreeEnd, treeOrder } from '../../packages/cms/resources/js/lib/tree.ts';
import { syncKeys, termPickerQuery } from '../../packages/cms/resources/js/lib/values.ts';

const tree = (spec: string) => spec.split(' ').map((s) => ({ label: s.replace(/^-+/, ''), depth: s.length - s.replace(/^-+/, '').length }));
const shape = (items: { label: string; depth: number }[]) => items.map((i) => `${'-'.repeat(i.depth)}${i.label}`).join(' ');

test('finds the items directly under one', () => {
    const items = tree('A -A1 --A1a -A2 B C -C1');

    assert.deepEqual(childIndexes(items, -1), [0, 4, 5]);
    assert.deepEqual(childIndexes(items, 0), [1, 3]);
    assert.deepEqual(childIndexes(items, 1), [2]);
    assert.deepEqual(childIndexes(items, 4), []);
    assert.equal(subtreeEnd(items, 0), 4);
});

test('reorders one level, children carried along', () => {
    const items = tree('A -A1 --A1a -A2 B C -C1');

    assert.equal(shape(reorderLevel(items, -1, [5, 0, 4])), 'C -C1 A -A1 --A1a -A2 B');
    assert.equal(shape(reorderLevel(items, 0, [3, 1])), 'A -A2 -A1 --A1a B C -C1');
    // Anything but the level's own indexes changes nothing.
    assert.equal(shape(reorderLevel(items, -1, [0, 1, 4])), shape(items));
    assert.equal(shape(reorderLevel(items, 0, [1])), shape(items));
});

test('keeps keys for a list\'s items in step with its length', () => {
    const keys = syncKeys([], 2);
    assert.equal(keys.length, 2);
    assert.deepEqual(syncKeys(keys, 2), keys);
    const more = syncKeys(keys, 3);
    assert.deepEqual(more.slice(0, 2), keys);
    assert.notEqual(more[2], keys[0]);
    assert.equal(new Set(more).size, 3);
    assert.deepEqual(syncKeys(more, 1), [keys[0]]);
});

test('builds the term picker query', () => {
    assert.equal(termPickerQuery({ taxonomy: 'tags' }), 'taxonomy=tags');
    assert.equal(termPickerQuery({ taxonomy: 'tags', q: 'ne ws', ids: [3, 4] }), 'taxonomy=tags&q=ne+ws&ids%5B%5D=3&ids%5B%5D=4');
});

test('puts a flat list with parents in tree order, each level in its own order', () => {
    // As the entries list sends them: by sort order across levels.
    const rows = [
        { id: 1, label: 'Home', parentId: null }, { id: 2, label: 'About', parentId: null }, { id: 3, label: 'Story', parentId: 2 },
        { id: 4, label: 'Services', parentId: null }, { id: 5, label: 'Buy', parentId: 4 }, { id: 6, label: 'Contact', parentId: null },
        { id: 7, label: 'Sell', parentId: 4 }, { id: 8, label: 'Orphan', parentId: 99 }, { id: 9, label: 'Deep', parentId: 3 },
    ];

    assert.equal(shape(treeOrder(rows, (row) => row.parentId)), 'Home About -Story --Deep Services -Buy -Sell Contact Orphan');
    // A loop (bad data) doesn't hide its entries.
    assert.equal(treeOrder([{ id: 1, label: 'A', parentId: 2 }, { id: 2, label: 'B', parentId: 1 }], (row) => row.parentId).length, 2);
});
