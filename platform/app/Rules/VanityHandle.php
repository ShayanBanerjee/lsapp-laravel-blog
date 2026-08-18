<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Handle rules, including the vanity tier promised on /upgrade.
 *
 * Short handles are scarce, so they are the paid tier: free accounts take five
 * characters or more, premium may claim two to four. Reserved words are blocked
 * for everyone regardless of plan — `@support` in someone else's hands is an
 * impersonation problem, not a pricing one, and a route-colliding handle would
 * be a routing bug.
 */
class VanityHandle implements ValidationRule
{
    public const PREMIUM_MINIMUM = 2;

    public const FREE_MINIMUM = 5;

    /**
     * Names that must never belong to a person: anything that collides with a
     * top-level route, and anything that would let a handle impersonate us.
     */
    public const RESERVED = [
        'admin', 'administrator', 'api', 'about', 'auth', 'billing', 'circles',
        'categories', 'dashboard', 'deep-field', 'following', 'help', 'home',
        'inkfathom', 'learn', 'letters', 'library', 'login', 'logout',
        'notifications', 'official', 'personas', 'posts', 'privacy', 'register',
        'root', 'rss', 'security', 'settings', 'staff', 'support', 'system',
        'terms', 'universes', 'upgrade', 'write', 'writers', 'you',
    ];

    public function __construct(private readonly bool $isPremium) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $handle = is_string($value) ? mb_strtolower($value) : '';

        if (in_array($handle, self::RESERVED, true)) {
            $fail('That handle is reserved.');

            return;
        }

        if (str_starts_with($handle, '@') || str_contains($handle, '/')) {
            $fail('Handles may not contain @ or /.');

            return;
        }

        $length = mb_strlen($handle);

        if ($this->isPremium) {
            if ($length < self::PREMIUM_MINIMUM) {
                $fail('Handles must be at least '.self::PREMIUM_MINIMUM.' characters.');
            }

            return;
        }

        if ($length < self::FREE_MINIMUM) {
            $fail('Handles under '.self::FREE_MINIMUM.' characters are a premium feature.');
        }
    }
}
