import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatPairs, parsePairs } from '../../packages/admin/resources/js/lib/pairs.ts';

test('reads and writes "key: value" lines as rows', () => {
    assert.deepEqual(parsePairs('en: English\n\nid:Bahasa Indonesia\nfr'), [
        { key: 'en', value: 'English' }, { key: 'id', value: 'Bahasa Indonesia' }, { key: 'fr', value: '' },
    ]);
    assert.equal(formatPairs([{ key: ' id ', value: 'Bahasa Indonesia' }, { key: '', value: '' }, { key: 'fr', value: '' }]), 'id: Bahasa Indonesia\nfr');
    assert.equal(formatPairs(parsePairs('en: English: UK')), 'en: English: UK');
});
