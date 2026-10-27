import { test } from 'node:test';
import assert from 'node:assert/strict';
import { entryLanguageTabs, localeTabs } from '../../packages/cms/resources/js/lib/languages.ts';

const say = (text: string, replace: Record<string, string> = {}) => text.replace(/:([a-z]+)/g, (match, name: string) => replace[name] ?? match);
const name = (code: string) => ({ en: 'English', id: 'Bahasa Indonesia', fr: 'French' })[code] ?? code;
const status = (value: string) => ({ draft: 'Draft', scheduled: 'Scheduled', published: 'Live' })[value] ?? value;

test('a page with a text per language: the main one first, the ones without theirs missing', () => {
    assert.deepEqual(localeTabs(['en', 'id', 'fr'], ['en', 'id'], name), [
        { code: 'en', name: 'English', main: true, missing: false },
        { code: 'id', name: 'Bahasa Indonesia', main: false, missing: false },
        { code: 'fr', name: 'French', main: false, missing: true },
    ]);
    // Not known: none missing.
    assert.equal(localeTabs(['en', 'id'], null, name).some((tab) => tab.missing), false);
});

test('an entry\'s languages: live green, outdated amber, others said, missing ones to add', () => {
    const locales = [
        { code: 'en', name: 'English', main: true, status: 'published' as const },
        { code: 'id', main: false, status: 'draft' as const, outdated: true },
        { code: 'fr', main: false, status: 'draft' as const },
        { code: 'de', name: 'Deutsch', main: false, status: null },
    ];

    assert.deepEqual(entryLanguageTabs(locales, true, say, status, name), [
        { code: 'en', name: 'English', main: true, status: { tone: 'success', label: 'Live' } },
        { code: 'id', name: 'Bahasa Indonesia', main: false, status: { tone: 'warning', label: 'Outdated: the main language changed since' }, description: 'Draft' },
        { code: 'fr', name: 'French', main: false, description: 'Draft' },
        { code: 'de', name: 'Deutsch', main: false, missing: true },
    ]);
    // Without the right to add one, the missing ones aren't offered.
    assert.deepEqual(entryLanguageTabs(locales, false, say, status, name).map((tab) => tab.code), ['en', 'id', 'fr']);
});
