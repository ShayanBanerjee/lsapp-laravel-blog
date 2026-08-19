<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Payout;
use App\Support\Earnings\Ledger;
use App\Support\Earnings\Money;
use App\Support\Earnings\RevenueSplit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The writer's own view of what they have earned.
 *
 * Everything on this page is stated in full, including the platform's cut.
 * Showing a writer only their net and quietly omitting the fee is the standard
 * pattern and it is the wrong one: they can compute the difference anyway, and
 * discovering it themselves is worse than being told.
 */
class EarningsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('earnings', [
            'balances' => [
                'lifetime' => $this->money(Ledger::lifetime($user)),
                'available' => $this->money(Ledger::available($user)),
                'pending' => $this->money(Ledger::pending($user)),
                'platform_share' => $this->money(Ledger::platformShare($user)),
            ],
            'rate' => [
                'percent' => RevenueSplit::ratePercent(),
                'basis_points' => RevenueSplit::rateBasisPoints(),
            ],
            'monthly' => collect(Ledger::monthly($user))
                ->map(fn (array $row) => [
                    'month' => $row['month'],
                    'label' => Carbon::createFromFormat('Y-m', $row['month'])->format('M'),
                    'net_minor' => $row['net_minor'],
                    'net' => (new Money($row['net_minor'], config('earnings.currency')))->format(),
                ]),
            'supporters' => collect(Ledger::topSupporters($user))
                ->map(fn (array $row) => [
                    ...$row,
                    'total' => (new Money($row['total_minor'], config('earnings.currency')))->format(),
                ]),
            'recent' => Contribution::where('to_user_id', $user->id)
                ->settled()
                ->with(['post:id,slug,title', 'persona:id,handle'])
                ->latest('settled_at')
                ->limit(20)
                ->get()
                ->map(fn (Contribution $c) => [
                    'id' => $c->id,
                    'kind' => $c->kind,
                    'from' => $c->supporterName(),
                    'gross' => $c->gross()->format(),
                    'net' => $c->writerNet()->format(),
                    'fee' => $c->platformFee()->format(),
                    'message' => $c->message,
                    'post' => $c->post ? ['slug' => $c->post->slug, 'title' => $c->post->title] : null,
                    'when_human' => $c->settled_at?->diffForHumans(),
                ]),
            'payouts' => Payout::where('user_id', $user->id)
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (Payout $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount()->format(),
                    'status' => $p->status,
                    'paid_human' => $p->paid_at?->format('j M Y'),
                ]),
            'payoutAccount' => [
                'connected' => $user->payoutAccount?->canReceivePayouts() ?? false,
                'status' => $user->payoutAccount?->status ?? 'none',
            ],
            'thresholds' => [
                'minimum' => (new Money((int) config('earnings.payout_minimum'), config('earnings.currency')))->format(),
                'hold_days' => (int) config('earnings.payout_hold_days'),
            ],
            'memberships' => $user->memberships()->active()->with('persona:id,handle,display_name')->get()
                ->map(fn ($m) => [
                    'handle' => $m->persona?->handle,
                    'display_name' => $m->persona?->display_name,
                    'amount' => $m->amount()->format(),
                    'renews_human' => $m->renews_at?->format('j M Y'),
                ]),
        ]);
    }

    /**
     * Request a transfer of the available balance.
     *
     * The amount is taken from the ledger, never from the request. A payout
     * endpoint that accepts an amount from the browser is a withdrawal
     * endpoint that accepts an amount from an attacker.
     */
    public function withdraw(Request $request): RedirectResponse
    {
        $user = $request->user();
        $available = Ledger::available($user);
        $minimum = (int) config('earnings.payout_minimum');

        if (! ($user->payoutAccount?->canReceivePayouts() ?? false)) {
            return back()->with('error', 'Connect a payout account before withdrawing.');
        }

        if ($available->amount < $minimum) {
            return back()->with('error', sprintf(
                'You need at least %s available. Smaller balances roll over — a transfer would cost more than it moves.',
                (new Money($minimum, $available->currency))->format(),
            ));
        }

        Payout::create([
            'user_id' => $user->id,
            'amount_minor' => $available->amount,
            'currency' => $available->currency,
            'status' => Payout::PENDING,
            'period_start' => Contribution::where('to_user_id', $user->id)->payable()->min('settled_at'),
            'period_end' => now(),
        ]);

        return back()->with('success', sprintf('Payout of %s requested.', $available->format()));
    }

    /** @return array<string, mixed> */
    private function money(Money $money): array
    {
        return ['minor' => $money->amount, 'formatted' => $money->format()];
    }
}
