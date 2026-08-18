<?php

namespace App\Support\Billing;

final class WebhookResult
{
    public function __construct(
        public readonly string $event,        // subscription_started | subscription_ended
        public readonly int $userId,
        public readonly ?string $reference = null,
    ) {}
}
