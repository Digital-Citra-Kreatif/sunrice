import type * as React from 'react';
import { bundleFor } from '@/lib/bundles';
import type { BundleInfo } from '@/types';

/** A page component; `bare = true` renders it without the admin layout (a login page). */
export type PageComponent = React.ComponentType<any> & {
    layout?: (page: React.ReactNode) => React.ReactNode;
    bare?: boolean;
};

const pages = new Map<string, PageComponent>();
const loading = new Map<string, Promise<void>>();

/** Called by plugin bundles (through @sunrice/admin-sdk). */
export function registerPage(name: string, component: PageComponent): void {
    if (pages.has(name)) {
        console.warn(`[sunrice] The page "${name}" was registered twice; the second registration wins.`);
    }
    pages.set(name, component);
}

function loadStyle(href: string): void {
    if (document.querySelector(`link[rel="stylesheet"][href="${CSS.escape(href)}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

/** Load a bundle's module (once) and its styles. */
function load(bundle: BundleInfo): Promise<void> {
    let promise = loading.get(bundle.id);
    if (!promise) {
        bundle.styles.forEach(loadStyle);
        promise = bundle.module === null
            ? Promise.resolve()
            : import(/* @vite-ignore */ bundle.module).then(() => undefined);
        // A failed load can be retried on the next visit.
        promise.catch(() => loading.delete(bundle.id));
        loading.set(bundle.id, promise);
    }

    return promise;
}

/** A plugin's page: loads the bundle that provides it on first use. */
export async function pluginPage(name: string, bundles: BundleInfo[]): Promise<PageComponent> {
    if (!pages.has(name)) {
        const bundle = bundleFor(name, bundles);
        if (!bundle) {
            throw new Error(`[sunrice] No page "${name}": it isn't one of the panel's pages and no plugin bundle claims it (PluginBundle::pages()).`);
        }
        await load(bundle);
    }
    const page = pages.get(name);
    if (!page) {
        throw new Error(`[sunrice] The bundle for "${name}" loaded but didn't register that page (definePlugin({ pages: { '${name}': … } })).`);
    }

    return page;
}
