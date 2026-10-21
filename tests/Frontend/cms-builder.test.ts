import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    cleanHandle, errorKeysAt, errorsAt, errorsUnder, fromDefinitions, handleFromLabel, handleInput, handleWarning, hasErrors, markSaved, move, newField, relabel, renamedFrom, toDefinitions, uniqueHandle,
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

test('tells a saved field whose handle changed, until it is saved again', () => {
    const [hero, body] = fromDefinitions([
        { handle: 'hero', type: 'group', fields: [{ handle: 'heading', type: 'text' }] },
        { handle: 'links', type: 'fieldset' },
    ]);
    assert.ok(hero && body);
    assert.equal(renamedFrom(hero), null);
    assert.equal(renamedFrom({ ...hero, handle: 'banner' }), 'hero');
    assert.equal(renamedFrom({ ...body, handle: 'other' }), null, 'a fieldset include has no handle of its own');
    assert.equal(renamedFrom(newField('text', 'Intro', [])), null, 'a field added since was never saved');

    const [saved] = markSaved([{ ...hero, handle: 'banner', fields: hero.fields.map((f) => ({ ...f, handle: 'title' })) }]);
    assert.equal(renamedFrom(saved!), null);
    assert.equal(saved!.fields[0]?.savedHandle, 'title');
});

test('finds errors under a field', () => {
    const errors = { 'fields.0.handle': 'Taken.', 'fields.0.fields.1.type': 'Bad.', 'fields.10.label': 'Long.', title: 'Required.' };

    assert.deepEqual(errorsUnder(errors, 'fields.0'), { handle: 'Taken.', 'fields.1.type': 'Bad.' });
    assert.equal(hasErrors(errors, 'fields.0.fields'), true);
    assert.equal(hasErrors(errors, 'fields.1'), false);
    assert.equal(hasErrors(errors, 'title'), true);
});

test('lists the error keys to clear when a value is edited', () => {
    const errors = { title: 'Required.', 'data.rating': 'Too high.', 'data.features.0.title': 'Long.', 'data.featuresx': 'Other.' };

    assert.deepEqual(errorKeysAt(errors, 'data.features'), ['data.features.0.title']);
    assert.deepEqual(errorKeysAt(errors, 'title'), ['title']);
    assert.deepEqual(errorKeysAt(errors, 'slug'), []);
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
    assert.match(source, /t\('Close all'\)/);
    // No example rules that look like rules already set.
    assert.doesNotMatch(source, /placeholder=\{'min:3/);
});

test('a multiple select setting in tick order adds what is ticked last, and numbers it', async () => {
    const { readFile } = await import('node:fs/promises');
    const source = await readFile(new URL('../../packages/cms/resources/js/components/FieldInput.tsx', import.meta.url), 'utf8');

    assert.match(source, /inTickOrder \? \[\.\.\.value\.filter\(\(v\) => v !== option\.value\), option\.value\]/);
    assert.match(source, /inTickOrder=\{field\.config\?\.in_tick_order === true\}/);
});

test('keeps a typed handle to what a handle may hold', () => {
    assert.equal(cleanHandle('textareaqwataSSS'), 'textareaqwatasss');
    assert.equal(cleanHandle('Hero Image'), 'hero_image');
    assert.equal(cleanHandle('2nd-title'), 'nd_title');
    assert.equal(cleanHandle('café  menu!'), 'cafe_menu_');
    assert.equal(cleanHandle('hero_'), 'hero_');
});

test('offers only fields with a value of their own for "Show when", never a fieldset include or the field itself', async () => {
    const { conditionChoices } = await import('../../packages/cms/resources/js/lib/builder.ts');
    const siblings = [
        { key: 'a', handle: 'show_map', type: 'toggle' },
        { key: 'b', handle: 'contact', type: 'fieldset' },
        { key: 'c', handle: '', type: 'text' },
        { key: 'd', handle: 'map', type: 'text' },
    ];
    assert.deepEqual(conditionChoices({ key: 'd' }, siblings).map((s) => s.handle), ['show_map']);
});

test('puts a builder setting\'s server error on that setting of that field (shown inline, its card opened)', () => {
    // As BlueprintForm and FieldBuilder pass them on: errors under "fields", then the field's index, then the setting.
    const server = { 'fields.1.config.collections': 'The "From collections" field is required.' };
    const field = errorsUnder(errorsUnder(server, 'fields'), '1');
    assert.equal(hasErrors(errorsUnder(server, 'fields'), '1'), true);
    assert.deepEqual(errorsAt(field, 'config.collections'), { '': 'The "From collections" field is required.' });
    assert.equal(hasErrors(errorsUnder(server, 'fields'), '0'), false);
});
