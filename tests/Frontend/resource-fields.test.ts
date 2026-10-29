import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createElement, type ComponentType } from 'react';
import { formatDateRange, parseDateRange } from '../../packages/admin/resources/js/lib/date-range.ts';
import { changeWarning, savedWarning } from '../../packages/admin/resources/js/lib/change-warning.ts';
import { isSafeUrl } from '../../packages/admin/resources/js/lib/safe-url.ts';
import { slugInput } from '../../packages/admin/resources/js/lib/slug.ts';
import { loadAdminModule, render } from './support/render.ts';

test('reads and writes a date range filter\'s value', () => {
    assert.deepEqual(parseDateRange('2026-01-01,2026-01-31'), { from: '2026-01-01', to: '2026-01-31' });
    assert.deepEqual(parseDateRange(',2026-01-31'), { from: null, to: '2026-01-31' });
    assert.deepEqual(parseDateRange('soon'), { from: null, to: null });
    assert.deepEqual(parseDateRange(undefined), { from: null, to: null });
    assert.equal(formatDateRange('2026-01-01', null), '2026-01-01,');
    assert.equal(formatDateRange('2026-01-01T10:00', '2026-01-31'), '2026-01-01,2026-01-31');
    assert.equal(formatDateRange(null, null), '');
});

test('warns about a changed value only on an edit form, once it differs from the saved one', () => {
    const warning = 'Templates that use ":old" need ":new".';
    assert.equal(changeWarning(warning, 'news', { handle: 'news' }, 'handle'), null);
    assert.equal(changeWarning(warning, 'journal', { handle: 'news' }, 'handle'), 'Templates that use "news" need "journal".');
    assert.equal(changeWarning(warning, '', { handle: 'news' }, 'handle'), 'Templates that use "news" need "…".');
    assert.equal(changeWarning(warning, 'journal', undefined, 'handle'), null, 'creating: nothing saved');
    assert.equal(changeWarning(null, 'journal', { handle: 'news' }, 'handle'), null);
    assert.equal(changeWarning(warning, null, { handle: '' }, 'handle'), null, 'empty and missing alike');
});

test('shows a warning about the saved record only while the field holds the saved value', () => {
    const warning = { text: '"About" is live at /about.', links: [] };
    assert.equal(savedWarning(warning, '/about', { path: '/about' }, 'path'), warning);
    assert.equal(savedWarning(warning, '/elsewhere', { path: '/about' }, 'path'), null, 'another value: it may not hold');
    assert.equal(savedWarning(warning, '/about', undefined, 'path'), null, 'creating: nothing saved');
    assert.equal(savedWarning(null, '/about', { path: '/about' }, 'path'), null);
    assert.equal(savedWarning({ text: '', links: [] }, '/about', { path: '/about' }, 'path'), null);
});

test('shows a field\'s warning about the saved record under it, with its links', async () => {
    const { FormSections } = await loadAdminModule<{ FormSections: ComponentType<any> }>('components/resources/form-sections.tsx');
    const warning = { text: '"About" is live at /about.', links: [{ label: 'Edit "About"', url: '/admin/about', newTab: false, showWhen: null }] };
    const field = { kind: 'field', type: 'text', name: 'path', label: 'From', required: false, disabled: false, help: null, placeholder: null, span: 1, warning };
    const sections = [{ kind: 'section', title: null, description: null, columns: 1, fields: [field] }];
    const props = { sections, errors: {}, onChange: () => undefined };

    const html = render(FormSections, { ...props, data: { path: '/about' }, saved: { path: '/about' } });
    assert.match(html, /&quot;About&quot; is live at \/about\./);
    assert.match(html, /href="\/admin\/about"/);
    assert.doesNotMatch(render(FormSections, { ...props, data: { path: '/other' }, saved: { path: '/about' } }), /is live at/);
});

