import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/trip-map.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            // Tanpa vendor/storage/public/build Vite memantau ±17 ribu berkas (±1 inti CPU saat diam, CSS 1-10 detik); dengan ini ±1,4 ribu. Tailwind tetap membaca @source sendiri.
            ignored: ['**/storage/**', '**/vendor/**', '**/public/build/**'],
        },
    },
});
