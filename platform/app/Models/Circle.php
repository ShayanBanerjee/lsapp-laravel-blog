<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Circle extends Model
{
    protected $fillable = [
        'slug', 'name', 'tagline', 'description', 'universe_id', 'created_by', 'sort_order',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_memberships')->withTimestamps();
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'circle_post')->withTimestamps();
    }
}
