import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isAddressInput, isSearchInput, linkGroupsOf, linkSearchQuery, parseDataLink, relTokens, toggleRel } from '../../packages/admin/resources/js/lib/rich-link.ts';

test('a link\'s rel reads back as the editor\'s choices, ignoring what the server adds', () => {
    assert.deepEqual(relTokens('nofollow noopener noreferrer'), ['nofollow']);
    assert.deepEqual(relTokens('UGC  sponsored nofollow junk'), ['nofollow', 'sponsored', 'ugc']);
    assert.deepEqual(relTokens(null), []);
    assert.deepEqual(relTokens(''), []);
});

test('turning a rel choice on or off keeps only the choices, in order, or none', () => {
    assert.equal(toggleRel('noopener noreferrer', 'nofollow'), 'nofollow');
    assert.equal(toggleRel('ugc noopener noreferrer', 'nofollow'), 'nofollow ugc');
    assert.equal(toggleRel('nofollow noopener noreferrer', 'nofollow'), null);
    assert.equal(toggleRel(null, 'sponsored'), 'sponsored');
});

test('data-link reads as a source and an id, or not at all', () => {
    assert.deepEqual(parseDataLink('entry:12'), { source: 'entry', id: 12 });
    assert.deepEqual(parseDataLink(' listing:3 '), { source: 'listing', id: 3 });
    assert.equal(parseDataLink('Entry:12'), null);
    assert.equal(parseDataLink('entry:x'), null);
    assert.equal(parseDataLink('entry:12 onclick'), null);
    assert.equal(parseDataLink(null), null);
});

test('what is typed is an address with a slash, a scheme, # or ?; other words are a search', () => {
    for (const address of ['/about', 'https://example.com', 'mailto:hi@example.com', 'tel:+62123', '#prices', '?page=2', 'example.com/a']) {
        assert.equal(isAddressInput(address), true, address);
        assert.equal(isSearchInput(address), false, address);
    }
    for (const words of ['about us', 'coffee', 'example.com']) {
        assert.equal(isAddressInput(words), false, words);
        assert.equal(isSearchInput(words), true, words);
    }
    assert.equal(isSearchInput('a'), false);
    assert.equal(isAddressInput('  '), false);
    assert.equal(isSearchInput('  '), false);
});

test('the search asks for one source or all of them, and keeps only well-formed results', () => {
    assert.equal(linkSearchQuery(' about us ', null), 'q=about+us');
    assert.equal(linkSearchQuery('news', 'listing'), 'q=news&source=listing');
    const item = { link: 'entry:1', id: 1, title: 'About', hint: 'Pages', url: '/about' };
    assert.deepEqual(linkGroupsOf({ groups: [{ key: 'entry', label: 'Entry', items: [item, { title: 'no link' }, null] }, { key: 'term', label: 'Term', items: [] }, 'junk'] }),
        [{ key: 'entry', label: 'Entry', items: [item] }]);
    assert.deepEqual(linkGroupsOf(null), []);
    assert.deepEqual(linkGroupsOf({ groups: 'x' }), []);
});
