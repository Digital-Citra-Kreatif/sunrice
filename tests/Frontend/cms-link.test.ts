import { test } from 'node:test';
import assert from 'node:assert/strict';
import { defaultNewTab, linkKindOf, linkRelOf, linkValue, switchLink, toggleLinkRel } from '../../packages/cms/resources/js/lib/values.ts';

const none = { url: undefined, entry_id: undefined, term_id: undefined, collection_id: undefined };

test('an empty link is no link, unless it has text, a tab or a rel choice', () => {
    assert.equal(linkValue({ type: 'url', url: '' }), null);
    assert.equal(linkValue({ type: 'entry' }), null);
    assert.equal(linkValue({ type: 'term' }), null);
    assert.equal(linkValue({ type: 'listing', collection_id: undefined }), null);
    assert.deepEqual(linkValue({ type: 'entry', label: 'Team' }), { type: 'entry', label: 'Team' });
    assert.deepEqual(linkValue({ type: 'url', rel: ['nofollow'] }), { type: 'url', rel: ['nofollow'] });
    assert.deepEqual(linkValue({ type: 'url', url: '/a' }), { type: 'url', url: '/a' });
    assert.deepEqual(linkValue({ type: 'term', term_id: 5 }), { type: 'term', term_id: 5 });
    // Older values without a type are addresses.
    assert.deepEqual(linkValue({ url: '/a' }), { url: '/a' });
});

test('switching to an entry and back brings the address back', () => {
    const toEntry = switchLink({ type: 'url', url: '/management' }, 'url', 'entry', {});
    assert.equal(toEntry.value, null); // no entry picked yet: nothing to save
    const back = switchLink(toEntry.value ?? {}, 'entry', 'url', toEntry.remembered);
    assert.deepEqual(back.value, { ...none, type: 'url', url: '/management' });
});

test('switching to an address and back brings the entry back, and keeps the text, tab and rel', () => {
    const toUrl = switchLink({ type: 'entry', entry_id: 7, label: 'Team', new_tab: true, rel: ['ugc'] }, 'entry', 'url', {});
    assert.deepEqual(toUrl.value, { ...none, type: 'url', label: 'Team', new_tab: true, rel: ['ugc'] });
    const back = switchLink(toUrl.value ?? {}, 'url', 'entry', toUrl.remembered);
    assert.deepEqual(back.value, { ...none, type: 'entry', entry_id: 7, label: 'Team', new_tab: true, rel: ['ugc'] });
});

test('each kind remembers its own target: a term and a listing page too', () => {
    let state = switchLink({ type: 'term', term_id: 5 }, 'term', 'listing', {});
    state = switchLink({ ...(state.value ?? {}), type: 'listing', collection_id: 2 }, 'listing', 'term', state.remembered);
    assert.deepEqual(state.value, { ...none, type: 'term', term_id: 5 });
    state = switchLink(state.value ?? {}, 'term', 'listing', state.remembered);
    assert.deepEqual(state.value, { ...none, type: 'listing', collection_id: 2 });
});

test('what was typed last for a kind is what comes back', () => {
    let state = switchLink({ type: 'url', url: '/a' }, 'url', 'entry', {});
    state = switchLink({ type: 'entry', entry_id: 3 }, 'entry', 'url', state.remembered);
    assert.deepEqual(state.value, { ...none, type: 'url', url: '/a' });
    state = switchLink({ type: 'url', url: '' }, 'url', 'entry', state.remembered); // cleared on purpose
    state = switchLink(state.value ?? {}, 'entry', 'url', state.remembered);
    assert.equal(state.value, null);
});

test('a new address opens in a new tab; the site\'s own pages don\'t', () => {
    assert.equal(defaultNewTab('url'), true);
    assert.equal(defaultNewTab('entry'), false);
    assert.equal(defaultNewTab('term'), false);
    assert.equal(defaultNewTab('listing'), false);
});

test('kinds read back from what is stored', () => {
    assert.equal(linkKindOf('listing'), 'listing');
    assert.equal(linkKindOf('page'), null);
    assert.equal(linkKindOf(undefined), null);
});

test('rel choices are nofollow, sponsored and ugc, each once, in order', () => {
    assert.deepEqual(linkRelOf(['ugc', 'nofollow', 'junk', 'nofollow']), ['nofollow', 'ugc']);
    assert.deepEqual(linkRelOf('sponsored noopener'), ['sponsored']);
    assert.deepEqual(linkRelOf(null), []);
    assert.deepEqual(toggleLinkRel(['ugc'], 'nofollow'), ['nofollow', 'ugc']);
    assert.deepEqual(toggleLinkRel(['nofollow'], 'nofollow'), []);
});
