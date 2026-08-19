<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform share
    |--------------------------------------------------------------------------
    |
    | In basis points: 2500 = 25%. Expressed this way so the rate itself is an
    | exact integer — see App\Support\Earnings\RevenueSplit for why nothing in
    | this subsystem touches a float.
    |
    | The platform pays payment-processing fees out of this share rather than
    | deducting them from the writer first, which is what keeps small tips
    | worth making.
    |
    */
    'platform_rate_basis_points' => (int) env('PLATFORM_RATE_BASIS_POINTS', 2500),

    'currency' => env('EARNINGS_CURRENCY', 'GBP'),

    /*
    |--------------------------------------------------------------------------
    | Contribution limits
    |--------------------------------------------------------------------------
    |
    | Minimum exists because below roughly £1 the processing fee exceeds the
    | platform's entire share and every transaction loses money. Maximum is a
    | fraud tripwire, not a ceiling on generosity — larger amounts should go
    | through a reviewed flow rather than a one-click button.
    |
    */
    'min_contribution' => (int) env('MIN_CONTRIBUTION_MINOR', 100),
    'max_contribution' => (int) env('MAX_CONTRIBUTION_MINOR', 50_000),

    /** Suggested tip amounts, in minor units. */
    'tip_presets' => [200, 500, 1000, 2500],

    /** Monthly membership tiers a writer can offer, in minor units. */
    'membership_tiers' => [300, 700, 1500],

    /*
    |--------------------------------------------------------------------------
    | Payouts
    |--------------------------------------------------------------------------
    |
    | Balances below the threshold roll over rather than being paid out, because
    | a transfer costs more than it moves. The hold period is the window in
    | which a card payment can still be charged back — paying out earlier means
    | clawing money back from a writer who has already spent it.
    |
    */
    'payout_minimum' => (int) env('PAYOUT_MINIMUM_MINOR', 2_000),
    'payout_hold_days' => (int) env('PAYOUT_HOLD_DAYS', 14),

];
