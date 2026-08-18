<?php

return [
    'ssr' => [
        /*
         * Server-side rendering.
         *
         * On by default; the request simply falls back to client rendering when
         * the Node process is not running. Turned off explicitly in phpunit.xml
         * so the test suite never depends on that process being up.
         */
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    ],
];
