import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import type { ComponentType } from 'react';
import { CODE_LANGUAGES, codeEditorLines, codeLanguage } from '../../packages/admin/resources/js/lib/code-editor.ts';
import { codeLanguageOf } from '../../packages/cms/resources/js/lib/code.ts';
import { loadAdminModule, loadCmsModule, render } from './support/render.ts';

test('knows the languages it highlights, plain for any other', () => {
    assert.deepEqual([...CODE_LANGUAGES], ['html', 'css', 'javascript', 'json', 'plain']);
    assert.equal(codeLanguage('html'), 'html');
    assert.equal(codeLanguage(' JSON '), 'json');
    assert.equal(codeLanguage('js'), 'javascript');
    assert.equal(codeLanguage('htm'), 'html');
    assert.equal(codeLanguage('php'), 'plain');
    assert.equal(codeLanguage(null), 'plain');
    assert.equal(codeLanguage(3), 'plain');

    assert.equal(codeLanguageOf({ config: { language: 'css' } }), 'css');
    assert.equal(codeLanguageOf({ config: { language: 'ruby' } }), 'plain');
    assert.equal(codeLanguageOf({}), 'plain');
});

test('grows from its least lines to its most, then scrolls', () => {
    assert.deepEqual(codeEditorLines(), { min: 4, max: 20 });
    assert.deepEqual(codeEditorLines(6, 24), { min: 6, max: 24 });
    assert.deepEqual(codeEditorLines(30), { min: 30, max: 30 });
    assert.deepEqual(codeEditorLines(8, 3), { min: 8, max: 8 });
    assert.deepEqual(codeEditorLines(0, 2.6), { min: 1, max: 3 });
    assert.deepEqual(codeEditorLines(Number.NaN, null), { min: 4, max: 20 });
});

test('shows a plain, labelled textarea with the code while CodeMirror loads', async () => {
    const { CodeEditor } = await loadAdminModule<{ CodeEditor: ComponentType<any> }>('components/app/code-editor.tsx');
    const html = render(CodeEditor, {
        id: 'field-head', value: '<script>\n  x()\n</script>', onChange: () => undefined, language: 'html', invalid: true,
        'aria-labelledby': 'field-head-label', 'aria-describedby': 'field-head-help', minLines: 2, maxLines: 6,
    });

    assert.match(html, /^<textarea id="field-head" data-slot="code-editor"/);
    assert.match(html, /rows="3"/);
    assert.match(html, /spellCheck="false"|spellcheck="false"/);
    assert.match(html, /aria-invalid="true"/);
    assert.match(html, /aria-labelledby="field-head-label" aria-describedby="field-head-help"/);
    assert.match(html, /font-mono/);
    assert.match(html, /&lt;script&gt;\n {2}x\(\)\n&lt;\/script&gt;<\/textarea>$/);
});

test('the panel\'s Code field and the CMS\'s code field use the code editor', async () => {
    const { FieldInput } = await loadAdminModule<{ FieldInput: ComponentType<any> }>('components/resources/fields.tsx');
    const panel = render(FieldInput, {
        field: { kind: 'field', type: 'code', name: 'head', label: 'In the <head>', required: false, disabled: false, help: null, placeholder: null, span: 1, language: 'html', minLines: 6, maxLines: 24, maxLength: 20000 },
        value: '<meta name="x">', invalid: false, onChange: () => undefined,
    });
    // The textarea until CodeMirror has loaded (the test above started loading it), then CodeMirror's box.
    assert.match(panel, /<textarea id="field-head" data-slot="code-editor"[^>]*rows="6"[^>]*aria-labelledby="field-head-label"|^<div data-slot="code-editor"/);

    const { FieldInput: CmsFieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const cms = render(CmsFieldInput, {
        field: { handle: 'snippet', label: 'Snippet', type: 'code', instructions: 'Shown as code.', config: { language: 'json', max: 100 } },
        value: '{"a": 1}', onChange: () => undefined, errors: {}, id: 'f-snippet',
    });
    assert.match(cms, /<textarea id="f-snippet" data-slot="code-editor"[^>]*aria-labelledby="f-snippet-label" aria-describedby="f-snippet-help"|<div data-slot="code-editor"/);
    assert.match(cms, /id="f-snippet-label"/);
    assert.match(cms, /8 \/ 100/);
});

test('CodeMirror loads in chunks of its own, never with the panel\'s main script', () => {
    const editor = readFileSync(new URL('../../packages/admin/resources/js/components/app/code-editor.tsx', import.meta.url), 'utf8');
    const languages = readFileSync(new URL('../../packages/admin/resources/js/components/app/code-editor-languages.ts', import.meta.url), 'utf8');
    for (const source of [editor, languages]) {
        assert.doesNotMatch(source, /^import (?!type )[^'"]*from '@codemirror/m, 'only type imports or import() of CodeMirror');
    }
    assert.match(editor, /React\.lazy\(\(\) => import\('\.\/code-editor-view'\)/);

    const manifest = JSON.parse(readFileSync(new URL('../../packages/admin/dist/manifest.json', import.meta.url), 'utf8')) as Record<string, { file: string; imports?: string[]; dynamicImports?: string[] }>;
    const entry = manifest['resources/js/app.tsx'];
    const codemirror = Object.keys(manifest).find((key) => key.startsWith('_codemirror'));
    assert.ok(codemirror, 'a codemirror chunk');
    assert.ok(!(entry.imports ?? []).includes(codemirror), 'not imported by the entry');
    // Nothing the entry loads at once imports it: only the code editor's own chunks do.
    const eager = new Set<string>();
    const visit = (key: string) => {
        if (eager.has(key)) return;
        eager.add(key);
        (manifest[key]?.imports ?? []).forEach(visit);
    };
    visit('resources/js/app.tsx');
    assert.ok(!eager.has(codemirror));
    const importers = Object.entries(manifest).filter(([, chunk]) => (chunk.imports ?? []).includes(codemirror)).map(([key]) => key);
    assert.ok(importers.every((key) => /code-editor-view|@codemirror\/lang-/.test(key)), importers.join(', '));
});
