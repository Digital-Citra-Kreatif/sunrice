import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { defaultNewTab, linkGroupsOf, linkSearchQuery, parseDataLink, relAttribute, relTokens, toggleRel } from '../../packages/admin/resources/js/lib/rich-link.ts';
import { loadAdminModule, render } from './support/render.ts';

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

test('the rel to store holds the choices alone, in order, or nothing', () => {
    assert.equal(relAttribute(['ugc', 'nofollow']), 'nofollow ugc');
    assert.equal(relAttribute(['junk']), null);
    assert.equal(relAttribute([]), null);
});

test('a new address link opens in a new tab; links to the site\'s own pages don\'t', () => {
    assert.equal(defaultNewTab('url'), true);
    for (const kind of ['entry', 'term', 'listing']) assert.equal(defaultNewTab(kind), false, kind);
});

test('the link attributes show the new-tab box, the rel toggles with what each is for, and the safety note', async () => {
    const { LinkAttributes } = await loadAdminModule<{ LinkAttributes: ComponentType<any> }>('components/app/link-attributes.tsx');
    const html = render(LinkAttributes, { newTab: true, rel: ['sponsored'], onNewTabChange: () => undefined, onRelChange: () => undefined, label: 'Cta: link attributes' });

    assert.match(html, /role="group" aria-label="Cta: link attributes"/);
    assert.match(html, /role="checkbox" aria-checked="true"/);
    assert.match(html, /aria-pressed="false"[^>]*>nofollow</);
    assert.match(html, /aria-pressed="true"[^>]*>sponsored</);
    assert.match(html, /search engines don&#x27;t follow the link/);
    assert.match(html, /always do so safely \(noopener noreferrer\)/);
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
