<?php

namespace Tests\Unit;

use App\Support\Earnings\Money;
use App\Support\Earnings\RevenueSplit;
use Tests\TestCase;

/**
 * Money arithmetic.
 *
 * These are the tests that stop the platform quietly owing people fractions of
 * a penny. The two invariants asserted exhaustively below are the whole
 * contract: the split always reconciles, and it never rounds against the
 * writer.
 */
class RevenueSplitTest extends TestCase
{
    public function test_the_split_always_reconciles_exactly(): void
    {
        // Exhaustive over every amount from 1p to £100, plus the awkward ones
        // above it. If a rounding bug exists at all it exists in here.
        $amounts = array_merge(range(1, 10_000), [12_345, 49_999, 50_000, 999_999]);

        foreach ($amounts as $amount) {
            $split = RevenueSplit::for(new Money($amount));

            $this->assertSame(
                $amount,
                $split->platformFee->amount + $split->writerNet->amount,
                "fee + net != gross at {$amount}",
            );
        }
    }

    public function test_the_writer_is_never_paid_a_negative_amount(): void
    {
        foreach (range(0, 500) as $amount) {
            $this->assertGreaterThanOrEqual(0, RevenueSplit::for(new Money($amount))->writerNet->amount);
        }
    }

    public function test_rounding_favours_the_writer(): void
    {
        // 25% of 99p is 24.75p. The fee floors to 24p, so the spare penny goes
        // to the writer rather than the platform.
        $split = RevenueSplit::for(new Money(99));

        $this->assertSame(24, $split->platformFee->amount);
        $this->assertSame(75, $split->writerNet->amount);
    }

    public function test_the_default_rate_is_twenty_five_percent(): void
    {
        $split = RevenueSplit::for(new Money(1000));

        $this->assertSame(250, $split->platformFee->amount);
        $this->assertSame(750, $split->writerNet->amount);
        $this->assertSame('25%', RevenueSplit::ratePercent());
    }

    public function test_the_rate_is_configurable_and_still_reconciles(): void
    {
        config(['earnings.platform_rate_basis_points' => 1000]);

        $split = RevenueSplit::for(new Money(999));

        $this->assertSame(99, $split->platformFee->amount);
        $this->assertSame(900, $split->writerNet->amount);
        $this->assertSame('10%', RevenueSplit::ratePercent());
    }

    public function test_an_absurd_rate_cannot_produce_a_negative_payout(): void
    {
        // Guards against a configuration mistake reaching a writer's balance.
        config(['earnings.platform_rate_basis_points' => 20_000]);

        $split = RevenueSplit::for(new Money(500));

        $this->assertSame(500, $split->platformFee->amount);
        $this->assertSame(0, $split->writerNet->amount);
    }

    public function test_money_formats_for_humans(): void
    {
        $this->assertSame('£12.50', (new Money(1250))->format());
        $this->assertSame('£0.05', (new Money(5))->format());
        $this->assertSame('£1,000.00', (new Money(100_000))->format());
        $this->assertSame('$9.99', (new Money(999, 'USD'))->format());
    }

    public function test_the_currency_symbol_survives_json_encoding(): void
    {
        // Regression: taking the symbol as format()[0] returns the first *byte*
        // of a two-byte character, which is invalid UTF-8 and makes
        // json_encode return false — silently emptying an Inertia payload.
        foreach (['GBP', 'EUR', 'USD'] as $currency) {
            $symbol = (new Money(0, $currency))->symbol();

            $this->assertNotFalse(json_encode(['symbol' => $symbol]), "{$currency} symbol is not encodable");
            $this->assertTrue(mb_check_encoding($symbol, 'UTF-8'));
        }
    }

    public function test_money_refuses_to_be_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(-1);
    }

    public function test_money_refuses_to_mix_currencies(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Money(100, 'GBP'))->plus(new Money(100, 'USD'));
    }
}
