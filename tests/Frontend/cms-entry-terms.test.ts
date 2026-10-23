import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sameTerms, termsToSend } from '../../packages/cms/resources/js/lib/entry-terms.ts';

test('compares terms whatever their order', () => {
    assert.equal(sameTerms([1, 2], [2, 1]), true);
    assert.equal(sameTerms([], []), true);
    assert.equal(sameTerms([1], [1, 2]), false);
    assert.equal(sameTerms([1, 3], [1, 2]), false);
});

test('sends nothing when no taxonomy\'s terms changed, so another language\'s newer terms stay', () => {
    assert.equal(termsToSend({ tags: [1, 2], format: [] }, { tags: [2, 1], format: [] }), null);
    assert.equal(termsToSend({}, { tags: [] }), null);
});

test('sends only the taxonomies changed, with the terms loaded for them', () => {
    assert.deepEqual(termsToSend({ tags: [1], format: [5] }, { tags: [1, 2], format: [5] }), { terms: { tags: [1, 2] }, terms_base: { tags: [1] } });
    // Emptied, and a taxonomy the editor had nothing for.
    assert.deepEqual(termsToSend({ tags: [1] }, { tags: [], format: [7] }), { terms: { tags: [], format: [7] }, terms_base: { tags: [1], format: [] } });
});
