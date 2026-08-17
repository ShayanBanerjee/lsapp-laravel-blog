<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Replaces the copy-pasted `auth()->user()->id !== $post->user_id` checks the
 * legacy app repeated in edit/update/destroy.
 */
class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($post->isPublished()) {
            return true;
        }

        // Drafts are visible only to their author.
        return $user !== null && $user->id === $post->user_id;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
