import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            publicDirectory: 'dist',
            buildDirectory: '.',
            hotFile: 'dist/hot',
            refresh: false,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': resolve(__dirname, 'resources/js') },
    },
    build: {
        outDir: 'dist',
        manifest: 'manifest.json',
        emptyOutDir: true,
    },
});
