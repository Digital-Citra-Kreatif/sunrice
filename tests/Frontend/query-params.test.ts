import assert from 'node:assert/strict';
import { test } from 'node:test';
import { queryParams } from '../../packages/admin/resources/js/lib/query-params.ts';

test('leaves empty and default values out of the query string', () => {
    assert.deepEqual(queryParams({ search: '', source: null, action: 'deleted', perPage: 50 }, { perPage: 50 }, { perPage: 'per_page' }), { action: 'deleted' });
    assert.deepEqual(queryParams({ search: 'rina', perPage: 100, trash: true }, { perPage: 50 }, { perPage: 'per_page' }), { search: 'rina', per_page: 100, trash: 1 });
    assert.deepEqual(queryParams({ trash: false, page: undefined }), {});
});
