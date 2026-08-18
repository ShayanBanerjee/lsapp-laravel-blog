<?php

namespace App\Support\Billing;

use App\Models\User;

/**
 * A payment provider.
 *
 * Deliberately provider-agnostic: Stripe is not available in every market this
 * platform would launch in, and the access-control code must never learn who
 * took the money. Entitlements are the source of truth; a gateway's only job is
 * to tell us a payment succeeded.
 */
interface BillingGateway
{
    /** Whether credentials are present. Nothing is offered to a user without this. */
    public function isConfigured(): bool;

    /** Human-facing provider name. */
    public function name(): string;

    /** Begin a purchase; returns the URL to send the customer to. */
    public function createCheckout(User $user, string $plan): string;

    /**
     * Verify an inbound webhook and return the user it concerns, or null when
     * the signature does not verify.
     */
    public function verifyWebhook(string $payload, array $headers): ?WebhookResult;
}
