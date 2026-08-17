<?php

namespace App\Policies;

use App\Models\Universe;
use App\Models\User;

/**
 * The monetization boundary.
 *
 * This is a real security check, not a UI nicety: theme tokens ship to the
 * browser, so gating premium universes in React alone would let anyone read
 * the paid palettes out of the bundle. The server must never serialize tokens
 * the user has not unlocked.
 */
class UniversePolicy
{
    /** Anyone may browse a universe and read what is published in it. */
    public function view(?User $user, Universe $universe): bool
    {
        return true;
    }

    /** Writing in — and receiving the full token set for — a universe. */
    public function use(User $user, Universe $universe): bool
    {
        return $user->canAccessUniverse($universe);
    }
}
