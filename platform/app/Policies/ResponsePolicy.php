<?php

namespace App\Policies;

use App\Models\Response;
use App\Models\User;

class ResponsePolicy
{
    /**
     * Removable by its author, or by the author of the piece it sits under —
     * a writer needs to be able to clear abuse from their own page.
     */
    public function delete(User $user, Response $response): bool
    {
        return $user->id === $response->user_id
            || $user->id === $response->post?->user_id;
    }
}
