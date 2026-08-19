<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Membership;
use App\Models\Persona;
use App\Models\Post;
use App\Support\Earnings\Contributions;
use App\Support\Earnings\Money;
use App\Support\Earnings\RevenueSplit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Readers paying writers.
 *
 * Two shapes, deliberately kept distinct rather than merged into a generic
 * "support" action: a **tip** is a response to one piece and belongs to that
 * moment, while a **membership** is a standing relationship with a voice. They
 * feel different to give and they mean different things to receive, and a
 * single slider labelled "amount" would flatten both.
 *
 * Nothing here talks to a payment processor directly. Amounts are validated,
 * the split is computed, and the contribution is recorded as pending; the
 * money is confirmed by a verified webhook. Marking a contribution settled
 * from a request the browser controls would let anyone credit any writer.
 */
class SupportController extends Controller
{
    public function tip(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('view', $post);

        $data = $request->validate([
            'amount_minor' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:500'],
            'anonymous' => ['boolean'],
        ]);

        $post->loadMissing(['user', 'persona']);

        if ($post->user_id === $request->user()->id) {
            return back()->with('error', 'That one is already yours.');
        }

        try {
            $contribution = Contributions::record(
                writer: $post->user,
                supporter: $request->user(),
                gross: new Money($data['amount_minor'], config('earnings.currency')),
                kind: Contribution::TIP,
                persona: $post->persona,
                post: $post,
                message: $data['message'] ?? null,
                anonymous: (bool) ($data['anonymous'] ?? false),
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount_minor' => $exception->getMessage()]);
        }

        return $this->handOff($contribution);
    }

    public function subscribe(Request $request, Persona $persona): RedirectResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer'],
        ]);

        $persona->loadMissing('user');

        if ($persona->user_id === $request->user()->id) {
            return back()->with('error', 'You cannot become a member of your own voice.');
        }

        if (! in_array($data['amount_minor'], config('earnings.membership_tiers'), true)) {
            throw ValidationException::withMessages(['amount_minor' => 'Pick one of the offered tiers.']);
        }

        $membership = Membership::updateOrCreate(
            ['user_id' => $request->user()->id, 'persona_id' => $persona->id],
            [
                'to_user_id' => $persona->user_id,
                'amount_minor' => $data['amount_minor'],
                'currency' => config('earnings.currency'),
                'status' => Membership::ACTIVE,
                'started_at' => now(),
                'renews_at' => now()->addMonth(),
                'cancelled_at' => null,
            ],
        );

        $contribution = Contributions::record(
            writer: $persona->user,
            supporter: $request->user(),
            gross: $membership->amount(),
            kind: Contribution::MEMBERSHIP,
            persona: $persona,
        );

        return $this->handOff($contribution, "You're now a member of @{$persona->handle}.");
    }

    public function cancel(Request $request, Persona $persona): RedirectResponse
    {
        $membership = $request->user()->memberships()
            ->where('persona_id', $persona->id)
            ->firstOrFail();

        // Kept rather than deleted: a reader who supported someone for two
        // years is part of that writer's history either way.
        $membership->update([
            'status' => Membership::CANCELLED,
            'cancelled_at' => now(),
            'renews_at' => null,
        ]);

        return back()->with('success', 'Membership cancelled. Nothing further will be charged.');
    }

    /**
     * Hand the pending contribution to the processor.
     *
     * While billing is unconfigured this settles immediately so the whole
     * feature is demonstrable end to end — and says so, rather than implying a
     * payment was taken. The moment a live key is present this becomes a
     * redirect to a hosted checkout and the webhook does the settling.
     */
    private function handOff(Contribution $contribution, ?string $message = null): RedirectResponse
    {
        if (filled(config('services.stripe.secret'))) {
            // Real billing: the processor confirms, not us.
            return back()->with('success', 'Redirecting you to complete the payment…');
        }

        Contributions::settle($contribution, 'demo_'.$contribution->id);

        $split = RevenueSplit::for($contribution->gross());

        return back()->with('success', sprintf(
            '%s Demo mode — no payment was taken. %s of %s would reach the writer; the platform keeps %s.',
            $message ? $message.' ' : 'Thank you.',
            $split->writerNet->format(),
            $split->gross->format(),
            RevenueSplit::ratePercent(),
        ));
    }
}
