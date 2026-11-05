import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createElement, type ComponentType } from 'react';
import { loadCmsModule, render } from './support/render.ts';

type Inputs = typeof import('../../packages/cms/resources/js/lib/field-inputs.ts');

test('draws a plugin field type with the input it registered, with the label and errors around it', async () => {
    const { registerFieldInput, installCmsApi } = await loadCmsModule<Inputs>('lib/field-inputs.ts');
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    let seen: Record<string, unknown> = {};
    registerFieldInput('shop_rating', (props) => {
        seen = props as unknown as Record<string, unknown>;
        return createElement('output', { id: props.id, 'aria-describedby': props.describedBy }, `${String(props.value)} stars`);
    });

    const html = render(FieldInput, { field: { handle: 'rating', label: 'Rating', type: 'shop_rating', config: { max: 5 } }, value: 4, onChange: () => undefined,
        errors: { '': 'Too many stars.', 'nested.0': 'Inner.' }, id: 'data-rating' });

    assert.match(html, /<label[^>]*>Rating<\/label>/);
    assert.match(html, /<output id="data-rating"[^>]*>4 stars<\/output>/);
    assert.match(html, /Too many stars\./);
    assert.deepEqual((seen.field as { config: unknown }).config, { max: 5 });
    assert.deepEqual(seen.errors, { '': 'Too many stars.', 'nested.0': 'Inner.' });

    // Other plugins' bundles queue their calls on globalThis.SunriceCmsQueue: run when the CMS's bundle runs, at once after.
    const scope = globalThis as { SunriceCms?: { version: number }; SunriceCmsQueue?: Array<(cms: { registerFieldInput: typeof registerFieldInput }) => void> };
    const ran: string[] = [];
    (scope.SunriceCmsQueue ??= []).push(() => ran.push('before'));
    installCmsApi();
    scope.SunriceCmsQueue.push(() => ran.push('after'));
    assert.equal(scope.SunriceCms?.version, 1);
    assert.deepEqual(ran, ['before', 'after']);
});

test('without a registered input, an unknown type is a text input', async () => {
    const { FieldInput } = await loadCmsModule<{ FieldInput: ComponentType<any> }>('components/FieldInput.tsx');
    const html = render(FieldInput, { field: { handle: 'code', label: 'Code', type: 'shop_unknown' }, value: 'A1', onChange: () => undefined, errors: {}, id: 'data-code' });

    assert.match(html, /<input[^>]*id="data-code"[^>]*value="A1"/);
});
