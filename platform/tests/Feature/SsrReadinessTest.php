<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the things that break server-side rendering.
 *
 * These are cheap proxies for an expensive failure. A component that reads
 * `window` at render time, or a missing Ziggy payload, takes down the SSR
 * process for *every* page — and because Inertia falls back to client
 * rendering, the symptom is not an error anyone sees, it is search engines
 * quietly going back to seeing an empty page.
 */
class SsrReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ssr_entry_point_exists_where_vite_expects_it(): void
    {
        $entry = resource_path('js/ssr.tsx');

        $this->assertFileExists($entry);
        $this->assertStringContainsString(
            "ssr: 'resources/js/ssr.tsx'",
            file_get_contents(base_path('vite.config.js')),
            'vite.config.js must point at the SSR entry that actually exists',
        );
    }

    public function test_the_ziggy_route_table_is_shared_as_a_prop(): void
    {
        // The @routes Blade directive puts this on `window`, which SSR does not
        // have. Without the prop, every layout calling route() fails there.
        $this->get('/')->assertInertia(fn ($page) => $page
            ->has('ziggy.routes')
            ->has('ziggy.location'));
    }

    /**
     * Pages and layouts must take the current URL from Inertia, not the browser.
     *
     * Narrow on purpose. A general "no window at render time" scan cannot be
     * written reliably against source text — hooks legitimately touch the DOM
     * inside effects, and a scanner that cannot tell the difference produces
     * false positives until people stop reading it.
     *
     * This checks the one rule that is unambiguous and that actually broke:
     * a page or layout module reading `window.location` renders on every
     * request and takes the SSR process down with it. Everything else is
     * caught by the SSR bundle failing to render, which is what
     * `npm run build:ssr` plus a smoke request is for.
     */
    public function test_pages_and_layouts_do_not_read_window_location(): void
    {
        $offenders = [];

        foreach (['pages', 'layouts'] as $directory) {
            $files = collect(File::allFiles(resource_path('js/'.$directory)))
                ->filter(fn ($file) => in_array($file->getExtension(), ['ts', 'tsx'], true));

            foreach ($files as $file) {
                $source = file_get_contents($file->getPathname());

                // Strip comments first — a comment explaining why we avoid
                // window.location must not read as a use of it.
                $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
                $source = preg_replace('#^\s*//.*$#m', '', $source) ?? $source;

                if (preg_match('/(?<![\w.])window\.location/', $source)) {
                    $offenders[] = str_replace(resource_path('js').'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These read window.location during render, which fails under SSR — use usePage().url instead:\n"
                .implode("\n", $offenders),
        );
    }
}
