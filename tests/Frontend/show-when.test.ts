import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isShownBy } from '../../packages/admin/resources/js/lib/show-when.ts';

test('shows a field while another is filled in, or holds a value', () => {
    assert.equal(isShownBy(null, {}), true);
    assert.equal(isShownBy({ field: 'route', value: null }, { route: '/blog/{slug}' }), true);
    for (const empty of ['', null, false, [], undefined]) {
        assert.equal(isShownBy({ field: 'route', value: null }, { route: empty }), false);
    }
    assert.equal(isShownBy({ field: 'kind', value: 'link' }, { kind: 'link' }), true);
    assert.equal(isShownBy({ field: 'kind', value: 'link' }, { kind: 'page' }), false);
    assert.equal(isShownBy({ field: 'tags', value: 3 }, { tags: ['1', '3'] }), true);
    assert.equal(isShownBy({ field: 'on', value: true }, { on: true }), true);
});
