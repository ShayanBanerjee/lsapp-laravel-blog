<?php

namespace App\Support\Billing;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout.
 *
 * NOTE: this issues the Checkout Session over the REST API directly rather than
 * pulling in Cashier, because the only thing we need from Stripe is "did this
 * user pay" — subscriptions state lives in `theme_entitlements` either way.
 *
 * The webhook signature check is written out in full rather than trusting the
 * event body: an unverified billing webhook is a free-premium endpoint for
 * anyone who can guess the URL.
 */
class StripeGateway implements BillingGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret')) && filled(config('services.stripe.price_id'));
    }

    public function name(): string
    {
        return 'Stripe';
    }

    public function createCheckout(User $user, string $plan): string
    {
        throw_unless($this->isConfigured(), RuntimeException::class, 'Stripe is not configured.');

        $response = Http::asForm()
            ->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'subscription',
                'line_items[0][price]' => config('services.stripe.price_id'),
                'line_items[0][quantity]' => 1,
                'success_url' => route('upgrade.show').'?checkout=success',
                'cancel_url' => route('upgrade.show').'?checkout=cancelled',
                'customer_email' => $user->email,
                // Round-trips our user id so the webhook does not have to guess.
                'client_reference_id' => (string) $user->id,
            ])->throw();

        return $response->json('url');
    }

    public function verifyWebhook(string $payload, array $headers): ?WebhookResult
    {
        $secret = config('services.stripe.webhook_secret');
        $signature = $headers['stripe-signature'][0] ?? null;

        if (! $secret || ! $signature || ! $this->signatureIsValid($payload, $signature, $secret)) {
            return null;
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];
        $userId = (int) ($object['client_reference_id'] ?? 0);

        if (! $userId) {
            return null;
        }

        return match ($type) {
            'checkout.session.completed', 'invoice.payment_succeeded' => new WebhookResult('subscription_started', $userId, $object['id'] ?? null),
            'customer.subscription.deleted' => new WebhookResult('subscription_ended', $userId, $object['id'] ?? null),
            default => null,
        };
    }

    /** Constant-time verification of Stripe's `t=…,v1=…` signature header. */
    private function signatureIsValid(string $payload, string $header, string $secret): bool
    {
        $parts = collect(explode(',', $header))
            ->mapWithKeys(function (string $part) {
                [$k, $v] = array_pad(explode('=', $part, 2), 2, null);

                return [$k => $v];
            });

        $timestamp = $parts['t'] ?? null;
        $provided = $parts['v1'] ?? null;

        if (! $timestamp || ! $provided) {
            return false;
        }

        // Reject stale signatures so a captured webhook cannot be replayed.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $provided);
    }
}
