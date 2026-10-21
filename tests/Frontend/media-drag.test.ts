import assert from 'node:assert/strict';
import { test } from 'node:test';
import { parseDraggedIds } from '../../packages/admin/resources/js/lib/media-drag.ts';

test('reads the dragged files\' ids, and nothing else', () => {
    assert.deepEqual(parseDraggedIds('[3, 7]'), [3, 7]);
    assert.deepEqual(parseDraggedIds('[3, "x", null]'), [3]);
    assert.deepEqual(parseDraggedIds(''), []);
    assert.deepEqual(parseDraggedIds('{"id": 3}'), []);
});
