import { test } from 'node:test';
import assert from 'node:assert/strict';
import { appendPage, chosenIds, isFull, moveActive, parentPaths, pickTerm, termPath, termValue, withoutLast } from '../../packages/cms/resources/js/lib/term-picker.ts';
import { termPickerQuery } from '../../packages/cms/resources/js/lib/values.ts';

test('reads the value as a list of ids and back', () => {
    assert.deepEqual(chosenIds(null), []);
    assert.deepEqual(chosenIds(4), [4]);
    assert.deepEqual(chosenIds([4, 2]), [4, 2]);
    assert.deepEqual(termValue([4, 2], true), [4, 2]);
    assert.equal(termValue([4], false), 4);
    assert.equal(termValue([], false), null);
});

test('toggles terms, keeping the order they were chosen in', () => {
    assert.deepEqual(pickTerm([3, 1], 2, true, null), [3, 1, 2]);
    assert.deepEqual(pickTerm([3, 1, 2], 1, true, null), [3, 2]);
});

test('adds no term past max, but still takes one out', () => {
    assert.equal(isFull([1, 2], true, 2), true);
    assert.equal(isFull([1], true, 2), false);
    assert.equal(isFull([1, 2], true, null), false);
    assert.equal(isFull([1], false, 1), false);
    assert.deepEqual(pickTerm([1, 2], 3, true, 2), [1, 2]);
    assert.deepEqual(pickTerm([1, 2], 2, true, 2), [1]);
});

test('replaces the term when only one may be chosen', () => {
    assert.deepEqual(pickTerm([], 5, false, null), [5]);
    assert.deepEqual(pickTerm([3], 5, false, null), [5]);
    assert.deepEqual(pickTerm([5], 5, false, null), [5]);
});

test('removes the last chip', () => {
    assert.deepEqual(withoutLast([1, 2, 3]), [1, 2]);
    assert.deepEqual(withoutLast([]), []);
});

test('moves the highlight with the arrow keys, skipping disabled options', () => {
    assert.equal(moveActive(-1, 'ArrowDown', 3), 0);
    assert.equal(moveActive(0, 'ArrowDown', 3), 1);
    assert.equal(moveActive(2, 'ArrowDown', 3), 2);
    assert.equal(moveActive(1, 'ArrowUp', 3), 0);
    assert.equal(moveActive(0, 'ArrowUp', 3), 0);
    assert.equal(moveActive(-1, 'ArrowUp', 3), 2);
    assert.equal(moveActive(1, 'Home', 3), 0);
    assert.equal(moveActive(0, 'End', 3), 2);
    assert.equal(moveActive(-1, 'ArrowDown', 0), -1);
    assert.equal(moveActive(1, 'Enter', 3), 1);
    const disabled = (index: number) => index === 1;
    assert.equal(moveActive(0, 'ArrowDown', 3, disabled), 2);
    assert.equal(moveActive(2, 'ArrowUp', 3, disabled), 0);
    assert.equal(moveActive(0, 'ArrowDown', 2, disabled), 0);
});

test('puts pages of terms together, each once', () => {
    const a = { id: 1, label: 'A', depth: 0 };
    const b = { id: 2, label: 'B', depth: 0 };
    assert.deepEqual(appendPage([a], [a, b]), [a, b]);
});

test('reads parents off a list in tree order', () => {
    const items = [
        { id: 1, label: 'News', depth: 0 }, { id: 2, label: 'Local', depth: 1 }, { id: 3, label: 'Town', depth: 2 },
        { id: 4, label: 'World', depth: 1 }, { id: 5, label: 'Sport', depth: 0 },
    ];
    assert.deepEqual(parentPaths(items), { 1: [], 2: ['News'], 3: ['News', 'Local'], 4: ['News'], 5: [] });
    assert.equal(termPath('Local', ['News']), 'News › Local');
    assert.equal(termPath('Sport', undefined), 'Sport');
});

test('asks the picker for the next page', () => {
    assert.equal(termPickerQuery({ taxonomy: 'tags', offset: 0 }), 'taxonomy=tags');
    assert.equal(termPickerQuery({ taxonomy: 'tags', q: 'a', offset: 100 }), 'taxonomy=tags&q=a&offset=100');
});
