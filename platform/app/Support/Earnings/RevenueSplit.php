<?php

namespace App\Support\Earnings;

/**
 * How a reader's payment is divided.
 *
 * The platform takes a flat share of the gross and **pays the payment
 * processor out of its own half**. That choice is deliberate and it is the one
 * that decides whether small contributions are worth making at all: if
 * processing came off the top, a £1 tip would lose roughly a third to card
 * fees before anyone split anything, and the writer would receive less than
 * half of a gesture the reader thought was whole.
 *
 * Two invariants hold for every possible input, and both are tested:
 *
 * 1. `platformFee + writerNet === gross` — exactly, with no lost minor unit.
 * 2. `writerNet >= 0` — including on amounts too small to divide cleanly.
 *
 * Rounding favours the writer. On a 25% share of 99p the arithmetic gives
 * 24.75p; rounding the *fee* down to 24p hands the extra penny to the writer
 * rather than the platform. Over millions of transactions that is a real sum,
 * and it should fall on the side of the person who did the writing.
 */
readonly class RevenueSplit
{
    public function __construct(
        public Money $gross,
        public Money $platformFee,
        public Money $writerNet,
        public int $rateBasisPoints,
    ) {}

    /**
     * The platform's share, in basis points (2500 = 25%).
     *
     * Basis points rather than a percentage float so the rate itself is exact.
     */
    public static function rateBasisPoints(): int
    {
        return (int) config('earnings.platform_rate_basis_points', 2500);
    }

    public static function for(Money $gross): self
    {
        $rate = self::rateBasisPoints();

        // intdiv truncates toward zero, which for a positive numerator is a
        // floor — the rounding direction promised above.
        $fee = intdiv($gross->amount * $rate, 10_000);

        // Belt and braces: a misconfigured rate must never produce a negative
        // payout or a fee larger than the payment.
        $fee = max(0, min($fee, $gross->amount));

        return new self(
            gross: $gross,
            platformFee: new Money($fee, $gross->currency),
            // Subtraction rather than a second multiplication, so the two
            // halves cannot drift apart by a rounding unit.
            writerNet: new Money($gross->amount - $fee, $gross->currency),
            rateBasisPoints: $rate,
        );
    }

    /** "25%" — for display. */
    public static function ratePercent(): string
    {
        $rate = self::rateBasisPoints() / 100;

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.').'%';
    }
}
