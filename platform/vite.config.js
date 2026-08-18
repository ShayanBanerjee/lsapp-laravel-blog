import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            /*
             * The Ziggy JS client ships with the composer package, not npm.
             * The browser gets `route()` as a global from the @routes
             * directive, so the client bundle never has to resolve this — but
             * the SSR bundle has no such global and must import the real
             * module, which needs the alias to be found at all.
             */
            'ziggy-js': path.resolve('vendor/tightenco/ziggy'),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
});