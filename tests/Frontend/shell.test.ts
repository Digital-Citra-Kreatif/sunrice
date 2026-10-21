import assert from 'node:assert/strict';
import { test } from 'node:test';
import { flushPrefetchAfterWrites } from '../../packages/admin/resources/js/lib/prefetch-flush.ts';
import { errorToast } from '../../packages/admin/resources/js/lib/error-toast.ts';
import { copyText, type ClipboardEnv } from '../../packages/admin/resources/js/lib/clipboard.ts';
import { parseFolded, withFolded } from '../../packages/admin/resources/js/lib/folded-groups.ts';
import { matchNavigation, navigationCrumbs } from '../../packages/admin/resources/js/lib/nav-match.ts';

const navigation = [
    { group: null, items: [{ key: 'media', label: 'Media', url: 'media', icon: 'image', children: [] }] },
    { group: 'Content', items: [
        { key: 'entries', label: 'Entries', url: 'cms/entries', icon: 'file-text', children: [
            { key: 'entries.blog', label: 'Blog', url: 'cms/collections/blog/entries', icon: '' },
        ] },
    ] },
    { group: 'Administration', items: [{ key: 'users', label: 'Users', url: 'users', icon: 'users', children: [] }] },
];
const href = (url: string) => `/admin/${url}`.replace(/\/$/, '');

test('matches the navigation link with the longest prefix of the address', () => {
    assert.equal(matchNavigation(navigation, '/admin/users/5/edit', href)?.link.key, 'users');
    assert.equal(matchNavigation(navigation, '/admin/cms/collections/blog/entries/12?tab=seo', href)?.link.key, 'entries.blog');
    assert.equal(matchNavigation(navigation, '/admin/cms/entries', href)?.item.key, 'entries');
    assert.equal(matchNavigation(navigation, '/admin/usersettings', href), null);
    assert.equal(matchNavigation(navigation, '/admin', href), null);
});

test('derives breadcrumbs from the navigation', () => {
    assert.deepEqual(navigationCrumbs(navigation, '/admin/users/create', href), [{ label: 'Users', href: '/admin/users' }]);
    assert.deepEqual(navigationCrumbs(navigation, '/admin/cms/collections/blog/entries', href), [
        { label: 'Entries', href: '/admin/cms/entries' },
        { label: 'Blog', href: '/admin/cms/collections/blog/entries' },
    ]);
    assert.deepEqual(navigationCrumbs(navigation, '/admin/elsewhere', href), []);
});

/** A tiny DOM: a body that takes children and a textarea that can be selected. */
function fakeDocument(copies: boolean) {
    const log: string[] = [];
    const body = { appendChild: (el: { value: string }) => log.push(`append:${el.value}`) };
    const document = {
        body,
        activeElement: null,
        createElement: () => ({
            value: '', style: {} as Record<string, string>,
            setAttribute: () => {}, focus: () => {}, select: () => log.push('select'), remove: () => log.push('remove'),
        }),
        execCommand: (command: string) => { log.push(`exec:${command}`); return copies; },
    };

    return { document: document as unknown as ClipboardEnv['document'], log };
}

test('copies with the Clipboard API on secure pages', async () => {
    const written: string[] = [];
    const { document, log } = fakeDocument(true);
    await copyText('https://example.com/a.jpg', { isSecureContext: true, clipboard: { writeText: async (t) => { written.push(t); } }, document });
    assert.deepEqual(written, ['https://example.com/a.jpg']);
    assert.deepEqual(log, []);
});

test('falls back to a hidden textarea on plain http, and says when that fails', async () => {
    const { document, log } = fakeDocument(true);
    await copyText('link', { isSecureContext: false, clipboard: undefined, document });
    assert.deepEqual(log, ['append:link', 'select', 'exec:copy', 'remove']);

    const failing = fakeDocument(false);
    await assert.rejects(copyText('link', { isSecureContext: false, document: failing.document }), /Copy failed/);
    assert.ok(failing.log.includes('remove'));
});

test('remembers which sidebar groups are folded', () => {
    assert.deepEqual(parseFolded(null), []);
    assert.deepEqual(parseFolded('not json'), []);
    assert.deepEqual(parseFolded('["Structure", 5]'), ['Structure']);
    assert.deepEqual(withFolded(['Structure'], 'Shop', false), ['Structure', 'Shop']);
    assert.deepEqual(withFolded(['Structure', 'Shop'], 'Structure', true), ['Shop']);
    assert.deepEqual(withFolded(['Shop'], 'Shop', false), ['Shop']);
});

test('a rejected save toasts once: the same errors again update the same toast', () => {
    const first = errorToast({ slug: 'Another entry in this collection already uses "team".' });
    // The deferred-props reload right after the redirect carries the same errors.
    const again = errorToast({ slug: 'Another entry in this collection already uses "team".' });
    assert.deepEqual(first, { message: 'Another entry in this collection already uses "team".', id: 'errors:Another entry in this collection already uses "team".' });
    assert.equal(again?.id, first?.id);
    assert.equal(errorToast({ title: 'A', slug: 'B' })?.message, 'A (+1 more)');
    assert.notEqual(errorToast({ title: 'A', slug: 'B' })?.id, first?.id);
    assert.equal(errorToast({}), null);
    assert.equal(errorToast({ slug: '' }), null);
});

test('a finished write drops the prefetched pages, a read keeps them', () => {
    let listener: ((event: { detail: { visit: { method: string } } }) => void) | undefined;
    let flushed = 0;
    const router = { on: (_event: 'finish', callback: typeof listener) => { listener = callback; return () => { listener = undefined; }; }, flushAll: () => { flushed++; } };

    const off = flushPrefetchAfterWrites(router as never);
    listener!({ detail: { visit: { method: 'get' } } });
    assert.equal(flushed, 0);
    for (const method of ['post', 'put', 'patch', 'delete']) listener!({ detail: { visit: { method } } });
    assert.equal(flushed, 4);
    off();
    assert.equal(listener, undefined);
});
