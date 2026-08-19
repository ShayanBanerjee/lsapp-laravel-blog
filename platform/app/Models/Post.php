<?php

namespace App\Models;

use App\Models\Scopes\StandalonePostScope;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A piece of writing.
 *
 * A Post with a `course_module_id` is a **lesson** inside a course rather than
 * standalone writing. StandalonePostScope hides those from every query by
 * default — see that class for why the default runs that way round.
 */
#[ScopedBy([StandalonePostScope::class])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'persona_id', 'universe_id', 'course_module_id', 'position',
        'slug', 'title', 'excerpt', 'body', 'cover_image', 'status',
        'reading_time', 'published_at',
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

    public function courseModule(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class);
    }

    /** True when this post is a lesson rather than standalone writing. */
    public function isLesson(): bool
    {
        return $this->course_module_id !== null;
    }

    /**
     * Where this post is actually read.
     *
     * Standalone writing lives at /posts/{slug}; a lesson lives inside its
     * course and 404s at that URL, because StandalonePostScope hides it from
     * the `{post}` binding. Anything redirecting to "the post" must go through
     * here or it will send lessons to a dead page.
     */
    public function readUrl(): string
    {
        if (! $this->isLesson()) {
            return route('posts.show', $this, absolute: false);
        }

        $module = $this->relationLoaded('courseModule')
            ? $this->courseModule
            : $this->courseModule()->with('course')->first();

        $course = $module?->relationLoaded('course') ? $module->course : $module?->course()->first();

        return $course
            ? route('courses.lesson', [$course, $this], absolute: false)
            : route('courses.index', absolute: false);
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_post')->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Marked passages, grouped so identical passages collapse into one row with
     * a count. This is the "which sentence worked" answer the writer's desk is
     * built on, and the trending signal used instead of view counts.
     *
     * Grouped in SQL rather than in PHP so a heavily-marked piece does not pull
     * every individual highlight row into memory.
     *
     * @return Collection<int, object>
     */
    public function markedPassages(int $limit = 50): Collection
    {
        return $this->highlights()
            ->selectRaw('block_index, start_offset, end_offset, MIN(quote) as quote, COUNT(*) as marks')
            ->groupBy('block_index', 'start_offset', 'end_offset')
            ->orderByDesc('marks')
            ->orderBy('block_index')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve the cover to a browsable URL.
     *
     * Two shapes are stored: uploads keep the disk-relative path returned by
     * Storage::store() ("cover-images/x.jpg"), while bundled seed imagery is an
     * absolute public path ("/images/covers/x.jpg"). Everything reads through
     * here so no caller has to know which it is holding.
     */
    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return str_starts_with($this->cover_image, '/')
            ? $this->cover_image
            : Storage::disk('public')->url($this->cover_image);
    }

    /** True when the cover is a user upload we own and may delete. */
    public function hasUploadedCover(): bool
    {
        return $this->cover_image !== null && ! str_starts_with($this->cover_image, '/');
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

    /**
     * Rough reading time in minutes, floored at 1. Body is HTML, so tags are
     * stripped before counting.
     */
    public static function estimateReadingTime(string $body): int
    {
        $words = str_word_count(strip_tags($body));

        return max(1, (int) ceil($words / 200));
    }
}
