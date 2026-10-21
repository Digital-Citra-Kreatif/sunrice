import assert from 'node:assert/strict';
import { test } from 'node:test';
import { savedValues } from '../../packages/admin/resources/js/lib/settings-form.ts';

test('after saving, the settings form shows what the server saved', () => {
    // Two-factor switched off and a number typed: the page reloads with the saved values.
    const onScreen = { twoFactor: false, closeAfterDays: '365', apiKey: 'typed secret' };
    const props = { values: { twoFactor: false, closeAfterDays: 365, apiKey: null } };

    assert.deepEqual(savedValues(props, onScreen), { twoFactor: false, closeAfterDays: 365, apiKey: null });
    // Never the values the form started with.
    assert.notDeepEqual(savedValues(props, onScreen), { twoFactor: true, closeAfterDays: null, apiKey: null });
});

test('keeps what is on screen when the response has no values', () => {
    const onScreen = { twoFactor: true };
    assert.deepEqual(savedValues({}, onScreen), onScreen);
    assert.deepEqual(savedValues({ values: [] }, onScreen), onScreen);
});
