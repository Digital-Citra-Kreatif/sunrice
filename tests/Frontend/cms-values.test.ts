import { test } from 'node:test';
import assert from 'node:assert/strict';
import { asRecord, dateInputValue, decodeEntities, dateValueFromInput, groupedNumber, hasSharedFields, itemSummary, offersEmptyChoice, newBlock, newRow } from '../../packages/cms/resources/js/lib/values.ts';

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

test('starts scheduling on the date to come, else tomorrow, never an old publish date', async () => {
    const { scheduleStart, formatDate } = await import('../../packages/cms/resources/js/lib/entries.ts');
    const now = new Date(2026, 9, 21, 14, 37);

    assert.deepEqual(scheduleStart('2026-09-20T10:00:00Z', now), new Date(2026, 9, 22, 14, 0));
    assert.deepEqual(scheduleStart(null, now), new Date(2026, 9, 22, 14, 0));
    assert.deepEqual(scheduleStart(new Date(2026, 10, 1, 9, 30).toISOString(), now), new Date(2026, 10, 1, 9, 30));
    assert.match(formatDate(new Date(2026, 9, 21, 17, 12).toISOString(), 'en-US'), /17:12/);
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

test('reads long numbers back grouped', () => {
    assert.equal(groupedNumber(18500000000), '18,500,000,000');
    assert.equal(groupedNumber('95000000.5'), '95,000,000.5');
    assert.equal(groupedNumber(-12345), '-12,345');
    assert.equal(groupedNumber(9999), null);
    assert.equal(groupedNumber(null), null);
    assert.equal(groupedNumber('abc'), null);
});

test('offers a select\'s own "None" only where it means something', () => {
    const plain = [{ value: 'available', label: 'Available' }, { value: 'sold', label: 'Sold' }];
    assert.equal(offersEmptyChoice(plain, false, false, true), true);
    assert.equal(offersEmptyChoice(plain, true, false, false), false, 'required');
    assert.equal(offersEmptyChoice([{ value: 'none', label: 'None' }, { value: 'per_month', label: 'per month' }], false, false, false), false, 'its own None');
    assert.equal(offersEmptyChoice(plain, false, true, true), false, 'beside a default');
    assert.equal(offersEmptyChoice(plain, false, true, false), true, 'an empty older value stays choosable');
});

test('lets a slug made from the title follow it until the entry is first published', async () => {
    const { followingSlug, slugFrom } = await import('../../packages/cms/resources/js/lib/entries.ts');
    assert.equal(slugFrom('Villa Arunika, Pererenan (copy)'), 'villa-arunika-pererenan-copy');
    assert.equal(slugFrom('Café Ümit'), 'cafe-umit');
    assert.equal(followingSlug('villa-arunika-pererenan-copy', 'Villa Arunika, Pererenan (copy)', 'Villa Senja', true), 'villa-senja');
    assert.equal(followingSlug('villa-arunika-pererenan-copy', 'Villa Arunika, Pererenan (copy)', 'Villa Senja', false), 'villa-arunika-pererenan-copy', 'published once');
    assert.equal(followingSlug('my-own', 'Villa', 'Villa Senja', true), 'my-own', 'typed by hand');
    assert.equal(followingSlug('villa-copy-2', 'Villa (copy)', 'Villa Senja', true), 'villa-senja', 'a second copy');
    assert.equal(followingSlug('villa-copy-two', 'Villa (copy)', 'Villa Senja', true), 'villa-copy-two', 'not a number after it');
});

test('notes a new slug only for an address visitors have known: redirected when published, else a 404', async () => {
    const { slugNote } = await import('../../packages/cms/resources/js/lib/entries.ts');
    assert.equal(slugNote('hello', 'hello', true), null);
    assert.equal(slugNote('hello', 'hello-world', true), 'redirects');
    assert.equal(slugNote('hello', 'hello-world', false), 'breaks', 'published once, a draft again');
    assert.equal(slugNote(null, 'hello-world', false), null, 'never published');
    assert.equal(slugNote('hello', '  ', true), null, 'empty: made from the title');
});

test('finds fields shared with the main language at any depth', () => {
    assert.equal(hasSharedFields([{ fields: [{}] }]), false);
    assert.equal(hasSharedFields([{ shared: true }]), true);
    assert.equal(hasSharedFields([{ fields: [{ fields: [{ shared: true }] }] }]), true);
    assert.equal(hasSharedFields([{ blocks: [{ fields: [{ shared: true }] }] }]), true);
});

test('names languages as the site does, else in the reader\'s language', async () => {
    const { localeName, setLocaleNames } = await import('../../packages/cms/resources/js/lib/entries.ts');

    setLocaleNames({ id: 'Bahasa Indonesia' });
    assert.equal(localeName('id'), 'Bahasa Indonesia');
    // Asked in a language, or a code the site doesn't name: as Intl says it.
    assert.equal(localeName('id', 'en'), 'Indonesian');
    assert.equal(localeName('xx'), new Intl.DisplayNames(undefined, { type: 'language' }).of('xx') ?? 'xx');
    setLocaleNames(null);
    assert.equal(localeName('id'), new Intl.DisplayNames(undefined, { type: 'language' }).of('id'));
});

test('publishes by the picked Publish date from either button: scheduled, back-dated or now', async () => {
    const { datedPublish } = await import('../../packages/cms/resources/js/lib/entries.ts');
    const now = new Date('2026-10-21T12:00:00Z').getTime();
    assert.deepEqual(datedPublish('draft', '', null, now), { action: 'now', at: null });
    assert.deepEqual(datedPublish('draft', '2026-11-20T11:00:00Z', null, now), { action: 'schedule', at: '2026-11-20T11:00:00.000Z' });
    assert.deepEqual(datedPublish('draft', '2026-09-01T09:00:00Z', null, now), { action: 'backdate', at: '2026-09-01T09:00:00.000Z' });
    assert.deepEqual(datedPublish('published', '2026-09-01T09:00:00Z', '2026-09-01T09:00:30Z', now), { action: 'now', at: null }, 'the saved date, to the minute');
    assert.deepEqual(datedPublish('published', '2026-08-01T09:00:00Z', '2026-09-01T09:00:00Z', now), { action: 'redate', at: '2026-08-01T09:00:00.000Z' });
    assert.equal(datedPublish('scheduled', '2026-12-01T09:00:00Z', '2026-11-20T11:00:00Z', now).action, 'schedule');
    // Taken off the site: its own past date publishes it again as it was, not "back-dated"; a date to come still schedules it.
    assert.deepEqual(datedPublish('draft', '2026-09-01T09:00:00Z', '2026-09-01T09:00:00Z', now), { action: 'now', at: null });
    assert.equal(datedPublish('draft', '2026-11-20T11:00:00Z', '2026-11-20T11:00:00Z', now).action, 'schedule');
});

test('reads a rich text block\'s summary as text, its entities decoded', () => {
    const fields = [{ handle: 'body', type: 'rich_text' }];
    assert.equal(itemSummary(fields, { body: '<p>The story of What&#039;s next &amp; more.</p>' }), 'The story of What\'s next & more.');
    // Decoded markup stays text, and isn't decoded twice.
    assert.equal(itemSummary(fields, { body: '<p>&lt;script&gt;alert(1)&lt;/script&gt; &amp;amp;</p>' }), '<script>alert(1)</script> &amp;');
    assert.equal(decodeEntities('&#x27;a&#39; &nbsp;b &bogus; &#0; &#xD800;'), '\'a\'  b &bogus; &#0; &#xD800;');
});
