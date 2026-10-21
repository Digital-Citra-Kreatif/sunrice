import { test } from 'node:test';
import assert from 'node:assert/strict';
import { asRecord, dateInputValue, dateValueFromInput, itemSummary, newBlock, newRow } from '../../packages/cms/resources/js/lib/values.ts';

test('reads stored dates into date inputs', () => {
    assert.equal(dateInputValue('2026-10-19', false), '2026-10-19');
    assert.equal(dateInputValue('2026-10-19T14:30:00', false), '2026-10-19');
    assert.equal(dateInputValue('2026-10-19T14:30:00', true), '2026-10-19T14:30');
    assert.equal(dateInputValue('2026-10-19', true), '2026-10-19T00:00');
    assert.equal(dateInputValue('soon', true), '');
    assert.equal(dateInputValue(null, false), '');
});

test('sends emptied dates as null', () => {
    assert.equal(dateValueFromInput(''), null);
    assert.equal(dateValueFromInput('2026-10-19T14:30'), '2026-10-19T14:30');
});

test('treats anything but an object as an empty record', () => {
    assert.deepEqual(asRecord(null), {});
    assert.deepEqual(asRecord(['a']), {});
    assert.deepEqual(asRecord({ a: 1 }), { a: 1 });
});

test('makes new rows and blocks shown', () => {
    assert.deepEqual(newRow(), { _hidden: false });
    assert.deepEqual(newBlock('quote'), { type: 'quote', hidden: false, values: {} });
});

test('checks link addresses like the server', async () => {
    const { isSafeUrl } = await import('../../packages/cms/resources/js/lib/values.ts');

    for (const url of ['https://example.com', '/about', '#top', '?page=2', 'mailto:hi@example.com', 'tel:+62123', 'about-us']) {
        assert.equal(isSafeUrl(url), true, url);
    }
    for (const url of ['javascript:alert(1)', ' JavaScript:alert(1)', 'java\tscript:alert(1)', 'data:text/html,x', 'vbscript:x', '/\\evil.com']) {
        assert.equal(isSafeUrl(url), false, url);
    }
});

test('names languages', async () => {
    const { localeName } = await import('../../packages/cms/resources/js/lib/entries.ts');

    assert.equal(localeName('id', 'en'), 'Indonesian');
    assert.equal(localeName('pt_BR', 'en'), 'Brazilian Portuguese');
    assert.equal(localeName('not a code!', 'en'), 'not a code!');
});

test('builds the entry picker\'s query', async () => {
    const { pickerQuery } = await import('../../packages/cms/resources/js/lib/values.ts');

    assert.equal(pickerQuery({ ids: [3, 1], collections: ['pages'] }), 'ids%5B%5D=3&ids%5B%5D=1&collections%5B%5D=pages');
    assert.equal(pickerQuery({ search: 'a&b', collections: [] }), 'search=a%26b');
});

test('names a row or block by its first filled text', () => {
    const fields = [
        { handle: 'icon', type: 'media' },
        { handle: 'title', type: 'text' },
        { handle: 'body', type: 'rich_text' },
    ];
    assert.equal(itemSummary(fields, { icon: 4, title: '  15 m saltwater   pool ' }), '15 m saltwater pool');
    assert.equal(itemSummary(fields, { title: '', body: '<p>Homes of <strong>quiet</strong> distinction</p>' }), 'Homes of quiet distinction');
    assert.equal(itemSummary(fields, { title: 'x'.repeat(80) }, 20), `${'x'.repeat(19)}…`);
    assert.equal(itemSummary([{ handle: 'hero', type: 'group', fields: [{ handle: 'heading', type: 'text' }] }], { hero: { heading: 'Welcome' } }), 'Welcome');
    assert.equal(itemSummary(fields, {}), null);
});
