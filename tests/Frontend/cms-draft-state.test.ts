import assert from 'node:assert/strict';
import { test } from 'node:test';

const entry = (status: 'draft' | 'scheduled' | 'published', hasUnpublishedChanges: boolean, extra: { trashed?: boolean; everPublished?: boolean } = {}) =>
    ({ status, hasUnpublishedChanges, trashed: extra.trashed ?? false, everPublished: extra.everPublished ?? status !== 'draft' });

test('tells the live version from unpublished changes', async () => {
    const { editorState, hasPendingChanges } = await import('../../packages/cms/resources/js/lib/entries.ts');

    assert.equal(editorState(entry('draft', true, { everPublished: false })), 'draft');
    assert.equal(editorState(entry('draft', true, { everPublished: true })), 'offline');
    assert.equal(editorState(entry('scheduled', false)), 'scheduled');
    assert.equal(editorState(entry('scheduled', true)), 'scheduled-changes');
    assert.equal(editorState(entry('published', false)), 'live');
    assert.equal(editorState(entry('published', true)), 'live-changes');
    assert.equal(editorState(entry('published', true, { trashed: true })), 'trashed');
    assert.deepEqual(['draft', 'offline', 'scheduled', 'scheduled-changes', 'live', 'live-changes', 'trashed'].filter((state) => hasPendingChanges(state as never)), ['scheduled-changes', 'live-changes']);
});

test('offers the live page, Compare, Preview and Discard only where they mean something', async () => {
    const { statusActions } = await import('../../packages/cms/resources/js/lib/entries.ts');
    const options = { liveUrl: true, pages: true, canUpdate: true };

    assert.deepEqual(statusActions('live-changes', options), { viewLive: true, compare: true, preview: true, discard: true });
    assert.deepEqual(statusActions('live', options), { viewLive: true, compare: false, preview: false, discard: false });
    assert.deepEqual(statusActions('draft', options), { viewLive: false, compare: false, preview: true, discard: false });
    assert.deepEqual(statusActions('scheduled', options), { viewLive: false, compare: false, preview: true, discard: false });
    assert.deepEqual(statusActions('scheduled-changes', options), { viewLive: false, compare: true, preview: true, discard: true });
    assert.deepEqual(statusActions('trashed', options), { viewLive: false, compare: false, preview: false, discard: false });
    // Those who may not edit can still look; a collection without pages has nothing to preview or visit.
    assert.deepEqual(statusActions('live-changes', { ...options, canUpdate: false }), { viewLive: true, compare: true, preview: true, discard: false });
    assert.deepEqual(statusActions('live-changes', { liveUrl: false, pages: false, canUpdate: true }), { viewLive: false, compare: true, preview: false, discard: true });
});

test('lists say Changes pending only beside a published entry', async () => {
    const { changesPending } = await import('../../packages/cms/resources/js/lib/entries.ts');

    assert.equal(changesPending({ status: 'published', changes: true }), true);
    assert.equal(changesPending({ status: 'scheduled', changes: true }), true);
    assert.equal(changesPending({ status: 'draft', changes: true }), false);
    assert.equal(changesPending({ status: 'published' }), false);
});

test('compares texts word by word, keeping each side whole', async () => {
    const { wordDiff, diffSide } = await import('../../packages/cms/resources/js/lib/diff.ts');
    const join = (parts: { text: string }[]) => parts.map((part) => part.text).join('');

    const parts = wordDiff('The quick brown fox', 'The slow brown fox jumps');
    assert.deepEqual(parts, [
        { text: 'The ', op: 'same' }, { text: 'quick ', op: 'removed' }, { text: 'slow ', op: 'added' }, { text: 'brown fox ', op: 'same' }, { text: 'jumps', op: 'added' },
    ]);
    assert.equal(join(diffSide(parts, 'new')), 'The slow brown fox jumps');
    assert.ok(diffSide(parts, 'old').some((part) => part.op === 'removed' && part.text.trim() === 'quick'));
    assert.ok(diffSide(parts, 'new').some((part) => part.op === 'added' && part.text.includes('slow')));
    assert.ok(diffSide(parts, 'new').every((part) => part.op !== 'removed'));

    assert.deepEqual(wordDiff('', ''), []);
    assert.deepEqual(wordDiff('Same', 'Same'), [{ text: 'Same', op: 'same' }]);
    assert.deepEqual(wordDiff('', 'New'), [{ text: 'New', op: 'added' }]);
    assert.deepEqual(wordDiff('Old', ''), [{ text: 'Old', op: 'removed' }]);
    // Line breaks are their own words.
    assert.equal(join(diffSide(wordDiff('One\nTwo', 'One\nThree'), 'new')), 'One\nThree');
    // Too long to compare word by word: the whole values.
    const long = Array.from({ length: 600 }, (_, i) => `w${i}`).join(' ');
    assert.deepEqual(wordDiff(long, `${long} more`).map((part) => part.op), ['removed', 'added']);
});
