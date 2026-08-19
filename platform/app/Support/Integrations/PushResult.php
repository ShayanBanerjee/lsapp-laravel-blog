<?php

namespace App\Support\Integrations;

/** What happened when we sent something to a third party. */
readonly class PushResult
{
    private function __construct(
        public bool $ok,
        public string $message,
        public ?string $url = null,
    ) {}

    public static function success(string $message, ?string $url = null): self
    {
        return new self(true, $message, $url);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
