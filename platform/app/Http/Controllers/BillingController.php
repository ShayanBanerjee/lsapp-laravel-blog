<?php

namespace App\Http\Controllers;

use App\Models\ThemeEntitlement;
use App\Models\Universe;
use App\Models\User;
use App\Support\Billing\BillingGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function checkout(Request $request, BillingGateway $gateway): RedirectResponse
    {
        abort_unless($gateway->isConfigured(), 503, 'Payments are not configured.');

        return redirect()->away($gateway->createCheckout($request->user(), 'premium'));
    }

    /**
     * Provider webhook.
     *
     * CSRF-exempt by necessity (it is a server-to-server call), which makes the
     * signature check the *only* thing standing between this route and anyone
     * granting themselves premium. It runs before any state is touched.
     */
    public function webhook(Request $request, BillingGateway $gateway): Response
    {
        $result = $gateway->verifyWebhook($request->getContent(), $request->headers->all());

        if (! $result) {
            // 400, not 401 — providers retry on 5xx, and an unverifiable
            // payload will never become verifiable.
            return response('invalid signature', 400);
        }

        DB::transaction(function () use ($result) {
            $user = User::find($result->userId);

            if (! $user) {
                return;
            }

            if ($result->event === 'subscription_started') {
                $user->update(['is_premium' => true]);

                // Entitlement rows are the source of truth the policies read,
                // so grant one per premium universe rather than relying on the
                // flag alone.
                Universe::where('is_premium', true)->get()->each(
                    fn (Universe $universe) => ThemeEntitlement::updateOrCreate(
                        ['user_id' => $user->id, 'universe_id' => $universe->id],
                        ['source' => 'subscription', 'expires_at' => null],
                    )
                );
            }

            if ($result->event === 'subscription_ended') {
                $user->update(['is_premium' => false]);
                $user->themeEntitlements()->where('source', 'subscription')->delete();
            }
        });

        return response('ok', 200);
    }
}
