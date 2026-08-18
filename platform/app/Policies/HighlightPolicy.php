<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;

class HighlightPolicy
{
    /** Your own mark is yours to remove. Nobody else's. */
    public function delete(User $user, Highlight $highlight): bool
    {
        return $user->id === $highlight->user_id;
    }
}
