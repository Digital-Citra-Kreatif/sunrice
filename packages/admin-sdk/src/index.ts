import type { ComponentType, ReactNode } from 'react';
import { SDK_VERSION } from './shared.js';

export { SDK_VERSION } from './shared.js';

/** A page component; set `bare = true` for a page without the admin layout. */
export type PageComponent = ComponentType<any> & { layout?: (page: ReactNode) => ReactNode; bare?: boolean };

/** What the panel exposes on `window.Sunrice` (internal: use the functions below). */
export interface SunriceRuntime {
    version: number;
    modules: Record<string, Record<string, unknown>>;
    registerPage(name: string, component: PageComponent): void;
    adminUrl(path?: string): string;
}

declare global {
    // eslint-disable-next-line no-var
    var Sunrice: SunriceRuntime | undefined;
}

function runtime(): SunriceRuntime {
    const sunrice = globalThis.Sunrice;
    if (!sunrice) {
        throw new Error('[sunrice] This script runs outside the Sunrice admin panel (window.Sunrice is missing).');
    }

    return sunrice;
}

export interface PluginDefinition {
    /** Page components by their Inertia name: { 'Shop/Orders/Index': OrdersIndex }. */
    pages?: Record<string, PageComponent>;
}

/**
 * The entry point of a plugin bundle:
 *
 *     import { definePlugin } from '@sunrice/admin-sdk';
 *     import OrdersIndex from './pages/Orders/Index';
 *
 *     definePlugin({ pages: { 'Shop/Orders/Index': OrdersIndex } });
 *
 * Refuses to run in a panel with an incompatible runtime, with a clear
 * error instead of a broken page.
 */
export function definePlugin(definition: PluginDefinition): void {
    const sunrice = runtime();
    if (sunrice.version !== SDK_VERSION) {
        throw new Error(`[sunrice] This plugin was built for admin runtime ${SDK_VERSION}, but the panel runs ${sunrice.version}. Rebuild it with a matching @sunrice/admin-sdk.`);
    }
    for (const [name, component] of Object.entries(definition.pages ?? {})) {
        sunrice.registerPage(name, component);
    }
}

/** adminUrl('shop/orders') → '/admin/shop/orders', whatever path the panel lives under. */
export function adminUrl(path = ''): string {
    return runtime().adminUrl(path);
}
