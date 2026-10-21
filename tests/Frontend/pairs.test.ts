import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatPairs, parsePairs } from '../../packages/admin/resources/js/lib/pairs.ts';

test('reads and writes "key: value" lines as rows', () => {
    assert.deepEqual(parsePairs('en: English\n\nid:Bahasa Indonesia\nfr'), [
        { key: 'en', value: 'English' }, { key: 'id', value: 'Bahasa Indonesia' }, { key: 'fr', value: '' },
    ]);
    assert.equal(formatPairs([{ key: ' id ', value: 'Bahasa Indonesia' }, { key: '', value: '' }, { key: 'fr', value: '' }]), 'id: Bahasa Indonesia\nfr');
    assert.equal(formatPairs(parsePairs('en: English: UK')), 'en: English: UK');
    assert.deepEqual(parsePairs('id: Bahasa Indonesia | indonesia\nen: English |'), [{ key: 'id', value: 'Bahasa Indonesia', extra: 'indonesia' }, { key: 'en', value: 'English' }]);
    assert.equal(formatPairs([{ key: 'en', value: 'English', extra: ' en ' }, { key: 'id', value: 'Indonesian', extra: '' }]), 'en: English | en\nid: Indonesian');
});

test('reads and writes the values of fixed keys, keeping the other lines', async () => {
    const { fixedValues, withFixedValue } = await import('../../packages/admin/resources/js/lib/pairs.ts');
    assert.deepEqual(fixedValues('id: Rumah premium | di Bali\nfr: Maisons\nxx', ['id', 'de']), { id: 'Rumah premium | di Bali', de: '' });
    // Typing: a trailing space stays, so words can be typed.
    assert.equal(fixedValues(withFixedValue('', 'id', 'Rumah '), ['id']).id, 'Rumah ');
    assert.equal(withFixedValue('fr: Maisons\nid: Lama', 'id', 'Baru\nsekali'), 'fr: Maisons\nid: Baru sekali');
    assert.equal(withFixedValue('fr: Maisons\nid: Lama', 'id', ''), 'fr: Maisons');
    assert.equal(withFixedValue('fr: Maisons', 'de', 'Häuser'), 'fr: Maisons\nde: Häuser');
});
