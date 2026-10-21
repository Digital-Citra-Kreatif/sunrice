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

test('shows a field once another one differs from its saved value, on edit forms only', () => {
    const condition = { field: 'collections', value: null, changed: true };

    assert.equal(isShownBy(condition, { collections: ['1', '2'] }, { collections: ['2', '1'] }), false);
    assert.equal(isShownBy(condition, { collections: ['1'] }, { collections: ['1', '2'] }), true);
    assert.equal(isShownBy(condition, { collections: ['1'] }), false);
});
