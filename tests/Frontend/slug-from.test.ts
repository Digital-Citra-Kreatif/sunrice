import assert from 'node:assert/strict';
import { test } from 'node:test';
import { slugify, withSlugs } from '../../packages/admin/resources/js/lib/slug-from.ts';

test('makes handles and address segments from text', () => {
    assert.equal(slugify('Main menu'), 'main_menu');
    assert.equal(slugify('  Café — Menu! '), 'cafe_menu');
    assert.equal(slugify('2026 News'), 'news');
    assert.equal(slugify('2026 News', '-'), '2026-news');
});

test('fills slugged fields from their source until they are typed in', () => {
    const fields = [{ name: 'title' }, { name: 'handle', slugFrom: 'title' }, { name: 'path', slugFrom: 'title', slugSeparator: '-' }];
    const data = { title: '', handle: '', path: '' };

    assert.deepEqual(withSlugs(fields, data, 'title', 'Main menu', new Set()), { title: 'Main menu', handle: 'main_menu', path: 'main-menu' });
    assert.deepEqual(withSlugs(fields, { ...data, handle: 'nav' }, 'title', 'Footer', new Set(['handle'])), { title: 'Footer', handle: 'nav', path: 'footer' });
});
