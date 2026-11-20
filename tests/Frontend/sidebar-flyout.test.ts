import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { createElement, type ComponentType } from 'react';
import { loadAdminModule, render } from './support/render.ts';

const { NavFlyout } = await loadAdminModule<{ NavFlyout: ComponentType<any> }>('components/app/app-sidebar.tsx');
const { SidebarProvider } = await loadAdminModule<{ SidebarProvider: ComponentType<any> }>('components/ui/sidebar.tsx');

test('a submenu folded to its icon opens as a menu beside it', () => {
    const Flyout = () => createElement(SidebarProvider, { defaultOpen: false }, createElement(NavFlyout, {
        label: 'Orders', active: true, icon: null,
        links: [{ key: 'orders', label: 'Orders', url: 'commerce/orders', current: true }, { key: 'carts', label: 'Abandoned carts', url: 'commerce/carts', current: false }],
    }));
    const html = render(Flyout, {});
    assert.match(html, /aria-haspopup="menu"/);
    assert.match(html, /aria-label="Orders"/);
    assert.match(html, /data-active="true"/);
});

test('the sidebar uses the flyout only while folded to icons on desktop', async () => {
    const sidebar = await readFile(new URL('../../packages/admin/resources/js/components/app/app-sidebar.tsx', import.meta.url), 'utf8');
    assert.match(sidebar, /const iconsOnly = state === 'collapsed' && !isMobile;/);
    assert.match(sidebar, /if \(iconsOnly\) \{[\s\S]*<NavFlyout/);
    assert.match(sidebar, /side="right"/);
});
