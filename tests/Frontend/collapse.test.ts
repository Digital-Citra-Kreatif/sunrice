import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import type { ComponentType } from 'react';
import { loadAdminModule, render } from './support/render.ts';

const { CollapseRegion, CollapseToggle } = await loadAdminModule<{ CollapseRegion: ComponentType<any>; CollapseToggle: ComponentType<any> }>('components/app/collapse.tsx');

test('a dashboard card\'s toggle says what it does and what it folds', () => {
    const open = render(CollapseToggle, { open: true, controls: 'w-body', describedBy: 'w-title', onToggle: () => undefined });
    assert.match(open, /type="button" aria-expanded="true" aria-controls="w-body" aria-describedby="w-title" aria-label="Collapse"/);
    const closed = render(CollapseToggle, { open: false, controls: 'w-body', onToggle: () => undefined });
    assert.match(closed, /aria-expanded="false"/);
    assert.match(closed, /aria-label="Expand"/);
    assert.match(closed, /-rotate-90/);
    assert.match(closed, /motion-reduce:transition-none/);
});

test('a folded region stays mounted, inert and hidden from assistive technology', () => {
    const open = render(CollapseRegion, { id: 'w-body', open: true }, 'Rows');
    assert.match(open, /id="w-body" data-state="open" class="sunrice-collapse"/);
    assert.doesNotMatch(open, /inert/);
    const closed = render(CollapseRegion, { id: 'w-body', open: false }, 'Rows');
    assert.match(closed, /data-state="closed"/);
    assert.match(closed, /inert="" aria-hidden="true">Rows/);
});

test('folding animates, except for people who asked for less motion', async () => {
    const css = await readFile(new URL('../../packages/admin/resources/css/admin.css', import.meta.url), 'utf8');
    assert.match(css, /\.sunrice-collapse \{[^}]*transition: grid-template-rows 180ms/);
    assert.match(css, /\.sunrice-collapsible\[data-state="open"\] \{\s*animation: sunrice-collapsible-open/);
    const reduced = css.slice(css.indexOf('@media (prefers-reduced-motion: reduce)'));
    assert.match(reduced, /\.sunrice-collapse \{\s*transition: none;/);
    assert.match(reduced, /\.sunrice-collapsible\[data-state\] \{\s*animation: none;/);

    const sidebar = await readFile(new URL('../../packages/admin/resources/js/components/app/app-sidebar.tsx', import.meta.url), 'utf8');
    assert.equal(sidebar.match(/<CollapsibleContent className="sunrice-collapsible">/g)?.length, 2, 'the sidebar\'s groups and submenus animate');
});
