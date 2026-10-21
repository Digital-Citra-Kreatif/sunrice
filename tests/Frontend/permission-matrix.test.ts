import assert from 'node:assert/strict';
import { test } from 'node:test';
import { matrixColumns, matrixRowName, matrixRows, selectionState } from '../../packages/admin/resources/js/lib/permission-matrix.ts';

const options = [
    { value: 'cms.entries.view', label: 'View entries' },
    { value: 'cms.entries.update', label: 'Edit drafts' },
    { value: 'cms.entries.update-own', label: 'Edit drafts of their own entries' },
    { value: 'cms.entries.delete-own', label: 'Trash their own entries' },
    { value: 'cms.entries.translate', label: 'Translate entries' },
    { value: 'cms.comments.moderate', label: 'Approve comments' },
    { value: 'cms.homepage', label: 'Choose the homepage' },
];

test('puts permissions in rows of things by action, own variants in their cell', () => {
    const rows = matrixRows(options);
    assert.deepEqual(rows.map((row) => row.key), ['cms.entries', 'cms.comments', 'cms']);
    const entries = rows[0]!;
    assert.equal(entries.cells.view.option?.value, 'cms.entries.view');
    assert.equal(entries.cells.update.own?.value, 'cms.entries.update-own');
    // An own variant without its action is listed with the others, as is an action without a column.
    assert.deepEqual(entries.other.map((o) => o.value), ['cms.entries.translate', 'cms.entries.delete-own']);
    assert.equal(rows[1]!.cells.moderate.option?.value, 'cms.comments.moderate');
    assert.deepEqual(rows[2]!.other.map((o) => o.value), ['cms.homepage']);
    assert.deepEqual(matrixColumns(rows), ['view', 'update', 'moderate']);
});

test('names rows and tells a select-all box its state', () => {
    assert.equal(matrixRowName('cms.entries'), 'Entries');
    assert.equal(matrixRowName('cms.submissions.contact_form'), 'Contact form');
    assert.equal(matrixRowName('cms', 'General'), 'General');
    assert.equal(selectionState(['a', 'b'], []), false);
    assert.equal(selectionState(['a', 'b'], ['a']), 'indeterminate');
    assert.equal(selectionState(['a', 'b'], ['b', 'a', 'c']), true);
});
