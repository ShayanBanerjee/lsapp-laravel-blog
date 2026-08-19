<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * A draft course is visible only to its author — the same rule PostPolicy
     * applies, and for the same reason: half-written teaching material read as
     * finished is worse than no material.
     */
    public function view(?User $user, Course $course): bool
    {
        return $course->isPublished() || ($user !== null && $user->id === $course->user_id);
    }

    public function create(User $user): bool
    {
        return $user->personas()->exists();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->user_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->id === $course->user_id;
    }
}
