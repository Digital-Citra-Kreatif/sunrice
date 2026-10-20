import { test } from 'node:test';
import assert from 'node:assert/strict';
import { childIndexes, reorderLevel, siblingOrder, subtreeEnd } from '../../packages/cms/resources/js/lib/tree.ts';
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

test('gives the moved item\'s siblings in their new order', () => {
    const rows = [{ id: 1, parent: null }, { id: 2, parent: 1 }, { id: 3, parent: null }, { id: 4, parent: 1 }];

    assert.deepEqual(siblingOrder(rows, 3, (r) => r.parent), [1, 3]);
    assert.deepEqual(siblingOrder(rows, 4, (r) => r.parent), [2, 4]);
    assert.deepEqual(siblingOrder(rows, 9, (r) => r.parent), []);
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
