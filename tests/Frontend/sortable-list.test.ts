import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createElement, type ComponentType } from 'react';
import { moveItem } from '../../packages/admin/resources/js/lib/move-item.ts';
import { loadAdminModule, render } from './support/render.ts';

test('moves an item to another position', () => {
    assert.deepEqual(moveItem(['a', 'b', 'c', 'd'], 0, 2), ['b', 'c', 'a', 'd']);
    assert.deepEqual(moveItem(['a', 'b', 'c', 'd'], 3, 0), ['d', 'a', 'b', 'c']);
    assert.deepEqual(moveItem(['a', 'b', 'c'], 1, 2), ['a', 'c', 'b']);
});

test('leaves the list as it was for a move to the same place or out of range, and never mutates it', () => {
    const items = ['a', 'b', 'c'];
    assert.deepEqual(moveItem(items, 1, 1), ['a', 'b', 'c']);
    assert.deepEqual(moveItem(items, -1, 1), ['a', 'b', 'c']);
    assert.deepEqual(moveItem(items, 0, 3), ['a', 'b', 'c']);
    assert.notEqual(moveItem(items, 0, 1), items);
    assert.deepEqual(items, ['a', 'b', 'c']);
});

test('renders a sortable row per item with a keyboard-reachable handle', async () => {
    const { SortableList, SortableHandle } = await loadAdminModule<{ SortableList: ComponentType<any>; SortableHandle: ComponentType<any> }>('components/app/sortable-list.tsx');
    const items = [{ id: 'first', title: 'First' }, { id: 'second', title: 'Second' }];
    const html = render(SortableList, {
        items,
        getId: (item: { id: string }) => item.id,
        onReorder: () => undefined,
        children: (item: { title: string }, state: { handle: object; index: number }) =>
            createElement('div', null, createElement(SortableHandle, state.handle), `${state.index + 1}. ${item.title}`),
    });

    assert.match(html, /<ul[^>]*>(<li[^>]*>[\s\S]*?<\/li>){2}<\/ul>/);
    assert.match(html, /data-sortable-item="first"/);
    assert.match(html, /data-sortable-item="second"/);
    assert.equal((html.match(/aria-roledescription="sortable"/g) ?? []).length, 2);
    assert.match(html, /<button type="button" aria-label="Reorder"[^>]*role="button"[^>]*tabindex="0"/);
    assert.match(html, /1\. First[\s\S]*2\. Second/);
});

test('a disabled list keeps its rows but takes the handles out of the tab order', async () => {
    const { SortableList } = await loadAdminModule<{ SortableList: ComponentType<any> }>('components/app/sortable-list.tsx');
    const html = render(SortableList, {
        items: ['a'],
        getId: (item: string) => item,
        onReorder: () => undefined,
        disabled: true,
        children: (item: string, state: { handle: object }) => createElement('button', { type: 'button', ...state.handle }, item),
    });

    assert.match(html, /<button type="button"[^>]*disabled=""[^>]*>a<\/button>/);
    assert.match(html, /tabindex="-1"/);
});
