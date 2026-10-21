import { test } from 'node:test';
import assert from 'node:assert/strict';
import { languageHint } from '../../packages/cms/resources/js/lib/languages.ts';

const say = (text: string, replace: Record<string, string> = {}) => text.replace(/:([a-z]+)/g, (match, name: string) => replace[name] ?? match);
const name = (code: string) => ({ en: 'English', id: 'Bahasa Indonesia', fr: 'French' })[code] ?? code;

test('says which language is being edited and the others to switch to', () => {
    assert.equal(languageHint('en', ['en', 'id'], null, say, name), 'Editing: English (main) — switch to edit Bahasa Indonesia');
    assert.equal(languageHint('id', ['en', 'id', 'fr'], ['en', 'id'], say, name), 'Editing: Bahasa Indonesia — switch to edit English, French');
    assert.equal(languageHint('fr', ['en', 'id', 'fr'], ['en'], say, name), 'Editing: French, not translated yet');
    assert.equal(languageHint('en', ['en'], null, say, name), 'Editing: English (main)');
});
