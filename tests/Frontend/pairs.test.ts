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

test('leaves out of each row\'s list the keys the other rows use', async () => {
    const { keysTaken, sameKey, suggestionsFor } = await import('../../packages/admin/resources/js/lib/pairs.ts');
    const suggestions = [{ key: 'en', value: 'English' }, { key: 'id', value: 'Bahasa Indonesia' }, { key: 'ms', value: 'Bahasa Melayu' }, { key: 'pt_BR', value: 'Português (Brasil)' }];
    const rows = [{ key: 'en', value: 'English' }, { key: ' id ', value: 'Bahasa Indonesia' }, { key: '', value: '' }];
    // Its own key stays (listed and ticked); empty rows take nothing.
    assert.deepEqual(keysTaken(rows, 0), ['id']);
    assert.deepEqual(suggestionsFor(suggestions, keysTaken(rows, 0)).map((s) => s.key), ['en', 'ms', 'pt_BR']);
    // The new row ("Add a language"): neither used code.
    assert.deepEqual(suggestionsFor(suggestions, keysTaken(rows, 2)).map((s) => s.key), ['ms', 'pt_BR']);
    // Compared ignoring case.
    assert.deepEqual(suggestionsFor(suggestions, ['PT_br', 'EN']).map((s) => s.key), ['id', 'ms']);
    assert.ok(sameKey(' pt_BR', 'pt_br'));
    assert.ok(!sameKey('pt_BR', 'pt-BR'), 'different codes to the CMS');
});
