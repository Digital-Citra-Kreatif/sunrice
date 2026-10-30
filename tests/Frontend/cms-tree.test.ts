import { test } from 'node:test';
import assert from 'node:assert/strict';
import { childIndexes, levelPosition, moveToPosition, parentIndex, reorderLevel, subtreeEnd, treeOrder } from '../../packages/cms/resources/js/lib/tree.ts';
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

test('finds an item\'s parent and its place among its siblings', () => {
    const items = tree('A -A1 --A1a -A2 B C -C1');

    assert.equal(parentIndex(items, 0), -1);
    assert.equal(parentIndex(items, 2), 1);
    assert.equal(parentIndex(items, 3), 0);
    assert.deepEqual(levelPosition(items, 5), { parent: -1, position: 3, count: 3 });
    assert.deepEqual(levelPosition(items, 3), { parent: 0, position: 2, count: 2 });
});

test('moves an item to a position among its siblings, clamped, children carried along', () => {
    const items = tree('A -A1 --A1a -A2 B C -C1');
    const moved = (index: number, position: number) => {
        const move = moveToPosition(items, index, position);
        return move === null ? null : shape(reorderLevel(items, move.parent, move.order));
    };

    assert.equal(moved(0, 3), 'B C -C1 A -A1 --A1a -A2');
    assert.equal(moved(5, 1), 'C -C1 A -A1 --A1a -A2 B');
    assert.equal(moved(0, 2), 'B A -A1 --A1a -A2 C -C1');
    // Past the last is the last; below 1 the first.
    assert.equal(moved(0, 50), 'B C -C1 A -A1 --A1a -A2');
    assert.equal(moved(4, -3), 'B A -A1 --A1a -A2 C -C1');
    // Within its own level only.
    assert.equal(moved(3, 1), 'A -A2 -A1 --A1a B C -C1');
    // Where it already is, or not a number: nothing to do.
    assert.equal(moved(0, 1), null);
    assert.equal(moved(3, 9), null);
    assert.equal(moved(0, Number.NaN), null);
});

test('moves the first of 60 to position 50', () => {
    const items = Array.from({ length: 60 }, (_, i) => ({ label: `E${i + 1}`, depth: 0 }));
    const move = moveToPosition(items, 0, 50)!;
    const next = reorderLevel(items, move.parent, move.order);

    assert.equal(next[49]!.label, 'E1');
    assert.equal(next[0]!.label, 'E2');
    assert.equal(next.length, 60);
});
