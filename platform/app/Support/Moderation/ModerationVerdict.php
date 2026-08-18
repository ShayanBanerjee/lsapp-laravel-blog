<?php

namespace App\Support\Moderation;

/**
 * The outcome of moderating one piece of text.
 *
 * Three states, not two. `review` exists because the alternative to "allow or
 * block" is not nuance — it is a false positive silencing someone, or a real
 * slur going up. Anything the checker is unsure about goes live *and* gets
 * flagged, so the cost of being wrong is a moderator's minute rather than a
 * person's voice.
 */
final class ModerationVerdict
{
    private function __construct(
        public readonly string $action,   // allow | review | block
        public readonly ?string $category = null,
        public readonly float $confidence = 0.0,
    ) {}

    public static function allow(): self
    {
        return new self('allow');
    }

    public static function review(string $category, float $confidence): self
    {
        return new self('review', $category, $confidence);
    }

    public static function block(string $category, float $confidence): self
    {
        return new self('block', $category, $confidence);
    }

    public function isBlocked(): bool
    {
        return $this->action === 'block';
    }

    public function needsReview(): bool
    {
        return $this->action === 'review';
    }
}
