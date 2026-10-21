import assert from 'node:assert/strict';
import { test } from 'node:test';
import { askToLeave, isAskingToLeave, leave, stay, subscribeLeave } from '../../packages/admin/resources/js/lib/leave-confirm.ts';

test('asks before leaving, and goes on only when told to', () => {
    let changes = 0;
    let left = 0;
    const unsubscribe = subscribeLeave(() => { changes++; });

    askToLeave(() => { left++; });
    assert.equal(isAskingToLeave(), true);
    stay();
    assert.equal(isAskingToLeave(), false);
    assert.equal(left, 0, 'staying keeps the page');

    askToLeave(() => { left++; });
    leave();
    assert.equal(left, 1);
    assert.equal(isAskingToLeave(), false);
    leave();
    assert.equal(left, 1, 'once');
    assert.equal(changes, 5);
    unsubscribe();
});
