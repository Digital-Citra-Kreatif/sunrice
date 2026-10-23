import { test } from 'node:test';
import assert from 'node:assert/strict';
import { FIELD_WIDTHS, fieldGrid, fieldWidth, widthClass, widthSpan, withWidth } from '../../packages/cms/resources/js/lib/width.ts';

test('offers the widths the server allows', () => {
    assert.deepEqual([...FIELD_WIDTHS], [25, 33, 50, 66, 75, 100]);
});

test('reads a field\'s width, 100 when it has none or an unknown one', () => {
    assert.equal(fieldWidth({}), 100);
    assert.equal(fieldWidth({ width: 50 }), 50);
    assert.equal(fieldWidth({ width: 33 }), 33);
    assert.equal(fieldWidth({ width: 40 }), 100);
    assert.equal(fieldWidth({ width: '50' }), 100);
    assert.equal(fieldWidth({ width: null }), 100);
});

test('maps widths to spans of a 12-column grid that fill rows exactly', () => {
    assert.deepEqual(FIELD_WIDTHS.map(widthSpan), [3, 4, 6, 8, 9, 12]);
    assert.equal(widthSpan(33) + widthSpan(66), 12);
    assert.equal(widthSpan(25) * 4, 12);
    assert.equal(widthSpan(50) * 2, 12);
    assert.equal(widthSpan(75) + widthSpan(25), 12);
});

test('every field is full width in a narrow row, its own width from 32rem', () => {
    assert.equal(widthClass({}), 'cms:col-span-12 cms:min-w-0');
    assert.equal(widthClass({ width: 50 }), 'cms:col-span-12 cms:min-w-0 cms:@lg:col-span-6');
    assert.equal(widthClass({ width: 33 }), 'cms:col-span-12 cms:min-w-0 cms:@lg:col-span-4');
    assert.equal(widthClass({ width: 66 }), 'cms:col-span-12 cms:min-w-0 cms:@lg:col-span-8');
    assert.equal(widthClass({ width: 25 }), 'cms:col-span-12 cms:min-w-0 cms:@lg:col-span-3');
    assert.equal(widthClass({ width: 75 }), 'cms:col-span-12 cms:min-w-0 cms:@lg:col-span-9');
    assert.match(fieldGrid(), /cms:@container/);
    assert.match(fieldGrid(), /cms:grid-cols-12/);
    assert.match(fieldGrid(), /cms:items-start/);
});

test('sets a width, leaving 100 out as the server stores it', () => {
    const field = { handle: 'price', type: 'number', width: 50 };
    assert.deepEqual(withWidth(field, 25), { handle: 'price', type: 'number', width: 25 });
    assert.deepEqual(withWidth(field, 100), { handle: 'price', type: 'number' });
    assert.equal('width' in withWidth(field, 100), false);
    assert.deepEqual(field, { handle: 'price', type: 'number', width: 50 });
});
