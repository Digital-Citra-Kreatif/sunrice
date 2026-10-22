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

/** Called by plugin bundles (through @imajiner/admin-sdk). */
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

function preloadModule(href: string): void {
    if (document.querySelector(`link[rel="modulepreload"][href="${CSS.escape(href)}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'modulepreload';
    link.href = href;
    document.head.appendChild(link);
}

let runtime: Promise<void> | null = null;

/**
 * window.Sunrice, which plugin bundles read as they load: installed with
 * the first plugin page, so the panel's own pages don't load what only
 * plugins use.
 */
function installRuntime(): Promise<void> {
    runtime ??= import('@/runtime').then((module) => module.installRuntime());
    runtime.catch(() => (runtime = null));

    return runtime;
}

/** Load a bundle's module (once, after the runtime it needs, while fetching both) and its styles. */
function load(bundle: BundleInfo): Promise<void> {
    let promise = loading.get(bundle.id);
    if (!promise) {
        bundle.styles.forEach(loadStyle);
        const module = bundle.module;
        if (module !== null) {
            preloadModule(module);
        }
        promise = module === null
            ? Promise.resolve()
            : installRuntime().then(() => import(/* @vite-ignore */ module)).then(() => undefined);
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
