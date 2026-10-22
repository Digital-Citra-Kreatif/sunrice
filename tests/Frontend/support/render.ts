/*
 * Renders the panel's React components to static HTML without a browser:
 * Vite loads the .tsx (the `@/` alias and all) and react-dom/server renders
 * it. Enough to check markup, attributes and ARIA; not events.
 */
import { after } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createElement, type ComponentType } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer, type ViteDevServer } from 'vite';

const servers = new Map<string, Promise<ViteDevServer>>();

// Registered at import time, so the servers outlive every test in the file.
after(async () => { await Promise.all([...servers.values()].map(async (server) => (await server).close())); });

/** A Vite server for one package's frontend (its `@/` alias points at its own resources/js). */
function vite(pkg: 'admin' | 'cms'): Promise<ViteDevServer> {
    let server = servers.get(pkg);
    if (!server) {
        server = createServer({
            configFile: false,
            root: fileURLToPath(new URL(`../../../packages/${pkg}`, import.meta.url)),
            resolve: { alias: { '@': fileURLToPath(new URL(`../../../packages/${pkg}/resources/js`, import.meta.url)) } },
            server: { middlewareMode: true, watch: null },
            appType: 'custom',
            logLevel: 'silent',
        });
        servers.set(pkg, server);
    }

    return server;
}

/** Loads a module of the panel's frontend by its path under resources/js. */
export async function loadAdminModule<T = Record<string, unknown>>(path: string): Promise<T> {
    return (await (await vite('admin')).ssrLoadModule(`/resources/js/${path}`)) as T;
}

/**
 * Loads a module of the CMS's admin pages by its path under resources/js.
 * Its `@imajiner/admin-sdk/ui` imports read the panel's runtime, so this
 * installs it (window.Sunrice) first.
 */
export async function loadCmsModule<T = Record<string, unknown>>(path: string): Promise<T> {
    if (!(globalThis as { Sunrice?: unknown }).Sunrice) {
        (await loadAdminModule<{ installRuntime: () => void }>('runtime.ts')).installRuntime();
    }

    return (await (await vite('cms')).ssrLoadModule(`/resources/js/${path}`)) as T;
}

/** The static HTML of a component with these props. */
export function render<P extends object>(component: ComponentType<P>, props: P, ...children: unknown[]): string {
    return renderToStaticMarkup(createElement(component as ComponentType<any>, props as any, ...(children as any[])));
}
