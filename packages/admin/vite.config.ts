import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    // Chunks load relative to the entry script, so the assets work wherever
    // they are published (public/vendor/sunrice-admin, a CDN via ASSET_URL…).
    base: './',
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: { '@': resolve(__dirname, 'resources/js') },
    },
    build: {
        outDir: 'dist',
        manifest: 'manifest.json',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/js/app.tsx',
            output: {
                // CodeMirror's core in one named chunk (each language's grammar stays a chunk of its own), loaded only by the code editor.
                // The small helpers it shares with TipTap (key names…) apart, so the rich text editor never loads CodeMirror.
                manualChunks: (id) => {
                    if (/node_modules\/(w3c-keyname|crelt|style-mod)\//.test(id)) return 'editor-helpers';

                    return /node_modules\/(@codemirror\/(state|view|language|commands)|@lezer\/(common|highlight|lr))\//.test(id) ? 'codemirror' : undefined;
                },
            },
        },
    },
});
