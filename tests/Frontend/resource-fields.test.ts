import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createElement, type ComponentType } from 'react';
import { formatDateRange, parseDateRange } from '../../packages/admin/resources/js/lib/date-range.ts';
import { isSafeUrl } from '../../packages/admin/resources/js/lib/safe-url.ts';
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
