import type { ComponentType, ReactNode } from 'react';
import { SDK_VERSION } from './shared.js';

export { SDK_VERSION } from './shared.js';

/** A page component; set `bare = true` for a page without the admin layout. */
export type PageComponent = ComponentType<any> & { layout?: (page: ReactNode) => ReactNode; bare?: boolean };

/** Loads a page on demand: `() => import('./pages/Orders/Index')` (its default export) or a function returning the component. */
export type PageLoader = () => Promise<PageComponent | { default: PageComponent }>;

/** What the panel exposes on `window.Sunrice` (internal: use the functions below). */
export interface SunriceRuntime {
    version: number;
    modules: Record<string, Record<string, unknown>>;
    registerPage(name: string, component: PageComponent): void;
    registerPageLoader(name: string, load: PageLoader): void;
    adminUrl(path?: string): string;
    t(text: string, replace?: Record<string, string | number | null | undefined>): string;
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
    /**
     * Pages loaded when first opened, by their Inertia name:
     * { 'Shop/Orders/Show': () => import('./pages/Orders/Show') }. Each
     * becomes a chunk next to the bundle, so a page's code loads only
     * where it's used.
     */
    lazyPages?: Record<string, PageLoader>;
}

/**
 * The entry point of a plugin bundle:
 *
 *     import { definePlugin } from '@imajiner/admin-sdk';
 *     import OrdersIndex from './pages/Orders/Index';
 *
 *     definePlugin({ pages: { 'Shop/Orders/Index': OrdersIndex } });
 *
 * or, loading each page only when it's opened:
 *
 *     definePlugin({ lazyPages: { 'Shop/Orders/Index': () => import('./pages/Orders/Index') } });
 *
 * Refuses to run in a panel with an incompatible runtime, with a clear
 * error instead of a broken page.
 */
export function definePlugin(definition: PluginDefinition): void {
    const sunrice = runtime();
    if (sunrice.version !== SDK_VERSION) {
        throw new Error(`[sunrice] This plugin was built for admin runtime ${SDK_VERSION}, but the panel runs ${sunrice.version}. Rebuild it with a matching @imajiner/admin-sdk.`);
    }
    for (const [name, component] of Object.entries(definition.pages ?? {})) {
        sunrice.registerPage(name, component);
    }
    for (const [name, load] of Object.entries(definition.lazyPages ?? {})) {
        sunrice.registerPageLoader(name, load);
    }
}

/**
 * The panel's words in the user's language: t('Save'), t('Delete :title?', { title }).
 * The English text is the key: ship lang/{code}.json with your plugin
 * (loadJsonTranslationsFrom) for other languages.
 */
export function t(text: string, replace: Record<string, string | number | null | undefined> = {}): string {
    return runtime().t(text, replace);
}

/** adminUrl('shop/orders') → '/admin/shop/orders', whatever path the panel lives under. */
export function adminUrl(path = ''): string {
    return runtime().adminUrl(path);
}
