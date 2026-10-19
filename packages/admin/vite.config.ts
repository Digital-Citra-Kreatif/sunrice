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
        rollupOptions: { input: 'resources/js/app.tsx' },
    },
});
