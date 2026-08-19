import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { route as routeFn, type Config as ZiggyConfig } from 'ziggy-js';

/**
 * The server-side rendering entry point.
 *
 * Article text currently reaches the browser only inside the `data-page` JSON,
 * which is fine for a scraper reading OG tags and useless for anything reading
 * the prose. This renders the same components to HTML first, so the words are
 * in the document.
 *
 * Two things differ from the browser entry and both matter:
 *
 * - **No CSS import.** `app.css` is bundled by the client build; importing it
 *   here would have Node try to parse CSS.
 * - **No theme initialisation.** `initializeTheme()` touches localStorage and
 *   the document, neither of which exists here. The universe's own tokens are
 *   inline styles from server props, so a server-rendered page is correctly
 *   themed regardless.
 */

const appName = import.meta.env.VITE_APP_NAME || 'Inkfathom';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
        setup: ({ App, props }) => {
            /*
             * Ziggy's route() reads window.location in the browser. On the
             * server that location has to come from the page payload instead,
             * or the first component to call route() takes the whole render
             * down — and several layouts call it for the home link alone.
             *
             * `route` is declared as a global const for the client entry, so it
             * is assigned through globalThis here rather than redeclared.
             */
            const ziggy = (page.props as { ziggy?: ZiggyConfig & { location: string } }).ziggy;

            (globalThis as Record<string, unknown>).route = (name: string, params?: unknown, absolute?: boolean) =>
                routeFn(
                    name as never,
                    params as never,
                    absolute,
                    ziggy ? ({ ...ziggy, location: new URL(ziggy.location) } as unknown as ZiggyConfig) : undefined,
                );

            return <App {...props} />;
        },
    }),
);
