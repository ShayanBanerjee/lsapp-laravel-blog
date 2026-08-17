<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Ad slots for the free tier.
 *
 * Two rules, both structural rather than cosmetic:
 *
 * 1. **Paying actually removes them.** For an entitled user this returns an
 *    empty array, so the ad payload is never serialized into the page at all.
 *    Hiding ads with CSS or a client-side flag would leave the creative in the
 *    HTML, which is not the thing the customer paid for.
 *
 * 2. **Never inside a piece.** Slots exist between pieces and beside them,
 *    never mid-argument. The entire proposition of a reading platform is
 *    uninterrupted attention; an interstitial in the middle of a paragraph
 *    would sell the one thing we are asking people to come here for.
 *
 * The creatives below are house placeholders. A real network integration would
 * replace `creative()` and must keep the entitlement check in place.
 */
class Ads
{
    /**
     * @return array<int, array<string, string>>
     */
    public static function forRequest(Request $request, string $placement): array
    {
        $user = $request->user();

        // Entitled users: nothing is generated, so nothing reaches the browser.
        if ($user && ! $user->seesAds()) {
            return [];
        }

        return match ($placement) {
            'feed' => [self::creative('feed')],
            'post' => [self::creative('after-reading')],
            default => [],
        };
    }

    /** @return array<string, string> */
    private static function creative(string $slot): array
    {
        return [
            'slot' => $slot,
            'kind' => 'house',
            'title' => 'Read without interruption',
            'body' => 'Premium removes every ad on Aetheris, opens all six universes, and lifts the persona limit.',
            'cta' => 'See premium',
            'href' => '/upgrade',
        ];
    }
}
