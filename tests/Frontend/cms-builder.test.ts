import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    errorsAt, errorsUnder, fromDefinitions, handleFromLabel, handleInput, handleWarning, hasErrors, move, newField, relabel, toDefinitions, uniqueHandle,
} from '../../packages/cms/resources/js/lib/builder.ts';

const children = (type: string) => ['group', 'repeater'].includes(type);

test('suggests handles from labels', () => {
    assert.equal(handleFromLabel('Hero image'), 'hero_image');
    assert.equal(handleFromLabel('  Café — menu! '), 'cafe_menu');
    assert.equal(handleFromLabel('2nd title'), 'nd_title');
    assert.equal(handleFromLabel('!!!'), '');
    assert.equal(handleFromLabel('x'.repeat(80)).length, 64);
});

test('keeps handles unique on a level', () => {
    assert.equal(uniqueHandle('Text', []), 'text');
    assert.equal(uniqueHandle('Text', [{ handle: 'text' }, { handle: 'text_2' }]), 'text_3');
    assert.equal(uniqueHandle('???', []), 'field');
});

test('changes the handle with the label until one is typed', () => {
    const field = newField('text', 'Title', []);
    const renamed = relabel(field, 'Page title', [field]);
    assert.equal(renamed.handle, 'page_title');

    const typed = { ...renamed, handle: 'heading', handleTouched: true };
    assert.equal(relabel(typed, 'Something else', [typed]).handle, 'heading');
});

test('doesn\'t count the field itself as a sibling when relabelling', () => {
    const field = newField('text', 'Title', []);
    assert.equal(relabel(field, 'Title', [field]).handle, 'title');
});

test('moves fields up and down, ignoring moves off the ends', () => {
    assert.deepEqual(move(['a', 'b', 'c'], 0, 1), ['b', 'a', 'c']);
    assert.deepEqual(move(['a', 'b', 'c'], 2, -1), ['a', 'c', 'b']);
    const items = ['a', 'b'];
    assert.equal(move(items, 0, -1), items);
    assert.equal(move(items, 1, 1), items);
});

test('round-trips definitions, dropping the builder\'s keys and stray children', () => {
    const definitions = [
        { handle: 'hero', type: 'group', label: 'Hero', fields: [{ handle: 'heading', type: 'text', validation: [' min:3 ', ''] }] },
        { handle: 'body', type: 'textarea', fields: [{ handle: 'stray', type: 'text' }] },
    ];
    const fields = fromDefinitions(definitions);

    assert.ok(fields[0]?.key);
    assert.deepEqual(toDefinitions(fields, children), [
        { handle: 'hero', type: 'group', label: 'Hero', validation: [], fields: [{ handle: 'heading', type: 'text', validation: ['min:3'] }] },
        { handle: 'body', type: 'textarea', validation: [] },
    ]);
});

test('finds errors under a field', () => {
    const errors = { 'fields.0.handle': 'Taken.', 'fields.0.fields.1.type': 'Bad.', 'fields.10.label': 'Long.', title: 'Required.' };

    assert.deepEqual(errorsUnder(errors, 'fields.0'), { handle: 'Taken.', 'fields.1.type': 'Bad.' });
    assert.equal(hasErrors(errors, 'fields.0.fields'), true);
    assert.equal(hasErrors(errors, 'fields.1'), false);
    assert.equal(hasErrors(errors, 'title'), true);
});

test('gives a value its own error and those under it', () => {
    const errors = { 'config.options': 'Required.', 'config.options.0.value': 'Too long.', 'config.optionsx': 'Other.' };

    assert.deepEqual(errorsAt(errors, 'config.options'), { '': 'Required.', '0.value': 'Too long.' });
    assert.deepEqual(errorsAt(errors, 'config.max'), {});
});

test('keeps a typed handle to lowercase letters, digits and underscores', () => {
    assert.equal(handleInput('contact-person'), 'contact_person');
    assert.equal(handleInput('Contact Person'), 'contact_person');
    assert.equal(handleInput('phone_'), 'phone_');
    assert.equal(handleInput('2nd-try'), 'nd_try');
});

test('warns about a reserved or taken handle as it is typed', () => {
    const a = { key: 'a', handle: 'status', type: 'select' };
    const b = { key: 'b', handle: 'phone', type: 'text' };
    const c = { key: 'c', handle: 'phone', type: 'text' };
    const include = { key: 'd', handle: 'phone', type: 'fieldset' };
    assert.equal(handleWarning(a, [a, b], ['status', 'author']), '"status" is used by the content itself; choose another handle.');
    assert.equal(handleWarning(a, [a, b]), null);
    assert.equal(handleWarning(b, [a, b, c]), 'There is already a field "phone" here.');
    assert.equal(handleWarning(b, [b, include]), null);
});

test('the builder folds fields to their summary: a new one opens alone, and all close at once', async () => {
    const { readFile } = await import('node:fs/promises');
    const source = await readFile(new URL('../../packages/cms/resources/js/components/FieldBuilder.tsx', import.meta.url), 'utf8');

    assert.match(source, /setOpen\(new Set\(\[field\.key\]\)\)/);
    assert.match(source, />Close all</);
    // No example rules that look like rules already set.
    assert.doesNotMatch(source, /placeholder=\{'min:3/);
});
