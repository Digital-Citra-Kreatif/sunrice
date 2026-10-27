import { test } from 'node:test';
import assert from 'node:assert/strict';
import { INLINE_FILTERS, menuFiltersSet, type EntryListQuery } from '../../packages/cms/resources/js/lib/entry-list.ts';

const query = (changes: Partial<EntryListQuery>): EntryListQuery => ({
    search: '', status: '', author: '', terms: {}, sort: '', locale: 'en', columns: [], perPage: 25, page: 1, ...changes,
} as EntryListQuery);

test('more than two of author and taxonomies go in the Filters menu', () => {
    assert.equal(INLINE_FILTERS, 2);
});

test('counts the Filters menu\'s filters that are set, not search or status', () => {
    assert.equal(menuFiltersSet(query({}), ['industries', 'regions']), 0);
    assert.equal(menuFiltersSet(query({ search: 'x', status: 'draft' }), ['industries']), 0);
    assert.equal(menuFiltersSet(query({ author: '3', terms: { industries: '5', regions: '' } }), ['industries', 'regions']), 2);
    // A term of a taxonomy the collection no longer has isn't counted.
    assert.equal(menuFiltersSet(query({ terms: { old: '1' } }), ['industries']), 0);
});
