import { test } from 'node:test';
import assert from 'node:assert/strict';
import { linkValue, switchLink } from '../../packages/cms/resources/js/lib/values.ts';

test('an empty link is no link, unless it has text or a tab choice', () => {
    assert.equal(linkValue({ type: 'url', url: '' }), null);
    assert.equal(linkValue({ type: 'entry' }), null);
    assert.deepEqual(linkValue({ type: 'entry', label: 'Team' }), { type: 'entry', label: 'Team' });
    assert.deepEqual(linkValue({ type: 'url', url: '/a' }), { type: 'url', url: '/a' });
});

test('switching to an entry and back brings the address back', () => {
    const toEntry = switchLink({ type: 'url', url: '/management' }, 'url', 'entry', {});
    assert.equal(toEntry.value, null); // no entry picked yet: nothing to save
    const back = switchLink(toEntry.value ?? {}, 'entry', 'url', toEntry.remembered);
    assert.deepEqual(back.value, { type: 'url', url: '/management', entry_id: undefined });
});

test('switching to an address and back brings the entry back, and keeps the text and tab', () => {
    const toUrl = switchLink({ type: 'entry', entry_id: 7, label: 'Team', new_tab: true }, 'entry', 'url', {});
    assert.deepEqual(toUrl.value, { type: 'url', url: undefined, entry_id: undefined, label: 'Team', new_tab: true });
    const back = switchLink(toUrl.value ?? {}, 'url', 'entry', toUrl.remembered);
    assert.deepEqual(back.value, { type: 'entry', url: undefined, entry_id: 7, label: 'Team', new_tab: true });
});

test('what was typed last for a kind is what comes back', () => {
    let state = switchLink({ type: 'url', url: '/a' }, 'url', 'entry', {});
    state = switchLink({ type: 'entry', entry_id: 3 }, 'entry', 'url', state.remembered);
    assert.deepEqual(state.value, { type: 'url', url: '/a', entry_id: undefined });
    state = switchLink({ type: 'url', url: '' }, 'url', 'entry', state.remembered); // cleared on purpose
    state = switchLink(state.value ?? {}, 'entry', 'url', state.remembered);
    assert.equal(state.value, null);
});
