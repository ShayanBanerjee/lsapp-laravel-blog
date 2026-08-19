<?php

namespace App\Support\Earnings;

use App\Models\Contribution;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What a writer has earned, and what is actually theirs to take.
 *
 * Three balances, and the distinction between them is the whole point:
 *
 * - **Lifetime** — everything ever settled. The number a writer wants to see.
 * - **Available** — settled, past the chargeback hold, not yet paid out. The
 *   only figure that may be withdrawn.
 * - **Pending** — settled but still inside the hold window. Real, but not yet
 *   safe to send.
 *
 * Collapsing these into one "balance" is how platforms end up clawing money
 * back from people who had already spent it.
 *
 * Balances are computed from the transaction rows rather than kept as a
 * running total on the user. A cached total is a number that can silently
 * disagree with the transactions that produced it, and reconciling that
 * afterwards is far more expensive than a `SUM` over an indexed column.
 */
class Ledger
{
    public static function lifetime(User $writer): Money
    {
        return self::sum(
            Contribution::query()->where('to_user_id', $writer->id)->settled()
        );
    }

    /** Settled, matured past the hold, minus everything already paid out. */
    public static function available(User $writer): Money
    {
        $earned = self::sum(
            Contribution::query()->where('to_user_id', $writer->id)->payable()
        );

        $paidOut = self::sumPayouts($writer);

        // Cannot go negative: a payout is only ever created from an available
        // balance, but clamping means a data problem shows as zero rather than
        // as a nonsensical debt shown to a writer.
        return new Money(max(0, $earned->amount - $paidOut->amount), $earned->currency);
    }

    /** Settled but still inside the chargeback window. */
    public static function pending(User $writer): Money
    {
        return self::sum(
            Contribution::query()
                ->where('to_user_id', $writer->id)
                ->settled()
                ->where('settled_at', '>', now()->subDays((int) config('earnings.payout_hold_days')))
        );
    }

    /** What the platform has taken from this writer's contributions, ever. */
    public static function platformShare(User $writer): Money
    {
        $total = (int) Contribution::query()
            ->where('to_user_id', $writer->id)
            ->settled()
            ->sum('platform_fee_minor');

        return new Money($total, self::currency());
    }

    /**
     * Monthly totals for the earnings chart.
     *
     * Grouped in SQL. Pulling every contribution into PHP to bucket them by
     * month is fine for a writer with forty supporters and ruinous for one
     * with forty thousand.
     *
     * @return array<int, array{month: string, net_minor: int}>
     */
    public static function monthly(User $writer, int $months = 12): array
    {
        $since = now()->startOfMonth()->subMonths($months - 1);

        // Date formatting differs between drivers; SQLite and Postgres do not
        // share a function here, so the expression is chosen per connection
        // rather than assuming MySQL.
        $expression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', settled_at)",
            'pgsql' => "to_char(settled_at, 'YYYY-MM')",
            default => "DATE_FORMAT(settled_at, '%Y-%m')",
        };

        $rows = Contribution::query()
            ->where('to_user_id', $writer->id)
            ->settled()
            ->where('settled_at', '>=', $since)
            ->selectRaw("{$expression} as month, SUM(writer_net_minor) as net_minor")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('net_minor', 'month');

        // Fill the gaps so the chart has a bar for a quiet month rather than
        // silently compressing the timeline.
        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $key = $since->copy()->addMonths($i)->format('Y-m');
            $series[] = ['month' => $key, 'net_minor' => (int) ($rows[$key] ?? 0)];
        }

        return $series;
    }

    /** Supporters, most generous first, honouring anonymity. */
    public static function topSupporters(User $writer, int $limit = 8): array
    {
        return Contribution::query()
            ->where('to_user_id', $writer->id)
            ->settled()
            ->with('fromUser:id,name')
            ->get()
            ->groupBy(fn (Contribution $c) => $c->is_anonymous ? 'anon:'.$c->id : 'user:'.($c->from_user_id ?? 'guest'))
            ->map(fn ($group) => [
                'name' => $group->first()->supporterName(),
                'total_minor' => (int) $group->sum('writer_net_minor'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total_minor')
            ->take($limit)
            ->values()
            ->all();
    }

    private static function sum(Builder $query): Money
    {
        return new Money((int) $query->sum('writer_net_minor'), self::currency());
    }

    private static function sumPayouts(User $writer): Money
    {
        $total = (int) Payout::query()
            ->where('user_id', $writer->id)
            ->whereIn('status', [Payout::PENDING, Payout::PAID])
            ->sum('amount_minor');

        return new Money($total, self::currency());
    }

    private static function currency(): string
    {
        return (string) config('earnings.currency', 'GBP');
    }
}
