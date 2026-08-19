<?php

namespace App\Support\Earnings;

use App\Models\Contribution;
use App\Models\Persona;
use App\Models\Post;
use App\Models\User;
use App\Support\Alerts;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recording money moving from a reader to a writer.
 *
 * Everything goes through here so the split is computed in exactly one place.
 * A contribution created anywhere else could disagree with the platform rate,
 * and a writer discovering two different fee percentages in their own history
 * is a trust problem no support email fixes.
 */
class Contributions
{
    /**
     * Record a contribution, computing the split.
     *
     * Created `pending`: money has not moved until the processor says it has.
     * `settle()` is what makes it real, and it is driven by a verified webhook.
     */
    public static function record(
        User $writer,
        ?User $supporter,
        Money $gross,
        string $kind,
        ?Persona $persona = null,
        ?Post $post = null,
        ?string $message = null,
        bool $anonymous = false,
    ): Contribution {
        self::assertAmountAllowed($gross);

        if ($supporter && $supporter->id === $writer->id) {
            throw new InvalidArgumentException('A writer cannot contribute to themselves.');
        }

        $split = RevenueSplit::for($gross);

        return Contribution::create([
            'from_user_id' => $supporter?->id,
            'to_user_id' => $writer->id,
            'persona_id' => $persona?->id,
            'post_id' => $post?->id,
            'kind' => $kind,
            'currency' => $gross->currency,
            'gross_minor' => $split->gross->amount,
            'platform_fee_minor' => $split->platformFee->amount,
            'writer_net_minor' => $split->writerNet->amount,
            'rate_basis_points' => $split->rateBasisPoints,
            'status' => Contribution::PENDING,
            // Plain text: this is displayed back to the writer.
            'message' => $message ? HtmlSanitizer::plain($message, 500) : null,
            'is_anonymous' => $anonymous,
        ]);
    }

    /**
     * Confirm the money arrived.
     *
     * Idempotent by design: processors retry webhooks, and a second delivery
     * must not credit a writer twice or send them a second notification. The
     * `provider_ref` unique index is the hard guarantee; this check is the one
     * that keeps the common path quiet.
     */
    public static function settle(Contribution $contribution, ?string $providerRef = null): Contribution
    {
        if ($contribution->status === Contribution::SETTLED) {
            return $contribution;
        }

        DB::transaction(function () use ($contribution, $providerRef) {
            $contribution->forceFill([
                'status' => Contribution::SETTLED,
                'settled_at' => now(),
                'provider_ref' => $providerRef ?? $contribution->provider_ref,
            ])->save();
        });

        $supporter = $contribution->fromUser;

        if ($supporter) {
            Alerts::supported(
                $contribution->to_user_id,
                $supporter,
                $contribution->persona,
                $contribution->gross()->format(),
                $contribution->message,
            );
        }

        return $contribution->refresh();
    }

    private static function assertAmountAllowed(Money $gross): void
    {
        $min = (int) config('earnings.min_contribution');
        $max = (int) config('earnings.max_contribution');

        if ($gross->amount < $min) {
            throw new InvalidArgumentException('Contributions start at '.(new Money($min, $gross->currency))->format().'.');
        }

        if ($gross->amount > $max) {
            throw new InvalidArgumentException('That is larger than a one-click contribution allows.');
        }
    }
}
