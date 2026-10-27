import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createElement, type ComponentType, type Context } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { loadCmsModule, render } from './support/render.ts';

type Presentation = { MainLanguage: Context<string | null> };

const hint = /<p id="([^"]+)-shared" class="cms:text-sm cms:text-muted-foreground">Not translatable: edit it in English\.<\/p>/g;

async function inEnglish(component: ComponentType<any>, props: object): Promise<string> {
    const { MainLanguage } = await loadCmsModule<Presentation>('components/FieldPresentation.tsx');

    return renderToStaticMarkup(createElement(MainLanguage.Provider, { value: 'English' }, createElement(component, props)));
}

test('a shared field says under it, after its helper text, where to edit it', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const field = { handle: 'price', label: 'Price', type: 'number', instructions: 'Before tax.' };
    const html = await inEnglish(FieldInput, { field, value: 10, onChange: () => undefined, errors: {}, id: 'data-price', shared: true });

    assert.match(html, /Before tax\.<\/p><p id="data-price-shared"[^>]*>Not translatable: edit it in English\.<\/p>/);
    assert.match(html, /id="data-price"[^>]*aria-describedby="data-price-help data-price-shared"/);
});

test('a field that is not shared has no such hint', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const html = await inEnglish(FieldInput, { field: { handle: 'title', label: 'Title', type: 'text' }, value: '', onChange: () => undefined, errors: {}, id: 'data-title' });

    assert.doesNotMatch(html, /Not translatable/);
});

test('without the main language named, the hint says "the main language"', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const html = render(FieldInput, { field: { handle: 'price', label: 'Price', type: 'number' }, value: 10, onChange: () => undefined, errors: {}, id: 'data-price', shared: true });

    assert.match(html, /Not translatable: edit it in the main language\./);
});

test('inside a translated group, only the fields the server marks shared have the hint', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const field = { handle: 'venue', label: 'Venue', type: 'group', fields: [
        { handle: 'name', label: 'Name', type: 'text' },
        { handle: 'capacity', label: 'Capacity', type: 'number', shared: true },
    ] };
    const html = await inEnglish(FieldInput, { field, value: { name: 'Hall', capacity: 200 }, onChange: () => undefined, errors: {}, id: 'data-venue', content: true });

    assert.deepEqual([...html.matchAll(hint)].map((match) => match[1]), ['data-venue-capacity']);
});

test('a group shared as a whole says it once, under its heading, not under each of its fields', async () => {
    const { FieldSections } = await loadCmsModule<{ FieldSections: ComponentType<any> }>('components/FieldSections.tsx');
    const fields = [
        { handle: 'title', label: 'Title', type: 'text' },
        { handle: 'price', label: 'Price', type: 'number' },
        { handle: 'venue', label: 'Venue', type: 'group', fields: [{ handle: 'name', type: 'text' }, { handle: 'capacity', type: 'number' }] },
    ];
    const html = render(FieldSections, { fields, values: { title: 'Hello' }, onChange: () => undefined, errors: {}, storageKey: 'test',
        shared: ['price', 'venue'], sharedValues: { price: 10, venue: { name: 'Hall', capacity: 200 } }, mainLanguage: 'English' });

    assert.equal((html.match(/Not translatable: edit it in English\./g) ?? []).length, 2, 'the price and the group, once');
    assert.match(html, /id="data-price-shared"/);
    assert.doesNotMatch(html, /data-venue-(name|capacity)-shared/);
    assert.doesNotMatch(html, /role="note"/, 'no note at the top any more');
});

test('the main language shows no hint', async () => {
    const { FieldSections } = await loadCmsModule<{ FieldSections: ComponentType<any> }>('components/FieldSections.tsx');
    const html = render(FieldSections, { fields: [{ handle: 'price', label: 'Price', type: 'number' }], values: { price: 10 }, onChange: () => undefined, errors: {}, storageKey: 'test' });

    assert.doesNotMatch(html, /Not translatable/);
});
