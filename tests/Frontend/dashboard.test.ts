import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatStat, greeting, isExternal, quickLinks, relativeTime, spanClass, widgetHref } from '../../packages/admin/resources/js/lib/dashboard.ts';

const adminUrl = (path: string) => `/admin/${path}`;

test('resolves widget links against the panel path', () => {
    assert.equal(widgetHref('shop/orders', adminUrl), '/admin/shop/orders');
    assert.equal(widgetHref('cms/comments?status=pending', adminUrl), '/admin/cms/comments?status=pending');
    assert.equal(widgetHref('/elsewhere', adminUrl), '/elsewhere');
    assert.equal(widgetHref('https://example.com/a', adminUrl), 'https://example.com/a');
    assert.equal(isExternal('https://example.com'), true);
    assert.equal(isExternal('/admin/media'), false);
});

test('spans one, two or three columns', () => {
    assert.equal(spanClass(1), '');
    assert.equal(spanClass(2), 'md:col-span-2');
    assert.equal(spanClass(3), 'md:col-span-2 xl:col-span-3');
});

test('greets by the time of day', () => {
    assert.equal(greeting(new Date(2026, 9, 20, 8)), 'Good morning');
    assert.equal(greeting(new Date(2026, 9, 20, 14)), 'Good afternoon');
    assert.equal(greeting(new Date(2026, 9, 20, 21)), 'Good evening');
    assert.equal(greeting(new Date(2026, 9, 20, 2)), 'Good evening');
});

test('formats stats and times', () => {
    assert.equal(formatStat(12345, 'en-US'), '12,345');
    assert.equal(formatStat('n/a'), 'n/a');
    const now = new Date('2026-10-20T12:00:00Z');
    assert.equal(relativeTime('2026-10-20T11:55:00Z', now), '5 minutes ago');
    assert.equal(relativeTime('2026-10-23T12:00:00Z', now), 'in 3 days');
    assert.equal(relativeTime(null, now), '');
    assert.equal(relativeTime('not a date', now), '');
});

test('takes quick links from the sidebar, administration last', () => {
    const navigation = [
        { group: 'Administration', items: [{ key: 'settings', label: 'Settings', url: 'settings', icon: 'settings', children: [] }] },
        { group: null, items: [{ key: 'media', label: 'Media', url: 'media', icon: 'image', children: [] }] },
        { group: 'Content', items: [
            { key: 'entries', label: 'Entries', url: 'cms/entries', icon: 'file-text', children: [] },
            { key: 'terms', label: 'Terms', url: 'cms/terms', icon: 'tags', children: [] },
        ] },
    ];
    assert.deepEqual(quickLinks(navigation, 3).map((link) => link.key), ['media', 'entries', 'terms']);
    assert.deepEqual(quickLinks(navigation).map((link) => link.key), ['media', 'entries', 'terms', 'settings']);
    assert.deepEqual(quickLinks([]), []);
});
