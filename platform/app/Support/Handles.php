<?php

namespace App\Support;

use App\Models\User;

/**
 * Handle rules for personas.
 *
 * Two separate concerns share this file because they share a namespace:
 *
 * **Reserved words** protect readers. A handle appears next to writing as a
 * claim about who wrote it, so `@support`, `@inkfathom` and `@moderator` are
 * not available at any price — an impersonation surface is not something to
 * sell. This list is closed to everyone, premium included.
 *
 * **Short handles** are the premium feature. There are only a few thousand
 * pronounceable handles under five characters and they are genuinely scarce,
 * which is the honest basis for charging: subscribers are buying from a finite
 * pool, not unlocking a check in an `if`. Everyone else gets the enormous
 * remainder for free.
 */
class Handles
{
    /** Handles this short are premium-only. */
    public const VANITY_MAX_LENGTH = 4;

    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 30;

    /**
     * Never issued. Grouped by why, because the reasons expire differently —
     * route names change, impersonation risks do not.
     *
     * @var array<int, string>
     */
    public const RESERVED = [
        // Identity of the platform itself.
        'inkfathom', 'ink', 'fathom', 'official', 'staff', 'team', 'admin', 'administrator',
        'moderator', 'mod', 'support', 'help', 'helpdesk', 'security', 'billing', 'legal',
        'abuse', 'trust', 'safety', 'root', 'system', 'sysadmin', 'noreply', 'no-reply',
        'postmaster', 'webmaster', 'hostmaster',

        // Reads as an endorsement.
        'verified', 'editor', 'editors', 'featured', 'staffpick', 'announcement', 'announcements',

        // Top-level route names, so a profile URL can never shadow a page.
        'about', 'api', 'auth', 'blog', 'categories', 'circles', 'courses', 'dashboard',
        'deep-field', 'feed', 'home', 'library', 'letters', 'login', 'logout', 'me',
        'new', 'personas', 'posts', 'privacy', 'register', 'robots', 'rss', 'search',
        'settings', 'signup', 'sitemap', 'terms', 'universes', 'upgrade', 'write', 'www',
        'mail', 'ftp', 'cdn', 'static', 'assets', 'storage', 'images',
    ];

    /** Is this handle short enough to be a paid one? */
    public static function isVanity(string $handle): bool
    {
        return mb_strlen($handle) <= self::VANITY_MAX_LENGTH;
    }

    public static function isReserved(string $handle): bool
    {
        return in_array(mb_strtolower(trim($handle)), self::RESERVED, true);
    }

    /**
     * Why this handle cannot be used, or null if it can.
     *
     * One message per problem, phrased as something the writer can act on —
     * "that one is taken, here is what is free" beats "invalid".
     */
    public static function rejectionReason(string $handle, ?User $user): ?string
    {
        $handle = trim($handle);

        if (self::isReserved($handle)) {
            return 'That handle is reserved — it would read as an official account.';
        }

        // A handle that is only digits or only separators is not a name, and
        // reads as spam next to a byline.
        if (! preg_match('/[a-z]/i', $handle)) {
            return 'Handles need at least one letter.';
        }

        if (preg_match('/^[-_]|[-_]$|[-_]{2}/', $handle)) {
            return 'Handles cannot start or end with a dash or underscore, or contain two in a row.';
        }

        if (self::isVanity($handle) && ! ($user?->is_premium ?? false)) {
            return sprintf(
                'Handles of %d characters or fewer are part of premium. Try a longer one, or upgrade to claim this.',
                self::VANITY_MAX_LENGTH,
            );
        }

        return null;
    }

    /**
     * A free variant of a taken handle, or null if we could not find one.
     *
     * Deliberately suffix-based rather than random: `@aiya2` still reads as the
     * name the writer chose, where `@aiya_x7f2` reads as a system-assigned id.
     */
    public static function suggest(string $handle, callable $isTaken, int $attempts = 12): ?string
    {
        $base = mb_substr(preg_replace('/[^a-z0-9_-]/i', '', $handle) ?: 'writer', 0, self::MAX_LENGTH - 3);

        for ($suffix = 2; $suffix < $attempts + 2; $suffix++) {
            $candidate = $base.$suffix;

            if (! self::isReserved($candidate) && ! $isTaken($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
