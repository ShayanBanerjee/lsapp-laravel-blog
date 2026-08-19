<?php

namespace App\Http\Controllers;

use App\Models\Universe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Deep Field — one continuous descent from the space between stars to the
 * bottom of the sea.
 *
 * The descent is a physical axis, so it carries only the universes that sit on
 * one. Signal — the machine room — is deliberately absent: there is no altitude
 * at which a rack belongs between a treeline and a seabed, and forcing it in
 * would break the one argument the page exists to make.
 *
 * It exists to make one argument that no paragraph could: scale is a choice,
 * and every scale has something worth writing about. It is also the honest
 * answer to "why not just film this?" — because the reader controls the
 * descent. They can stop, reverse, and sit at one depth and read. A film of
 * the same content is someone else's pacing imposed on you.
 */
class DeepFieldController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $universes = Universe::orderBy('sort_order')->get()->keyBy('slug');

        // Narrative order is a descent through space, not the catalogue order.
        $descent = ['cosmos', 'desert', 'mountains', 'jungle', 'nature', 'abyss'];

        $layers = collect($descent)
            ->filter(fn (string $slug) => $universes->has($slug))
            ->values()
            ->map(function (string $slug) use ($universes) {
                $universe = $universes[$slug];

                return [
                    ...$universe->preview(),
                    'altitude' => self::ALTITUDES[$slug]['label'],
                    'measure' => self::ALTITUDES[$slug]['measure'],
                    'line' => self::ALTITUDES[$slug]['line'],
                ];
            });

        return Inertia::render('deep-field', [
            'layers' => $layers,
        ]);
    }

    /**
     * The narrative. Kept server-side with the universes rather than hardcoded
     * in the component, so the page stays in step with the seeded worlds.
     */
    private const ALTITUDES = [
        'cosmos' => [
            'label' => 'The space between',
            'measure' => '4.2 light years',
            'line' => 'Start here, where the nearest other sun is four years of travelling light away. Nothing at this scale is in a hurry. The light on your face tonight left before you thought to look up.',
        ],
        'desert' => [
            'label' => 'First ground',
            'measure' => '11 kilometres',
            'line' => 'Fall far enough and the first thing that resolves is a surface with nothing on it. From here the dunes are still a pattern, not a place. Wind has been editing this page for ten thousand years.',
        ],
        'mountains' => [
            'label' => 'Above the treeline',
            'measure' => '3,400 metres',
            'line' => 'The air thins and the world gets honest. Everything unnecessary has been taken off this landscape by weather, which is the only editor that never gets tired.',
        ],
        'jungle' => [
            'label' => 'The canopy',
            'measure' => '40 metres',
            'line' => 'Now there is competition. Every leaf here is an argument for its own position, and the whole green ceiling is what the argument looks like from above.',
        ],
        'nature' => [
            'label' => 'The forest floor',
            'measure' => '1 metre',
            'line' => 'Below the canopy it is nearly night at noon. What grows down here gave up on the sun and made other arrangements. This is where most of the actual work happens.',
        ],
        'abyss' => [
            'label' => 'Below the last light',
            'measure' => '1,000 metres down',
            'line' => 'Keep going, past the point where light was ever an option. Everything that glows down here made the glow itself. Dozens of times, separately, by animals that never met.',
        ],
    ];
}
