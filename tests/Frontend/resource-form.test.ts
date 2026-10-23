import assert from 'node:assert/strict';
import { test } from 'node:test';
import { resourceFormKey, savedValues } from '../../packages/admin/resources/js/lib/resource-form.ts';

test('a resource form is a new one per record, so creating a record opens its edit page with a fresh form', () => {
    const users = { key: 'users' };
    assert.equal(resourceFormKey(users, null), 'users:new');
    assert.equal(resourceFormKey(users, { key: '7' }), 'users:7');
    assert.notEqual(resourceFormKey(users, null), resourceFormKey(users, { key: '7' }));
    assert.notEqual(resourceFormKey(users, { key: '7' }), resourceFormKey({ key: 'roles' }, { key: '7' }));
});

test('after a save the form takes the values the server sent back, without the password typed for it', () => {
    const typed = { name: 'Wendy', password: 'Typed-secret-123', password_confirmation: 'Typed-secret-123' };
    const fresh = { name: 'Wendy', password: '', password_confirmation: '' };

    assert.deepEqual(savedValues({ values: fresh }, typed), fresh);
    // A save that leaves the edit page (no values) keeps what was on screen.
    assert.deepEqual(savedValues({}, typed), typed);
    assert.deepEqual(savedValues(undefined, typed), typed);
    assert.deepEqual(savedValues({ values: ['not', 'a', 'record'] }, typed), typed);
});
