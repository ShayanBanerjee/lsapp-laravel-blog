<?php

namespace App\Models;

use App\Models\Scopes\StandalonePostScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseModule extends Model
{
    protected $fillable = ['course_id', 'title', 'summary', 'position'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Lessons in this module, in order.
     *
     * @return HasMany<Post, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Post::class, 'course_module_id')
            ->withoutGlobalScope(StandalonePostScope::class)
            ->orderBy('position');
    }
}
