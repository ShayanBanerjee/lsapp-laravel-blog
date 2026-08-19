import react from '@vitejs/plugin-react';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

// This config is ESM, so __dirname does not exist; derive it from import.meta.
const projectRoot = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            /*
             * Ziggy ships its JavaScript inside the Composer package rather
             * than as an npm dependency. The browser normally gets it from the
             * @routes Blade directive, but the SSR bundle has to import it —
             * and aliasing the vendored copy keeps the PHP and JS halves on the
             * same version, which a separate npm install would not.
             */
            'ziggy-js': resolve(projectRoot, 'vendor/tightenco/ziggy/dist'),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
});