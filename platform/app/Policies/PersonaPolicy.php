<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;

class PersonaPolicy
{
    public function create(User $user): bool
    {
        return $user->canCreateAnotherPersona();
    }

    public function update(User $user, Persona $persona): bool
    {
        return $user->id === $persona->user_id;
    }

    public function delete(User $user, Persona $persona): bool
    {
        return $user->id === $persona->user_id;
    }
}
