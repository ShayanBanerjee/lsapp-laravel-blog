<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

class LetterPolicy
{
    /** Letters are private: only the two people involved may read one. */
    public function view(User $user, Letter $letter): bool
    {
        return $user->id === $letter->to_user_id || $user->id === $letter->from_user_id;
    }
}
