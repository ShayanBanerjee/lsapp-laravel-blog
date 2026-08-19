<?php

namespace App\Http\Controllers;

use App\Models\Universe;
use App\Models\User;
use App\Support\Handles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Premium tier: unlocks the four gated universes, unlimited personas, and the
 * custom theme editor.
 *
 * NOTE: activate() is a DEVELOPMENT STUB. It flips the flag directly with no
 * payment taken. Wiring real billing means replacing it with Laravel Cashier —
 * a Stripe Checkout redirect, and a webhook that writes theme_entitlements
 * rows. The entitlement table is already the source of truth precisely so that
 * swap does not touch the access-control code.
 */
class UpgradeController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('upgrade', [
            'isPremium' => $user?->is_premium ?? false,
            'universes' => Universe::orderBy('sort_order')->get()
                ->map(fn (Universe $universe) => [
                    ...$universe->preview(),
                    'locked' => $universe->is_premium && ! ($user?->canAccessUniverse($universe) ?? false),
                ]),
            'freePersonaLimit' => User::FREE_PERSONA_LIMIT,
            'vanityMaxLength' => Handles::VANITY_MAX_LENGTH,
            'isStub' => ! config('services.stripe.secret'),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        abort_if(config('services.stripe.secret') !== null, 404);

        $request->user()->update(['is_premium' => true]);

        return to_route('universes.index')
            ->with('success', 'Demo premium enabled — every universe unlocked. No payment was taken.');
    }

    public function deactivate(Request $request): RedirectResponse
    {
        abort_if(config('services.stripe.secret') !== null, 404);

        $request->user()->update(['is_premium' => false]);

        return back()->with('success', 'Back to the free plan.');
    }
}
