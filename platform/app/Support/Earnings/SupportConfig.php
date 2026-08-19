<?php

namespace App\Support\Earnings;

use App\Models\Membership;
use App\Models\Persona;
use App\Models\User;

/**
 * What the support widget needs to render, in one place.
 *
 * Built server-side because two of the four facts are authorisation decisions
 * — whether this is the viewer's own work, and whether they already hold a
 * membership — and neither should be inferred in the browser.
 */
class SupportConfig
{
    /** @return array<string, mixed> */
    public static function for(?User $viewer, ?Persona $persona, ?int $authorId): array
    {
        $member = false;

        if ($viewer && $persona) {
            $member = Membership::where('user_id', $viewer->id)
                ->where('persona_id', $persona->id)
                ->where('status', Membership::ACTIVE)
                ->exists();
        }

        return [
            'presets' => array_values((array) config('earnings.tip_presets')),
            'tiers' => array_values((array) config('earnings.membership_tiers')),
            'currency_symbol' => (new Money(0, (string) config('earnings.currency')))->symbol(),
            'rate_percent' => RevenueSplit::ratePercent(),
            'member' => $member,
            'handle' => $persona?->handle,
            'is_mine' => $viewer !== null && $authorId !== null && $viewer->id === $authorId,
            'signed_in' => $viewer !== null,
        ];
    }
}
