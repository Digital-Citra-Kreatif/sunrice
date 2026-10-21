import assert from 'node:assert/strict';
import { test } from 'node:test';
import { translationProgress } from '../../packages/cms/resources/js/lib/translations.ts';

test('counts the translated texts, blanks as not translated', () => {
    assert.deepEqual(translationProgress(['Tim', '', '  ', 'Kontak']), { done: 2, total: 4 });
    assert.deepEqual(translationProgress([]), { done: 0, total: 0 });
});
