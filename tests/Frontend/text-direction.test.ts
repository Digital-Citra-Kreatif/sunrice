import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { textDir } from '../../packages/admin/resources/js/lib/text-dir.ts';
import { loadAdminModule, loadCmsModule, render } from './support/render.ts';

test('plain text inputs follow what is typed; addresses, emails and numbers keep the panel\'s direction', () => {
    assert.equal(textDir(undefined), 'auto');
    assert.equal(textDir(null), 'auto');
    assert.equal(textDir('text'), 'auto');
    assert.equal(textDir('search'), 'auto');
    for (const type of ['email', 'url', 'tel', 'password', 'number', 'color']) {
        assert.equal(textDir(type), undefined, type);
    }
});

test('the panel\'s text inputs and text areas get dir="auto", slugs and emails don\'t', async () => {
    const { FieldInput } = await loadAdminModule<{ FieldInput: ComponentType<any> }>('components/resources/fields.tsx');
    const props = { value: '', onChange: () => undefined, invalid: false };

    assert.match(render(FieldInput, { ...props, field: { name: 'name', type: 'text' } }), /<input[^>]*dir="auto"/);
    assert.match(render(FieldInput, { ...props, field: { name: 'bio', type: 'textarea' } }), /<textarea[^>]*dir="auto"/);
    assert.doesNotMatch(render(FieldInput, { ...props, field: { name: 'email', type: 'text', inputType: 'email' } }), /dir=/);
    assert.doesNotMatch(render(FieldInput, { ...props, field: { name: 'handle', type: 'text', slugFrom: 'name' } }), /dir=/);
});

test('the CMS\'s text, text area and link text fields get dir="auto"', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const props = { value: null, onChange: () => undefined, errors: {} };

    assert.match(render(FieldInput, { ...props, id: 'f-title', field: { handle: 'subtitle', label: 'Subtitle', type: 'text' } }), /<input[^>]*id="f-title"[^>]*dir="auto"/);
    assert.match(render(FieldInput, { ...props, id: 'f-intro', field: { handle: 'intro', label: 'Intro', type: 'textarea' } }), /<textarea[^>]*dir="auto"/);
    assert.match(render(FieldInput, { ...props, id: 'f-cta', field: { handle: 'cta', label: 'Call to action', type: 'link' } }), /<input[^>]*dir="auto"[^>]*aria-label="Call to action: link text"/);
});

test('the rich text editor\'s content takes its direction from its text, each paragraph its own', async () => {
    const editor = await readFile(new URL('../../packages/admin/resources/js/components/app/rich-text-editor.tsx', import.meta.url), 'utf8');
    assert.match(editor, /class: 'sunrice-prose',\s*\/\/[^\n]*\n\s*dir: 'auto',/);

    const css = await readFile(new URL('../../packages/admin/resources/css/admin.css', import.meta.url), 'utf8');
    assert.match(css, /\.sunrice-prose :is\(p, h2, h3, h4, li, blockquote, figcaption\) \{ unicode-bidi: plaintext; text-align: start; \}/);
    // The image presets float to the start and end of the line, as on the site.
    assert.match(css, /\.sunrice-img-left \{ float: inline-start;/);
    assert.match(css, /\.sunrice-img-right \{ float: inline-end;/);
});
