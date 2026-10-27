import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ComponentType } from 'react';
import { loadCmsModule, render } from './support/render.ts';

type Module = { UnsavedNote: ComponentType<{ dirty: boolean }> };

test('"Unsaved changes" keeps its space when there are none, so the header doesn\'t shift', async () => {
    const { UnsavedNote } = await loadCmsModule<Module>('components/UnsavedNote.tsx');
    const clean = render(UnsavedNote, { dirty: false });
    const dirty = render(UnsavedNote, { dirty: true });

    assert.match(clean, /Unsaved changes/);
    assert.match(clean, /cms:invisible/);
    assert.match(clean, /aria-hidden="true"/);
    assert.doesNotMatch(dirty, /cms:invisible/);
    assert.match(dirty, /aria-hidden="false"/);
});
