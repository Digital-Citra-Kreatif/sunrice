import type { Plugin, UserConfig } from 'vite';
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
 */
export function sunrice(options: SunriceOptions): Plugin {
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
                    lib: { entry: options.entry, formats: ['es'], fileName: () => `${options.fileName ?? 'admin'}.js` },
                    cssCodeSplit: false,
                    rollupOptions: { output: { assetFileNames: `${options.fileName ?? 'admin'}.[ext]` } },
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
    };
}
