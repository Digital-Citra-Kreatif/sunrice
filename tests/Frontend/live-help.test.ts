import { test } from 'node:test';
import assert from 'node:assert/strict';
import { codeParts, developerText, liveHelp, livePlaceholder, visibleLinks, withValues } from '../../packages/admin/resources/js/lib/live-help.ts';

test('fills the help from the form as it is edited, with a fallback built from other fields', () => {
    const values = { url: { field: 'listing_route', fallback: '/{handle}' }, collection: { field: 'title', fallback: null } };

    assert.equal(liveHelp('Gives the listing page (:url) its own fields. Under :collection.', values, { listing_route: '/news', title: 'Blog' }), 'Gives the listing page (/news) its own fields. Under Blog.');
    assert.equal(liveHelp('Page: :url', values, { listing_route: '', handle: 'blog' }), 'Page: /blog');
    assert.equal(liveHelp('Keeps :other as it is.', values, {}), 'Keeps :other as it is.');
    assert.equal(withValues('/{handle}/{slug}', { handle: 'blog' }), '/blog/{slug}');
});

test('says what developers write with the typed value, an example while it is empty, code in backticks', () => {
    assert.equal(developerText('`<x-form handle=":value" />`', 'newsletter', 'contact'), '`<x-form handle="newsletter" />`');
    assert.equal(developerText('`<x-form handle=":value" />`', '', 'contact'), '`<x-form handle="contact" />`');
    assert.deepEqual(codeParts('In a template: `<x-form />` here'), [['In a template: ', false], ['<x-form />', true], [' here', false]]);
});

test('shows links by the form\'s values, their addresses with them', () => {
    const links = [
        { label: 'Create', url: '/admin/blueprints/create', newTab: false, showWhen: { field: 'blueprint', value: null, not: true } },
        { label: 'Edit', url: '/admin/blueprints/%7Bblueprint%7D/edit', newTab: false, showWhen: { field: 'blueprint', value: null } },
        { label: 'Site', url: '/{slug}', newTab: true, showWhen: null },
    ];

    assert.deepEqual(visibleLinks(links, { blueprint: '', slug: 'a b' }).map((link) => [link.label, link.url]), [['Create', '/admin/blueprints/create'], ['Site', '/a%20b']]);
    assert.deepEqual(visibleLinks(links, { blueprint: 'page' }).map((link) => [link.label, link.url]), [['Edit', '/admin/blueprints/page/edit'], ['Site', '/{slug}']]);
});

test('a placeholder follows another field, else its fallback, else its own', () => {
    const from = { field: 'route', fallback: '/{handle}/{slug}' };
    assert.equal(livePlaceholder(null, from, { route: '/books/{slug}' }), '/books/{slug}');
    assert.equal(livePlaceholder(null, from, { route: '', handle: 'books' }), '/books/{slug}');
    assert.equal(livePlaceholder('Own', { field: 'route', fallback: null }, { route: null }), 'Own');
    assert.equal(livePlaceholder('Own', null, { route: '/x' }), 'Own');
    assert.equal(livePlaceholder(undefined, undefined, {}), null);
});

test('a placeholder or help value without a field is its fallback with the form\'s values, a {name} kept while that field is empty', () => {
    const from = { field: null, fallback: '/{handle}/{slug}' };
    assert.equal(livePlaceholder('/{handle}/{slug}', from, { route: '/typed/{slug}', handle: 'units' }), '/units/{slug}');
    assert.equal(livePlaceholder('/{handle}/{slug}', from, { handle: '' }), '/{handle}/{slug}');
    assert.equal(liveHelp('Empty: :default.', { default: from }, { handle: 'units' }), 'Empty: /units/{slug}.');
    assert.equal(liveHelp('Empty: :default.', { default: from }, { handle: '' }), 'Empty: /{handle}/{slug}.');
    // Links still fill an empty value as empty.
    assert.equal(withValues('/{handle}', { handle: '' }), '/');
    assert.equal(withValues('/{handle}', { handle: '' }, true), '/{handle}');
});
