import { test } from 'node:test';
import assert from 'node:assert/strict';
import { asRecord, dateInputValue, dateValueFromInput, newBlock, newRow } from '../../packages/cms/resources/js/lib/values.ts';

test('reads stored dates into date inputs', () => {
    assert.equal(dateInputValue('2026-10-19', false), '2026-10-19');
    assert.equal(dateInputValue('2026-10-19T14:30:00', false), '2026-10-19');
    assert.equal(dateInputValue('2026-10-19T14:30:00', true), '2026-10-19T14:30');
    assert.equal(dateInputValue('2026-10-19', true), '2026-10-19T00:00');
    assert.equal(dateInputValue('soon', true), '');
    assert.equal(dateInputValue(null, false), '');
});

test('sends emptied dates as null', () => {
    assert.equal(dateValueFromInput(''), null);
    assert.equal(dateValueFromInput('2026-10-19T14:30'), '2026-10-19T14:30');
});

test('treats anything but an object as an empty record', () => {
    assert.deepEqual(asRecord(null), {});
    assert.deepEqual(asRecord(['a']), {});
    assert.deepEqual(asRecord({ a: 1 }), { a: 1 });
});

test('makes new rows and blocks shown', () => {
    assert.deepEqual(newRow(), { _hidden: false });
    assert.deepEqual(newBlock('quote'), { type: 'quote', hidden: false, values: {} });
});
