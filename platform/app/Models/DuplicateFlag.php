<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateFlag extends Model
{
    protected $fillable = ['post_id', 'matched_post_id', 'containment', 'shared', 'status'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function matched(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'matched_post_id');
    }
}