test('shows a field\'s change warning under it on an edit form', async () => {
    const { FormSections } = await loadAdminModule<{ FormSections: ComponentType<any> }>('components/resources/form-sections.tsx');
    const field = { kind: 'field', type: 'text', name: 'handle', label: 'Handle', required: false, disabled: false, help: null, placeholder: null, span: 1, warnWhenChanged: 'Code that uses ":old" breaks.' };
    const sections = [{ kind: 'section', title: null, description: null, columns: 1, fields: [field] }];
    const props = { sections, errors: {}, onChange: () => undefined };

    assert.match(render(FormSections, { ...props, data: { handle: 'journal' }, saved: { handle: 'news' } }), /Code that uses &quot;news&quot; breaks\./);
    assert.doesNotMatch(render(FormSections, { ...props, data: { handle: 'news' }, saved: { handle: 'news' } }), /breaks/);
    assert.doesNotMatch(render(FormSections, { ...props, data: { handle: 'journal' } }), /breaks/);
});

test('keeps only safe link addresses in rich text', () => {
    for (const url of ['https://example.com', '/about', 'mailto:a@example.com', 'tel:+62', '#top']) assert.ok(isSafeUrl(url), url);
    for (const url of ['javascript:alert(1)', ' java\tscript:alert(1)', 'data:text/html,x', '\\\\evil']) assert.ok(!isSafeUrl(url), url);
});

test('a relation field searches its resource\'s options route', async () => {
    const { optionsUrl } = await loadAdminModule<{ optionsUrl: (r: string, f: string, q: string) => string }>('components/resources/relation-select.tsx');

    assert.equal(optionsUrl('shop/catalog', 'tags', ' sa '), '/admin/shop/catalog/options/tags?q=sa');
    assert.equal(optionsUrl('catalog', 'tags', ''), '/admin/catalog/options/tags');
});

test('renders a relation field\'s chosen records, and the rich text editor as a placeholder until its code loads', async () => {
    const { RelationSelect } = await loadAdminModule<{ RelationSelect: ComponentType<any> }>('components/resources/relation-select.tsx');
    const { ResourcePathContext } = await loadAdminModule<{ ResourcePathContext: any }>('components/resources/resource-context.ts');
    const one = render(ResourcePathContext, { value: 'catalog' }, createElement(RelationSelect, {
        id: 'field-category', field: 'category', multiple: false, value: 3, selected: [{ value: 3, label: 'Tools' }],
        required: false, disabled: false, invalid: false, onChange: () => undefined,
    }));
    assert.match(one, /id="field-category"[^>]*role="combobox"|role="combobox"[^>]*id="field-category"/);
    assert.match(one, /Tools/);
    assert.match(one, /aria-label="Clear"/);

    const many = render(RelationSelect, {
        id: 'field-tags', field: 'tags', multiple: true, value: [2, 1], selected: [{ value: 1, label: 'Red' }, { value: 2, label: 'Blue' }],
        required: false, disabled: false, invalid: false, onChange: () => undefined,
    });
    assert.ok(many.indexOf('Blue') < many.indexOf('Red'), 'in the chosen order');
    assert.match(many, /aria-label="Remove Red"/);

    const { RichTextEditor } = await loadAdminModule<{ RichTextEditor: ComponentType<any> }>('components/app/rich-text.tsx');
    const editor = render(RichTextEditor, { id: 'field-body', value: '<p>Hi</p>', onChange: () => undefined });
    assert.match(editor, /data-slot="skeleton"/);
});

test('keeps typed slugs and handles to the separator (TextInput::slug)', () => {
    assert.equal(slugInput('Contact-Person', '_'), 'contact_person');
    assert.equal(slugInput('contact ', '_'), 'contact_');
    assert.equal(slugInput('-lead', '-'), 'lead');
});

test('renders a slug field as a monospace handle input', async () => {
    const { FieldInput } = await loadAdminModule<{ FieldInput: ComponentType<any> }>('components/resources/fields.tsx');
    const html = render(FieldInput, { field: { kind: 'field', type: 'text', name: 'handle', label: 'Handle', required: true, disabled: false, help: null, placeholder: null, span: 1, slugFrom: 'title', slugSeparator: '_', maxLength: 64 }, value: 'news', invalid: false, onChange: () => undefined });
    assert.match(html, /font-mono/);
    assert.match(html, /value="news"/);
});
