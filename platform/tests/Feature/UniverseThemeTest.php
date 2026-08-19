<?php

namespace Tests\Feature;

use App\Models\Universe;
use App\Support\Contrast;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The universe system's central claim, asserted rather than believed.
 *
 * "Adding a universe should be one seeder row and nothing else" is the sentence
 * the whole theming architecture is built around, and it is exactly the kind of
 * claim that quietly stops being true. Signal — the seventh world — was added
 * after these tests existed and needed no component change, which is the only
 * evidence worth having.
 */
class UniverseThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    public function test_every_universe_carries_a_complete_token_set(): void
    {
        $required = [
            'bg', 'bgDeep', 'surface1', 'surface2', 'border', 'text', 'textMuted',
            'accent', 'accentFg', 'accentSoft', 'metalBase', 'metalSheen',
            'metalEdge', 'metalShadow', 'grainAngle', 'glow', 'halo', 'scheme',
        ];

        foreach (Universe::all() as $universe) {
            foreach ($required as $token) {
                $this->assertArrayHasKey(
                    $token,
                    $universe->theme,
                    "{$universe->slug} is missing the {$token} token — a half-defined world renders as a broken page, not a plain one.",
                );
            }
        }
    }

    /**
     * Every shipped palette clears WCAG AA on the pairs that carry prose.
     *
     * Author-made themes are already refused below this floor (see
     * ThemeTokens). Holding our own palettes to a lower standard than the ones
     * we reject would be indefensible.
     */
    public function test_every_shipped_palette_is_readable(): void
    {
        $pairs = [
            ['text', 'bg'],
            ['text', 'surface1'],
            ['textMuted', 'bg'],
            ['textMuted', 'surface1'],
            ['accentFg', 'accent'],
        ];

        foreach (Universe::all() as $universe) {
            foreach ($pairs as [$foreground, $background]) {
                $ratio = Contrast::ratio($universe->theme[$foreground], $universe->theme[$background]);

                $this->assertGreaterThanOrEqual(
                    Contrast::AA_TEXT,
                    $ratio,
                    sprintf(
                        '%s: %s on %s is %s:1, below the AA floor of %s:1.',
                        $universe->slug,
                        $foreground,
                        $background,
                        number_format($ratio, 2),
                        Contrast::AA_TEXT,
                    ),
                );
            }
        }
    }

    public function test_every_universe_has_imagery_that_exists(): void
    {
        foreach (Universe::all() as $universe) {
            $this->assertNotNull($universe->hero_image, "{$universe->slug} has no hero image.");

            $this->assertTrue(
                File::exists(public_path(ltrim($universe->hero_image, '/'))),
                "{$universe->slug}'s hero image is a broken path: {$universe->hero_image}",
            );
        }
    }

    /**
     * The claim itself: no component branches on which universe it is in.
     *
     * A single `if (universe === 'jungle')` anywhere means adding a world is no
     * longer one row, and the next person to add one discovers that the hard
     * way.
     */
    public function test_no_component_branches_on_a_universe_slug(): void
    {
        $slugs = Universe::pluck('slug')->all();
        $offenders = [];

        $files = collect(File::allFiles(resource_path('js')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['ts', 'tsx'], true));

        foreach ($files as $file) {
            $source = file_get_contents($file->getPathname());

            // Strip comments — this file's own explanation of the rule must not
            // read as a violation of it.
            $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
            $source = preg_replace('#^\s*//.*$#m', '', $source) ?? $source;

            foreach ($slugs as $slug) {
                // A comparison against a slug literal, in either direction.
                if (preg_match('/(===?\s*[\'"]'.preg_quote($slug, '/').'[\'"])|([\'"]'.preg_quote($slug, '/').'[\'"]\s*===?)/', $source)) {
                    $offenders[] = str_replace(resource_path('js').'/', '', $file->getPathname()).' branches on "'.$slug.'"';
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Components must never branch on universe — adding a world would stop being one seeder row:\n".implode("\n", $offenders),
        );
    }
}
