import { posix } from 'node:path';
import type { Plugin, Rollup, UserConfig } from 'vite';
import { SHARED_MODULES, type SharedModuleName } from './shared.js';

const PREFIX = '\0sunrice-shared:';

export interface SunriceOptions {
    /** The bundle's entry file, which calls definePlugin(). */
    entry: string;
    /** Where to write the bundle; "dist" by default. */
    outDir?: string;
    /** The output file name without extension; "admin" by default (dist/admin.js). */
    fileName?: string;
}

// Added to a chunk that loads others: puts <link rel="modulepreload"> in for a loaded chunk's imports, so
// they download alongside it instead of after it. Library builds have no preloading of their own.
const PRELOAD_HELPER = `function __sunricePreload(files) {
    if (typeof document === 'undefined') return;
    for (const file of files) {
        const href = new URL(file, import.meta.url).href;
        if (document.querySelector('link[rel="modulepreload"][href="' + CSS.escape(href) + '"]')) continue;
        const link = document.createElement('link');
        link.rel = 'modulepreload';
        link.href = href;
        document.head.appendChild(link);
    }
}`;

/** The chunks a chunk imports, directly or through others. */
function staticClosure(file: string, chunks: Map<string, Rollup.OutputChunk>, seen = new Set<string>()): Set<string> {
    for (const imported of chunks.get(file)?.imports ?? []) {
        if (!seen.has(imported)) {
            seen.add(imported);
            staticClosure(imported, chunks, seen);
        }
    }

    return seen;
}

/** import('./Page.js') → (__sunricePreload([its imports]), import('./Page.js')), for imports not already loaded. */
function preloadDynamicImports(chunk: Rollup.OutputChunk, chunks: Map<string, Rollup.OutputChunk>): void {
    const loaded = staticClosure(chunk.fileName, chunks);
    const directory = posix.dirname(chunk.fileName);
    let changed = false;
    chunk.code = chunk.code.replace(/\bimport\((["'])([^"']+\.js)\1\)/g, (call, _quote: string, path: string) => {
        const target = posix.join(directory, path);
        if (!chunks.has(target)) return call;
        const files = [...staticClosure(target, chunks)].filter((file) => !loaded.has(file) && file !== chunk.fileName);
        if (files.length === 0) return call;
        changed = true;
        const relative = files.map((file) => {
            const path = posix.relative(directory, file);
            return path.startsWith('.') ? path : `./${path}`;
        });

        return `(__sunricePreload(${JSON.stringify(relative)}), ${call})`;
    });
    if (changed) {
        chunk.code += `\n${PRELOAD_HELPER}\n`;
    }
}

function isShared(id: string): id is SharedModuleName {
    return Object.prototype.hasOwnProperty.call(SHARED_MODULES, id);
}

/**
 * Builds a plugin's admin bundle as an ES module that uses the panel's
 * React, Inertia and UI components (window.Sunrice.modules) instead of its
 * own copies:
 *
 *     // vite.config.ts
 *     import react from '@vitejs/plugin-react';
 *     import { sunrice } from '@imajiner/admin-sdk/vite';
 *
 *     export default { plugins: [react(), sunrice({ entry: 'resources/js/admin.tsx' })] };
 *
 * A bundle without dynamic imports is one file, dist/admin.js. One that
 * splits (lazyPages, `import()`, React.lazy) puts its code in
 * dist/chunks/, named by content hash, and dist/admin.js is a few lines
 * importing the main chunk: the panel loads admin.js with a version query
 * (`admin.js?v=…`), and chunks must import the main one by a URL that's
 * the same for everyone, or it would run twice.
 */
export function sunrice(options: SunriceOptions): Plugin {
    const name = options.fileName ?? 'admin';

    return {
        name: 'sunrice-admin-sdk',
        enforce: 'pre',
        config(_config, env): UserConfig {
            return {
                // Library builds leave process.env.NODE_ENV in dependencies (React
                // libraries, ProseMirror…), and browsers have no `process`.
                define: { 'process.env.NODE_ENV': JSON.stringify(env.mode === 'development' ? 'development' : 'production') },
                build: {
                    outDir: options.outDir ?? 'dist',
                    emptyOutDir: true,
                    lib: { entry: options.entry, formats: ['es'], fileName: () => `chunks/${name}-[hash].js` },
                    cssCodeSplit: false,
                    rollupOptions: { output: { assetFileNames: `${name}.[ext]`, chunkFileNames: 'chunks/[name]-[hash].js' } },
                },
            };
        },
        resolveId(id) {
            return isShared(id) ? PREFIX + id : null;
        },
        load(id) {
            if (!id.startsWith(PREFIX)) return null;
            const name = id.slice(PREFIX.length) as SharedModuleName;

            // Named imports (import { useState } from 'react') become property
            // reads on the panel's module at the place they're used, so
            // nothing unused ends up in the bundle.
            return {
                code: [
                    `const s = globalThis.Sunrice;`,
                    `if (!s) throw new Error('[sunrice] This bundle needs the Sunrice admin panel (window.Sunrice is missing).');`,
                    `export default s.modules[${JSON.stringify(name)}];`,
                ].join('\n'),
                syntheticNamedExports: 'default',
            };
        },
        generateBundle(_output, bundle) {
            const chunks = Object.values(bundle).filter((file): file is Rollup.OutputChunk => file.type === 'chunk');
            const entry = chunks.find((chunk) => chunk.isEntry);
            if (!entry) return;
            const file = `${name}.js`;

            // Nothing split off: the entry is the whole bundle, under its plain name.
            if (chunks.length === 1) {
                delete bundle[entry.fileName];
                entry.fileName = file;
                bundle[file] = entry;

                return;
            }

            const byFile = new Map(chunks.map((chunk) => [chunk.fileName, chunk]));
            for (const chunk of chunks) {
                if (chunk.dynamicImports.length > 0) preloadDynamicImports(chunk, byFile);
            }

            // The main chunk's own imports are listed too, so the browser fetches them all at once.
            this.emitFile({
                type: 'asset',
                fileName: file,
                source: [...entry.imports.map((chunk) => `import './${chunk}';`), `export * from './${entry.fileName}';`, ''].join('\n'),
            });
        },
    };
}
