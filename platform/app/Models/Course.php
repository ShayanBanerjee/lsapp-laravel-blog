<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    protected $fillable = [
        'slug', 'title', 'subtitle', 'description', 'user_id', 'persona_id',
        'universe_id', 'cover_image', 'level', 'status', 'published_at', 'sort_order',
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
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    /** Every lesson in the course, flattened — used for counts and ordering. */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, CourseModule::class, 'course_id', 'course_module_id');
    }

    /** @param  Builder<Course>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return str_starts_with($this->cover_image, '/')
            ? $this->cover_image
            : Storage::disk('public')->url($this->cover_image);
    }

    /** @return array<string, mixed> */
    public function card(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'level' => $this->level,
            'cover_url' => $this->coverUrl(),
            'lessons_count' => $this->lessons_count ?? null,
            'universe' => $this->relationLoaded('universe') ? $this->universe?->preview() : null,
            'persona' => $this->relationLoaded('persona') && $this->persona ? [
                'handle' => $this->persona->handle,
                'display_name' => $this->persona->display_name,
            ] : null,
        ];
    }
}
