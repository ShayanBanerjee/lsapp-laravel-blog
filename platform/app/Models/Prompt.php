<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    protected $fillable = ['universe_id', 'body'];

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }
}
