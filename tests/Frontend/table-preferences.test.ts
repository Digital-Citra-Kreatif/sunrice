import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isPreferenceKey, normalizeTablePreferences, toggleColumn } from '../../packages/admin/resources/js/lib/table-preferences.ts';
import {
    activeFilters, type EntryListQuery, entryListParams, entryListQueryString, nextSort, sortDirection,
} from '../../packages/cms/resources/js/lib/entry-list.ts';

test('reads saved table preferences, whatever was stored', () => {
    assert.deepEqual(normalizeTablePreferences({ columns: ['a', 3, 'b'], perPage: 50 }), { columns: ['a', 'b'], perPage: 50 });
    assert.deepEqual(normalizeTablePreferences({ perPage: 0 }), { columns: null, perPage: null });
    assert.deepEqual(normalizeTablePreferences(null), { columns: null, perPage: null });
    assert.deepEqual(normalizeTablePreferences(['a']), { columns: null, perPage: null });
});

test('shows and hides a column, keeping the table\'s order', () => {
    const all = ['title', 'status', 'author', 'updated_at'];
    assert.deepEqual(toggleColumn(['updated_at', 'title'], 'status', all), ['title', 'status', 'updated_at']);
    assert.deepEqual(toggleColumn(['title', 'status'], 'status', all), ['title']);
    assert.deepEqual(toggleColumn(['title'], 'gone', all), ['title']);
});

test('accepts the preference keys the server accepts', () => {
    assert.equal(isPreferenceKey('table.cms.entries.blog'), true);
    assert.equal(isPreferenceKey('../etc'), false);
    assert.equal(isPreferenceKey(''), false);
    assert.equal(isPreferenceKey('a'.repeat(101)), false);
});

const query: EntryListQuery = { search: '', status: '', author: '', terms: {}, locale: 'en', sort: '', perPage: 25, columns: ['status'] };

test('puts only what differs from the default in the entries list\'s URL', () => {
    assert.deepEqual(entryListParams(query, {}, 'en'), { per_page: 25 });
    assert.deepEqual(entryListParams(query, { search: 'tea', status: 'draft', author: '7', locale: 'id', sort: '-field.price', perPage: 50 }, 'en'),
        { search: 'tea', status: 'draft', author: '7', locale: 'id', sort: '-field.price', per_page: 50 });
    assert.deepEqual(entryListParams({ ...query, terms: { topics: '3', tags: '' } }, {}, 'en'), { terms: { topics: '3' }, per_page: 25 });
    assert.deepEqual(entryListParams({ ...query, status: 'trashed' }, { status: '' }, 'en'), { per_page: 25 });
});

test('writes the list\'s parameters as a query string for the export link', () => {
    assert.equal(entryListQueryString({ search: 'a b', terms: { topics: '3' }, per_page: 25 }), 'search=a+b&terms%5Btopics%5D=3&per_page=25');
    assert.equal(entryListQueryString({}), '');
});

test('cycles a column\'s sort: ascending, descending, the collection\'s order', () => {
    assert.equal(nextSort('', 'title'), 'title');
    assert.equal(nextSort('title', 'title'), '-title');
    assert.equal(nextSort('-title', 'title'), '');
    assert.equal(nextSort('-title', 'field.price'), 'field.price');
    assert.equal(sortDirection('-title', 'title'), 'desc');
    assert.equal(sortDirection('title', 'title'), 'asc');
    assert.equal(sortDirection('slug', 'title'), null);
});

test('counts the filters that narrow the list', () => {
    assert.equal(activeFilters(query), 0);
    assert.equal(activeFilters({ ...query, locale: 'id' }), 0);
    assert.equal(activeFilters({ ...query, search: 'x', status: 'draft', terms: { topics: '1', tags: '' } }), 3);
});
