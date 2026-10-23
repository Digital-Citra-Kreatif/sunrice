import assert from 'node:assert/strict';
import { test } from 'node:test';
import { errorsUnder } from '../../packages/cms/resources/js/lib/nested-errors.ts';

test('gives the structured data fields their errors by field name', () => {
    assert.deepEqual(errorsUnder({
        'structured.publisherType': 'Not a type.',
        'structured.sameAs.0': 'Not an address.',
        'site.twitter': 'Not a handle.',
        structuredData: 'Elsewhere.',
        'structured.telephone': undefined,
    }, 'structured'), { publisherType: 'Not a type.', 'sameAs.0': 'Not an address.' });
    assert.deepEqual(errorsUnder({}, 'structured'), {});
});
