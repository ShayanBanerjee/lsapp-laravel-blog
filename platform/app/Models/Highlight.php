<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Highlight extends Model
{
    protected $fillable = [
        'post_id', 'user_id', 'block_index', 'start_offset', 'end_offset', 'quote',
    ];

    protected function casts(): array
    {
        return [
            'block_index' => 'integer',
            'start_offset' => 'integer',
            'end_offset' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /** Stable identity for a passage, independent of who marked it. */
    public function passageKey(): string
    {
        return "{$this->block_index}:{$this->start_offset}:{$this->end_offset}";
    }
}
