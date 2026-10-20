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

let server: Promise<ViteDevServer> | undefined;

// Registered at import time, so the server outlives every test in the file.
after(async () => { await (await server)?.close(); });

function vite(): Promise<ViteDevServer> {
    server ??= createServer({
        configFile: false,
        root: fileURLToPath(new URL('../../../packages/admin', import.meta.url)),
        resolve: { alias: { '@': fileURLToPath(new URL('../../../packages/admin/resources/js', import.meta.url)) } },
        server: { middlewareMode: true, watch: null },
        appType: 'custom',
        logLevel: 'silent',
    });

    return server;
}

/** Loads a module of the panel's frontend by its path under resources/js. */
export async function loadAdminModule<T = Record<string, unknown>>(path: string): Promise<T> {
    return (await (await vite()).ssrLoadModule(`/resources/js/${path}`)) as T;
}

/** The static HTML of a component with these props. */
export function render<P extends object>(component: ComponentType<P>, props: P, ...children: unknown[]): string {
    return renderToStaticMarkup(createElement(component as ComponentType<any>, props as any, ...(children as any[])));
}
