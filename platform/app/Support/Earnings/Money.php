<?php

namespace App\Support\Earnings;

use InvalidArgumentException;

/**
 * Money, in integer minor units.
 *
 * There are no floats anywhere in this subsystem, and that is not fussiness:
 * `0.1 + 0.2 !== 0.3` in binary floating point, and a platform that computes a
 * revenue share in floats will, given enough transactions, owe someone a
 * fraction of a penny it cannot account for. Every amount here is an integer
 * number of pence/cents and every division states its rounding direction.
 */
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency = 'GBP',
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money cannot be negative; model a refund as its own transaction.');
        }
    }

    public static function zero(string $currency = 'GBP'): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /**
     * The currency symbol.
     *
     * Exposed as its own method because callers want it on its own, and
     * slicing it off the front of format() with `[0]` takes the first *byte*
     * — '£' and '€' are multi-byte in UTF-8, so that yields half a character,
     * which is invalid UTF-8 and makes json_encode fail silently.
     */
    public function symbol(): string
    {
        return match ($this->currency) {
            'GBP' => '£',
            'USD' => '$',
            'EUR' => '€',
            default => $this->currency.' ',
        };
    }

    /** e.g. "£12.50" */
    public function format(): string
    {
        return $this->symbol().number_format($this->amount / 100, 2);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
