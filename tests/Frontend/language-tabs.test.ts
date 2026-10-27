import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType, ReactNode } from 'react';
import { loadAdminModule, render } from './support/render.ts';

interface Tab { code: string; name?: string; main?: boolean; missing?: boolean; status?: { tone: 'success' | 'warning'; label: string } | null; description?: string | null }
type Props = { languages: Tab[]; value: string; onValueChange: (code: string) => void; onAdd?: (code: string) => void; actions?: ReactNode; children?: (code: string) => ReactNode };
type Module = { LanguageTabs: ComponentType<Props>; languageTabTitle: (tab: Tab, adding: boolean) => string };

const languages: Tab[] = [
    { code: 'en', name: 'English', main: true, status: { tone: 'success', label: 'Live' } },
    { code: 'id', name: 'Bahasa Indonesia', status: { tone: 'warning', label: 'Outdated' }, description: 'Draft' },
    { code: 'fr', name: 'French', missing: true },
];

test('says each language with what its marks mean', async () => {
    const { languageTabTitle } = await loadAdminModule<Module>('components/app/language-tabs.tsx');

    assert.equal(languageTabTitle(languages[0]!, false), 'English (main): Live');
    assert.equal(languageTabTitle(languages[1]!, false), 'Bahasa Indonesia: Outdated, Draft');
    assert.equal(languageTabTitle(languages[2]!, true), 'Add French');
    assert.equal(languageTabTitle(languages[2]!, false), 'French: not translated yet');
    assert.equal(languageTabTitle({ code: 'de' }, false), 'de');
});

test('a tab per language, the open one selected, the main one marked, missing ones with a plus', async () => {
    const { LanguageTabs } = await loadAdminModule<Module>('components/app/language-tabs.tsx');
    const html = render(LanguageTabs, { languages, value: 'id', onValueChange: () => {}, onAdd: () => {} });

    assert.match(html, /role="tablist"[^>]*aria-label="Language"/);
    assert.equal(html.match(/role="tab"/g)?.length, 3);
    assert.match(html, /aria-selected="true"[^>]*aria-label="Bahasa Indonesia: Outdated, Draft"/);
    assert.match(html, />Main</);
    assert.match(html, /aria-label="Add French"/);
    assert.match(html, /bg-emerald-500/);
    assert.match(html, /bg-amber-500/);
    // Switching pages: no panel to point at.
    assert.doesNotMatch(html, /aria-controls/);
});

test('no tabs for one language; in place, each language gets its panel', async () => {
    const { LanguageTabs } = await loadAdminModule<Module>('components/app/language-tabs.tsx');

    assert.equal(render(LanguageTabs, { languages: [languages[0]!], value: 'en', onValueChange: () => {} }), '');
    assert.equal(render(LanguageTabs, { languages: [], value: '', onValueChange: () => {}, children: (code) => `panel ${code}` }), 'panel ');

    const html = render(LanguageTabs, { languages: languages.slice(0, 2), value: 'en', onValueChange: () => {}, children: (code) => `panel ${code}` });
    assert.match(html, /aria-controls="[^"]+"/);
    assert.equal(html.match(/role="tabpanel"/g)?.length, 2);
    assert.match(html, /panel en/);
    assert.match(html, /panel id/);
});
