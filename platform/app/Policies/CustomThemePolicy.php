<?php

namespace App\Policies;

use App\Models\CustomTheme;
use App\Models\User;

/**
 * Authoring themes is premium; the tokens themselves are still gated by
 * UniversePolicy, since a custom theme is always a variation on a base
 * universe the author must already be entitled to write in.
 */
class CustomThemePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_premium;
    }

    public function update(User $user, CustomTheme $theme): bool
    {
        return $user->is_premium && $user->id === $theme->user_id;
    }

    public function delete(User $user, CustomTheme $theme): bool
    {
        // Deliberately not gated on is_premium: someone whose subscription
        // lapsed must still be able to clean up what they made.
        return $user->id === $theme->user_id;
    }
}
