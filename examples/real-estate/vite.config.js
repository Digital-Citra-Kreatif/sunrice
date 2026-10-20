import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// The site's front end: one stylesheet and one small script, fonts self-hosted at build time
// from the @fontsource npm packages (no third-party request from visitors' browsers). Printed in the layout with
// @vite([...]) and @fonts.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'lang/**'],
            fonts: [
                fontsource('Cormorant Garamond', {
                    alias: 'serif',
                    variable: '--font-serif',
                    weights: [500, 600],
                    styles: ['normal', 'italic'],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 500 }],
                    fallbacks: ['Georgia', 'serif'],
                }),
                fontsource('Manrope', {
                    alias: 'sans',
                    variable: '--font-sans',
                    weights: [400, 500, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 400 }, { weight: 600 }],
                    fallbacks: ['system-ui', 'sans-serif'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
