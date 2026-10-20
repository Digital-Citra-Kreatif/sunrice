import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isShown } from '../../packages/cms/resources/js/lib/conditions.ts';

const field = (operator: 'equals' | 'not_equals' | 'filled' | 'empty', value: unknown = null) =>
    ({ handle: 'video', type: 'text', condition: { field: 'layout', operator, value } }) as never;

test('shows fields without a condition', () => {
    assert.equal(isShown({ handle: 'title', type: 'text' }, {}), true);
});

test('compares with the other field, as the server does', () => {
    assert.equal(isShown(field('equals', 'video'), { layout: 'video' }), true);
    assert.equal(isShown(field('equals', 'video'), { layout: 'image' }), false);
    assert.equal(isShown(field('equals', 'video'), { layout: ['image', 'video'] }), true);
    assert.equal(isShown(field('not_equals', 'video'), { layout: 'image' }), true);
    assert.equal(isShown(field('equals', 'true'), { layout: true }), true);
    assert.equal(isShown(field('equals', 3), { layout: '3' }), true);
});

test('knows filled from empty', () => {
    for (const empty of [null, undefined, '', false, []]) {
        assert.equal(isShown(field('filled'), { layout: empty }), false);
        assert.equal(isShown(field('empty'), { layout: empty }), true);
    }
    assert.equal(isShown(field('filled'), { layout: 'x' }), true);
    assert.equal(isShown(field('filled'), { layout: 0 }), true);
});
