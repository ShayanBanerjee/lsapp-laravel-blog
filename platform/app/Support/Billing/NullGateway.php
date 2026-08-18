<?php

namespace App\Support\Billing;

use App\Models\User;
use RuntimeException;

/**
 * The default when no provider is configured.
 *
 * Refuses loudly rather than silently succeeding — a billing path that quietly
 * no-ops is how people end up with free premium in production.
 */
class NullGateway implements BillingGateway
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function createCheckout(User $user, string $plan): string
    {
        throw new RuntimeException('No payment provider is configured.');
    }

    public function verifyWebhook(string $payload, array $headers): ?WebhookResult
    {
        return null;
    }
}
