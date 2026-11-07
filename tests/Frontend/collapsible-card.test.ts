import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { readFile, readdir } from 'node:fs/promises';
import { createElement, type ComponentType } from 'react';
import ts from 'typescript';
import { loadAdminModule, render } from './support/render.ts';

const { CollapsibleCard } = await loadAdminModule<{ CollapsibleCard: ComponentType<any> }>('components/app/collapsible-card.tsx');
afterEach(() => { delete (globalThis as { window?: unknown }).window; });

const card = (props: Record<string, unknown> = {}, children: unknown = 'Card content') =>
    render(CollapsibleCard, { title: 'Details', ...props }, children);

test('starts expanded and exposes an accessible, non-submit toggle', () => {
    const html = card();
    assert.match(html, /type="button" aria-expanded="true"/);
    const id = /aria-controls="([^"]+)"/.exec(html)?.[1];
    assert.ok(id && html.includes(`id="${id}" data-state="open"`));
    assert.doesNotMatch(html, /inert=""/);
    // Never wider than the card, whatever it holds.
    assert.match(html, /class="min-h-0 min-w-0 overflow-clip"/);
});

test('keeps fields mounted but inert and hidden from assistive technology when collapsed', () => {
    const html = card({ defaultOpen: false }, createElement('input', { name: 'title', defaultValue: 'Unsaved content' }));
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, /data-state="closed"/);
    assert.match(html, /inert="" aria-hidden="true"/);
    assert.match(html, /name="title" value="Unsaved content"/);
    assert.doesNotMatch(html, /disabled/);
});

test('remembers a collapsed preference, but reveals validation errors', () => {
    (globalThis as { window?: unknown }).window = { localStorage: { getItem: (key: string) => {
        assert.equal(key, 'sunrice:card:settings:general');
        return '0';
    } } };
    assert.match(card({ storageKey: 'settings:general' }), /aria-expanded="false"/);
    assert.match(card({ storageKey: 'settings:general', hasErrors: true }), /aria-expanded="true"/);
});

test('falls back to the default state when browser storage is blocked', () => {
    (globalThis as { window?: unknown }).window = { localStorage: { getItem: () => { throw new Error('Storage blocked'); } } };
    assert.match(card({ storageKey: 'settings:general' }), /aria-expanded="true"/);
});

test('keeps header actions outside the collapse button', () => {
    const html = card({ headerAction: createElement('button', { type: 'button' }, 'Toggle all') });
    assert.match(html, /<\/button>.*<button type="button">Toggle all<\/button>/);
    const firstButton = /<button[^>]*>[\s\S]*?<\/button>/.exec(html)?.[0] ?? '';
    assert.doesNotMatch(firstButton, /Toggle all/);
    // Before the fold chevron, which sits at the far edge.
    assert.match(html, /Toggle all<\/button><\/div><span aria-hidden="true"[^>]*><svg[^>]*lucide-chevron-down/);
});

test('read-only fieldsets never disable card toggles', async () => {
    for (const pkg of ['admin', 'cms']) {
        const directory = new URL(`../../packages/${pkg}/resources/js/`, import.meta.url);
        for (const file of (await readdir(directory, { recursive: true })).filter((name) => name.endsWith('.tsx'))) {
            const source = ts.createSourceFile(file, await readFile(new URL(file, directory), 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
            const walk = (node: ts.Node) => {
                if (ts.isJsxElement(node) && node.openingElement.tagName.getText(source) === 'CollapsibleCard') {
                    for (let parent = node.parent; parent; parent = parent.parent) {
                        if (!ts.isJsxElement(parent) || parent.openingElement.tagName.getText(source) !== 'fieldset') continue;
                        const disabled = parent.openingElement.attributes.properties.some((attribute) => attribute.name?.getText(source) === 'disabled');
                        assert.ok(!disabled, `${pkg}: ${file}: disable the fields inside the card, not its header`);
                    }
                }
                ts.forEachChild(node, walk);
            };
            walk(source);
        }
    }
});

test('says what is inside while folded, and the description while open', () => {
    const folded = card({ defaultOpen: false, description: 'Printed on invoices.', summary: 'Jl. Senopati 26 · Jakarta' });
    assert.match(folded, /Jl\. Senopati 26 · Jakarta/);
    assert.doesNotMatch(folded, /Printed on invoices\./);

    const open = card({ description: 'Printed on invoices.', summary: 'Jl. Senopati 26 · Jakarta' });
    assert.match(open, /Printed on invoices\./);
    assert.doesNotMatch(open, /Jl\. Senopati 26/);
});

test('sums up a card in one line from the values inside it', async () => {
    const { cardSummary } = await loadAdminModule<{ cardSummary: (values: unknown[], max?: number) => string }>('lib/card-summary.ts');

    assert.equal(cardSummary([{ street: 'Jl. Senopati 26', building: '', region: { city: 'Jakarta Selatan' }, postal: '12190' }]), 'Jl. Senopati 26 · Jakarta Selatan · 12190');
    assert.equal(cardSummary(['<p>Hello <b>there</b></p>', true, null, ['a', 'b']]), 'Hello there · 2 items');
    assert.equal(cardSummary(['a', 'b', 'c', 'd'], 2), 'a · b');
    assert.equal(cardSummary([false, '', [], {}]), '');
    assert.ok(cardSummary(['x'.repeat(80)]).endsWith('…'));
});
