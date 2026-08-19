<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopyFlag extends Model
{
    /** A passage-level overlap: some of this text appears in the other piece. */
    public const CONTAINMENT = 'containment';

    /** A whole-document match: the two pieces are substantially the same. */
    public const DUPLICATE = 'duplicate';

    public const OPEN = 'open';

    public const CLEARED = 'cleared';

    public const UPHELD = 'upheld';

    protected $fillable = ['post_id', 'matched_post_id', 'kind', 'similarity', 'status'];

    protected function casts(): array
    {
        return ['similarity' => 'float'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function matchedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'matched_post_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }
}
