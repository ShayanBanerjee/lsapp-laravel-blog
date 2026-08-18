<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseModule extends Model
{
    protected $fillable = ['course_id', 'title', 'summary', 'sort_order'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Lessons are posts; see the courses migration for why. */
    public function lessons(): HasMany
    {
        return $this->hasMany(Post::class)->orderBy('sort_order');
    }
}
