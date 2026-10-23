import type * as React from 'react';
import { bundleFor } from '@/lib/bundles';
import type { BundleInfo } from '@/types';

/** A page component; `bare = true` renders it without the admin layout (a login page). */
export type PageComponent = React.ComponentType<any> & {
    layout?: (page: React.ReactNode) => React.ReactNode;
    bare?: boolean;
};

/** Loads a plugin page on demand (definePlugin({ lazyPages })): a module with the page as default export, or the page. */
export type PageLoader = () => Promise<PageComponent | { default: PageComponent }>;

const pages = new Map<string, PageComponent>();
const loaders = new Map<string, PageLoader>();
const loading = new Map<string, Promise<void>>();
const loadingPages = new Map<string, Promise<PageComponent>>();

function warnTwice(name: string): void {
    if (pages.has(name) || loaders.has(name)) {
        console.warn(`[sunrice] The page "${name}" was registered twice; the second registration wins.`);
    }
}

/** Called by plugin bundles (through @imajiner/admin-sdk). */
export function registerPage(name: string, component: PageComponent): void {
    warnTwice(name);
    loaders.delete(name);
    pages.set(name, component);
}

/** A page loaded the first time it opens (through @imajiner/admin-sdk's lazyPages). */
export function registerPageLoader(name: string, load: PageLoader): void {
    warnTwice(name);
    pages.delete(name);
    loaders.set(name, load);
}

/** A lazy page's component, loaded once (again after a failure, on the next visit). */
function loadPage(name: string, load: PageLoader): Promise<PageComponent> {
    let promise = loadingPages.get(name);
    if (!promise) {
        promise = load().then((loaded) => {
            const page = typeof loaded === 'object' && loaded !== null && 'default' in loaded ? loaded.default : loaded;
            // A function component, or memo()/forwardRef()'s object.
            if (typeof page !== 'function' && (typeof page !== 'object' || page === null || !('$$typeof' in page))) {
                throw new Error(`[sunrice] The page "${name}" loaded, but its module has no component as default export.`);
            }
            pages.set(name, page);
            loaders.delete(name);

            return page;
        });
        promise.catch(() => loadingPages.delete(name));
        loadingPages.set(name, promise);
    }

    return promise;
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
    if (!pages.has(name) && !loaders.has(name)) {
        const bundle = bundleFor(name, bundles);
        if (!bundle) {
            throw new Error(`[sunrice] No page "${name}": it isn't one of the panel's pages and no plugin bundle claims it (PluginBundle::pages()).`);
        }
        await load(bundle);
    }
    const loader = loaders.get(name);
    const page = pages.get(name) ?? (loader ? await loadPage(name, loader) : undefined);
    if (!page) {
        throw new Error(`[sunrice] The bundle for "${name}" loaded but didn't register that page (definePlugin({ pages: { '${name}': … } }) or lazyPages).`);
    }

    return page;
}
