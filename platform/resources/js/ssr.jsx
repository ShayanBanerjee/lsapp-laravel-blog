/* prettier-ignore */
import {
createInertiaApp
} from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { route as routeFn } from 'ziggy-js';

const appName = process.env.VITE_APP_NAME || 'Inkfathom';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        // Must match app.tsx exactly, or the server-rendered <title> differs
        // from the one the client produces on hydration.
        title: (title) => `${title} - ${appName}`,
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', {
                eager: true,
            });
            return pages[`./pages/${name}.tsx`];
        },
        setup: ({ App, props }) => {
            /*
             * `route()` is a browser global in the client bundle, injected by
             * the @routes Blade directive. Node has no such global, so without
             * this every page that calls route() throws during server render.
             * The config arrives as a shared prop; `location` becomes a URL so
             * Ziggy can answer route().current() without `window.location`.
             */
            const ziggy = page.props.ziggy;

            if (ziggy) {
                globalThis.Ziggy = { ...ziggy, location: new URL(ziggy.location) };
            }

            globalThis.route = routeFn;

            // prettier-ignore
            return <App {...props} />;
        },
    }),
);
