<?php

namespace App\Models;

use App\Models\Scopes\StandalonePostScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    protected $fillable = [
        'user_id', 'persona_id', 'universe_id', 'slug', 'title', 'subtitle',
        'description', 'cover_image', 'level', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    /**
     * Every lesson in the course, in reading order.
     *
     * The standalone scope is removed here for the same reason it exists —
     * this is the query that has explicitly asked for lessons.
     *
     * @return HasManyThrough<Post, CourseModule, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, CourseModule::class, 'course_id', 'course_module_id')
            ->withoutGlobalScope(StandalonePostScope::class)
            ->orderBy('course_modules.position')
            ->orderBy('posts.position');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /** Same two shapes as Post::coverUrl — uploads and bundled seed imagery. */
    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return str_starts_with($this->cover_image, '/')
            ? $this->cover_image
            : Storage::disk('public')->url($this->cover_image);
    }

    public function hasUploadedCover(): bool
    {
        return $this->cover_image !== null && ! str_starts_with($this->cover_image, '/');
    }

    /**
     * How long the whole path takes, summed from the lessons themselves rather
     * than typed in by the author — a hand-entered estimate goes stale the
     * first time a lesson is edited.
     *
     * @param  Collection<int, Post>  $lessons
     */
    public function estimatedMinutes(Collection $lessons): int
    {
        return max(1, (int) $lessons->sum(fn (Post $lesson) => max(1, (int) $lesson->reading_time)));
    }
}
