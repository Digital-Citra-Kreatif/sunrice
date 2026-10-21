import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// The latin woff2 file of a weight and style from an @fontsource package (no third-party request from visitors'
// browsers). One file per face: the plugin's fontsource provider declared every face twice (woff2, then woff with the
// same descriptors), so browsers downloaded the preloaded woff2 files and then used the woff ones.
const woff2 = (font, weight, style = 'normal') => ({
    src: `node_modules/@fontsource/${font}/files/${font}-latin-${weight}-${style}.woff2`,
    weight,
    style,
});

// The site's front end: one stylesheet and one small script, fonts self-hosted at build time. Printed in the layout
// with @vite([...]) and @fonts.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'lang/**'],
            fonts: [
                local('Cormorant Garamond', {
                    alias: 'serif',
                    variable: '--font-serif',
                    variants: [500, 600].flatMap((weight) => ['normal', 'italic'].map((style) => woff2('cormorant-garamond', weight, style))),
                    preload: [{ weight: 500 }],
                    fallbacks: ['Georgia', 'serif'],
                }),
                local('Manrope', {
                    alias: 'sans',
                    variable: '--font-sans',
                    variants: [400, 500, 600, 700].map((weight) => woff2('manrope', weight)),
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
