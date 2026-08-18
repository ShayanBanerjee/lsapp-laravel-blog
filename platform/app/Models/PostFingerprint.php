<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostFingerprint extends Model
{
    protected $fillable = ['post_id', 'shingles'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
