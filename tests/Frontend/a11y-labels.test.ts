import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { sortableAnnouncements } from '../../packages/admin/resources/js/lib/sortable-announcements.ts';
import { loadAdminModule, loadCmsModule, render } from './support/render.ts';

test('the admin shell has one main landmark: the sidebar inset is a plain container around it', async () => {
    const { SidebarInset } = await loadAdminModule<{ SidebarInset: ComponentType<any> }>('components/ui/sidebar.tsx');
    const inset = render(SidebarInset, {}, 'content');
    assert.match(inset, /^<div data-slot="sidebar-inset"/);
    assert.doesNotMatch(inset, /<main/);

    const layout = await readFile(new URL('../../packages/admin/resources/js/layouts/AppLayout.tsx', import.meta.url), 'utf8');
    assert.equal((layout.match(/<main\b/g) ?? []).length, 1);
});

test('drag-and-drop announcements name the items and where they go', () => {
    const titles: Record<string, string> = { a: 'Home', b: 'About', c: 'Contact' };
    const say = sortableAnnouncements(['a', 'b', 'c'], (id) => titles[id]);

    assert.equal(say.onDragStart({ active: { id: 'b' } }), 'Picked up About, position 2 of 3.');
    assert.equal(say.onDragOver({ active: { id: 'b' }, over: { id: 'a' } }), 'About moved above Home, position 1 of 3.');
    assert.equal(say.onDragOver({ active: { id: 'a' }, over: { id: 'c' } }), 'Home moved below Contact, position 3 of 3.');
    assert.equal(say.onDragOver({ active: { id: 'a' }, over: { id: 'a' } }), 'Home is back in its place.');
    assert.equal(say.onDragEnd({ active: { id: 'c' }, over: { id: 'a' } }), 'Contact dropped above Home, position 1 of 3.');
    assert.equal(say.onDragEnd({ active: { id: 'c' }, over: null }), "Contact dropped; its place didn't change.");
    assert.equal(say.onDragCancel({ active: { id: 'a' } }), "Moving Home was cancelled; it's back in its place.");
});

test('items without a label are announced by position, never by their internal id', () => {
    const say = sortableAnnouncements([47, 36], (id) => (id === 47 ? '  ' : null));
    assert.equal(say.onDragEnd({ active: { id: 47 }, over: { id: 36 } }), 'item 1 dropped below item 2, position 2 of 2.');
    assert.equal(sortableAnnouncements([47, 36]).onDragStart({ active: { id: 36 } }), 'Picked up item 2, position 2 of 2.');
});

test("a link field's inputs are named after the field, not just \"Address\"", async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const html = render(FieldInput, { field: { handle: 'map_link', label: 'Map link', type: 'link' }, value: null, onChange: () => undefined, errors: {}, id: 'f-map' });

    assert.match(html, /role="radiogroup" aria-label="Map link: link to"/);
    assert.match(html, /id="f-map" aria-label="Map link: address"/);
    assert.match(html, /aria-label="Map link: link text"/);
    assert.doesNotMatch(html, /aria-label="Address"/);
});

test("a refused blueprint shows the server's message under the field's setting, tied to it (STR-6)", async () => {
    const { FieldBuilder } = await loadCmsModule<{ FieldBuilder: ComponentType<any> }>('components/FieldBuilder.tsx');
    const { errorsUnder } = await loadCmsModule<{ errorsUnder: (errors: Record<string, string>, path: string) => Record<string, string> }>('lib/builder.ts');
    const collections = { handle: 'collections', label: 'From collections', type: 'select', required: true, config: { multiple: true, options: [{ value: 'pages', label: 'Pages' }] } };
    const types = [{ type: 'entries', label: 'Entries', hasChildren: false, settings: [collections, { handle: 'multiple', label: 'Several entries', type: 'toggle' }] }];
    const fields = [{ key: 'k1', handle: 'linked', type: 'entries', label: 'Linked', config: {}, fields: [], validation: [], required: false }];
    // As Inertia hands them over after the save is refused.
    const errors = errorsUnder({ 'fields.0.config.collections': 'The "From collections" field is required.' }, 'fields');
    const html = render(FieldBuilder, { fields, onChange: () => undefined, types, errors });

    assert.match(html, /data-invalid="true"/);
    assert.match(html, /role="group" aria-labelledby="field-k1-config-collections-label" aria-invalid="true" aria-describedby="field-k1-config-collections-error"/);
    assert.match(html, /<p id="field-k1-config-collections-error" class="[^"]*cms:text-destructive[^"]*">The &quot;From collections&quot; field is required.<\/p>/);
    assert.match(html, /role="checkbox" aria-checked="false"[^>]*aria-invalid="true"/);
});

test("a field's row shows its type, label and handle together, with Duplicate and Remove named after it", async () => {
    const { FieldBuilder } = await loadCmsModule<{ FieldBuilder: ComponentType<any> }>('components/FieldBuilder.tsx');
    const types = [
        { type: 'textarea', label: 'Textarea', hasChildren: false, translatable: true, settings: [] },
        { type: 'number', label: 'Number', hasChildren: false, translatable: false, settings: [] },
    ];
    const fields = [
        { key: 'a', handle: 'excerpt', type: 'textarea', label: 'Excerpt', config: {}, fields: [], validation: [], required: true },
        { key: 'b', handle: 'price', type: 'number', label: 'Price', config: {}, fields: [], validation: [] },
        { key: 'c', handle: 'note', type: 'textarea', label: 'Note', config: {}, fields: [], validation: [], translatable: false, condition: { field: 'price', operator: 'filled' } },
    ];
    const html = render(FieldBuilder, { fields, onChange: () => undefined, types, errors: {}, locales: [{ code: 'id', name: 'Bahasa Indonesia' }] });
    const rows = html;

    // Type, label and handle in this order, on the left.
    assert.match(rows, /Textarea<\/span><span[^>]*><span[^>]*>Excerpt<\/span><code[^>]*>excerpt<\/code>/);
    assert.match(rows, /aria-expanded="false" aria-controls="field-a"[^>]*aria-label="Open Excerpt"/);
    assert.match(rows, /aria-label="Duplicate Excerpt"/);
    assert.match(rows, /aria-label="Remove Excerpt"/);
    assert.match(rows, /aria-live="polite"/);
    // Markers: required; shared only where a field isn't translated in a multilingual site; conditional.
    assert.equal((rows.match(/>Shared</g) ?? []).length, 2);
    assert.equal((rows.match(/>Required</g) ?? []).length, 1);
    assert.equal((rows.match(/>Conditional</g) ?? []).length, 1);
    const single = render(FieldBuilder, { fields, onChange: () => undefined, types, errors: {} });
    assert.doesNotMatch(single, />Shared</);
});
