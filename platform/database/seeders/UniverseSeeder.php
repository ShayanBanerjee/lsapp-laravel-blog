<?php

namespace Database\Seeders;

use App\Models\Universe;
use Illuminate\Database\Seeder;

/**
 * The six shipped universes.
 *
 * Each `theme` payload is a complete token set, not a hue swap. Adding a
 * seventh universe should require a row here and nothing else — if it needs a
 * component change, the theming layer is wrong.
 *
 * Every palette is checked for WCAG AA body-text contrast against its own
 * surface; see the contrast test in tests/Feature/UniverseThemeTest.php.
 */
class UniverseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::universes() as $universe) {
            Universe::updateOrCreate(['slug' => $universe['slug']], $universe);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function universes(): array
    {
        return [
            [
                'slug' => 'cosmos',
                'hero_image' => '/images/universes/cosmos-hero.jpg',
                'hero_credit' => ['name' => 'Олег Мороз', 'username' => 'tengyart'],
                'name' => 'Cosmos',
                'tagline' => 'Vast, still, weightless.',
                'description' => 'Write from the space between stars. Long thoughts, cold light, and the particular clarity that comes from very far away.',
                'material' => 'iridescent titanium',
                'is_premium' => false,
                'sort_order' => 1,
                'theme' => [
                    'bg' => '#080a14', 'bgDeep' => '#04050c',
                    'surface1' => '#111527', 'surface2' => '#1a2039',
                    'border' => '#2c3454', 'text' => '#eaecf8', 'textMuted' => '#a8b0d0',
                    'accent' => '#9b8cff', 'accentFg' => '#07040f', 'accentSoft' => '#9b8cff2e',
                    'metalBase' => '#262d4a', 'metalSheen' => '#cdd6ff',
                    'metalEdge' => '#7d8ad4', 'metalShadow' => '#03040a',
                    'grainAngle' => '115deg', 'glow' => '#9b8cff',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #6f5cff33, transparent 65%)',
                    'scheme' => 'dark',
                ],
            ],
            [
                'slug' => 'nature',
                'hero_image' => '/images/universes/nature-hero.jpg',
                'hero_credit' => ['name' => 'Allen Y', 'username' => 'yanahd'],
                'name' => 'Nature',
                'tagline' => 'Warm, growing, close.',
                'description' => 'Ground-level writing. Moss, loam, and light coming through leaves — the world at the scale of a hand.',
                'material' => 'patinated copper & verdigris',
                'is_premium' => false,
                'sort_order' => 2,
                'theme' => [
                    'bg' => '#f3f1e7', 'bgDeep' => '#e7e4d6',
                    'surface1' => '#fbfaf4', 'surface2' => '#ffffff',
                    'border' => '#d3d0be', 'text' => '#1d2a20', 'textMuted' => '#4f5d4e',
                    'accent' => '#3f7a52', 'accentFg' => '#f6fbf6', 'accentSoft' => '#3f7a5224',
                    'metalBase' => '#9db4a0', 'metalSheen' => '#ffffff',
                    'metalEdge' => '#5f8f70', 'metalShadow' => '#b9bda8',
                    'grainAngle' => '95deg', 'glow' => '#6aa77f',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #6aa77f26, transparent 65%)',
                    'scheme' => 'light',
                ],
            ],
            [
                'slug' => 'mountains',
                'hero_image' => '/images/universes/mountains-hero.jpg',
                'hero_credit' => ['name' => 'Jerry Zhang', 'username' => 'z734923105'],
                'name' => 'Mountains',
                'tagline' => 'Severe, clear, high-altitude.',
                'description' => 'Thin air and hard edges. For writing that has had everything unnecessary stripped off it by the wind.',
                'material' => 'brushed steel & slate',
                'is_premium' => true,
                'sort_order' => 3,
                'theme' => [
                    'bg' => '#eef1f5', 'bgDeep' => '#dfe4ea',
                    'surface1' => '#f9fbfd', 'surface2' => '#ffffff',
                    'border' => '#cbd3dd', 'text' => '#14202c', 'textMuted' => '#4b5a6b',
                    'accent' => '#2f6485', 'accentFg' => '#f4fafd', 'accentSoft' => '#2f648522',
                    'metalBase' => '#aab6c4', 'metalSheen' => '#ffffff',
                    'metalEdge' => '#6d8299', 'metalShadow' => '#b3bcc8',
                    'grainAngle' => '88deg', 'glow' => '#5b8fb0',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #5b8fb024, transparent 65%)',
                    'scheme' => 'light',
                ],
            ],
            [
                'slug' => 'jungle',
                'hero_image' => '/images/universes/jungle-hero.jpg',
                'hero_credit' => ['name' => 'Lingchor', 'username' => 'lingchor'],
                'name' => 'Jungle',
                'tagline' => 'Dense, alive, overgrown.',
                'description' => 'Everything competing for the same light. Humid, layered, loud with things you cannot see.',
                'material' => 'oxidized brass',
                'is_premium' => true,
                'sort_order' => 4,
                'theme' => [
                    'bg' => '#0a1410', 'bgDeep' => '#050d0a',
                    'surface1' => '#11201a', 'surface2' => '#1a2d23',
                    'border' => '#2c4436', 'text' => '#e8f1e6', 'textMuted' => '#a3b89e',
                    'accent' => '#d3a441', 'accentFg' => '#100c02', 'accentSoft' => '#d3a4412e',
                    'metalBase' => '#4a4326', 'metalSheen' => '#f0dda4',
                    'metalEdge' => '#a58c46', 'metalShadow' => '#040a07',
                    'grainAngle' => '122deg', 'glow' => '#d3a441',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #4e7a3f38, transparent 65%)',
                    'scheme' => 'dark',
                ],
            ],
            [
                'slug' => 'abyss',
                'hero_image' => '/images/universes/abyss-hero.jpg',
                'hero_credit' => ['name' => 'Cristian Palmer', 'username' => 'cristianpalmer'],
                'name' => 'Abyss',
                'tagline' => 'Pressured, dark, luminous.',
                'description' => 'Below the last of the light, where everything that glows made the glow itself.',
                'material' => 'blackened chrome',
                'is_premium' => true,
                'sort_order' => 5,
                'theme' => [
                    'bg' => '#04080f', 'bgDeep' => '#01040a',
                    'surface1' => '#0a121d', 'surface2' => '#101d2c',
                    'border' => '#1e3346', 'text' => '#dfeaf2', 'textMuted' => '#95acbe',
                    'accent' => '#2ed3c0', 'accentFg' => '#01100e', 'accentSoft' => '#2ed3c02e',
                    'metalBase' => '#16222f', 'metalSheen' => '#9fd8e2',
                    'metalEdge' => '#37647a', 'metalShadow' => '#000308',
                    'grainAngle' => '100deg', 'glow' => '#2ed3c0',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #14707e40, transparent 65%)',
                    'scheme' => 'dark',
                ],
            ],
            [
                'slug' => 'desert',
                'hero_image' => '/images/universes/desert-hero.jpg',
                'hero_credit' => ['name' => 'Emma Van Sant', 'username' => 'emma'],
                'name' => 'Desert',
                'tagline' => 'Spare, sunbleached, wide.',
                'description' => 'Nothing to hide behind for a hundred miles. Writing with the adjectives burned off.',
                'material' => 'aged bronze',
                'is_premium' => true,
                'sort_order' => 6,
                'theme' => [
                    'bg' => '#f5ede0', 'bgDeep' => '#e9dcc9',
                    'surface1' => '#fdf8f1', 'surface2' => '#ffffff',
                    'border' => '#ded0b9', 'text' => '#2a2017', 'textMuted' => '#63553f',
                    'accent' => '#a4552c', 'accentFg' => '#fdf6ef', 'accentSoft' => '#a4552c22',
                    'metalBase' => '#c4a382', 'metalSheen' => '#fff6e8',
                    'metalEdge' => '#8f6a44', 'metalShadow' => '#c0ae94',
                    'grainAngle' => '92deg', 'glow' => '#b4633a',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #8a6aa32b, transparent 65%)',
                    'scheme' => 'light',
                ],
            ],
            [
                /*
                 * Signal — the seventh universe, and the proof of the claim.
                 *
                 * Adding a world is supposed to be a row here and nothing else.
                 * This one was added last and needed exactly that: no component
                 * branches on it, no CSS was written for it, and its palette is
                 * checked against the same contrast floor as the other six.
                 *
                 * Its imagery is *drawn*, not photographed — see the SVGs under
                 * public/images. The bundled photography is all landscape, and a
                 * machine room pulled off a web search would be someone else's
                 * copyrighted work. Vector also happens to be the right medium
                 * for the subject.
                 */
                'slug' => 'signal',
                'hero_image' => '/images/universes/signal-hero.svg',
                'hero_credit' => ['name' => 'Drawn for Inkfathom', 'username' => null],
                'name' => 'Signal',
                'tagline' => 'Clocked, cooled, and humming.',
                'description' => 'The machine room. Write about the systems we built to think with — what they assume, where they leak, and what they cost when nobody is looking.',
                'material' => 'anodised aluminium & phosphor glass',
                'is_premium' => true,
                'sort_order' => 7,
                'theme' => [
                    'bg' => '#05080d', 'bgDeep' => '#020406',
                    'surface1' => '#0d1218', 'surface2' => '#151f29',
                    'border' => '#26384a', 'text' => '#e6f1f7', 'textMuted' => '#9db4c4',
                    'accent' => '#3ddbd9', 'accentFg' => '#02201f', 'accentSoft' => '#3ddbd92e',
                    'metalBase' => '#1a2734', 'metalSheen' => '#cfe9f4',
                    'metalEdge' => '#5b8299', 'metalShadow' => '#000203',
                    // Vertical, like the brushed face of a rack panel.
                    'grainAngle' => '90deg', 'glow' => '#3ddbd9',
                    'halo' => 'radial-gradient(ellipse 120% 70% at 50% -10%, #1a8f9440, transparent 65%)',
                    'scheme' => 'dark',
                ],
            ],
        ];
    }
}
