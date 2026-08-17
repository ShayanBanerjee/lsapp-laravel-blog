<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'persona_id', 'universe_id', 'slug', 'title',
        'excerpt', 'body', 'cover_image', 'status', 'reading_time', 'published_at',
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
